<?php

namespace Tests\Feature\Mobility;

use App\Enums\UserRole;
use App\Models\Bike;
use App\Models\User;
use App\Services\Mapbox\MapboxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BikeTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function testTelemetryUpdatesBikeLocation(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        $bike = Bike::factory()->create([
            'last_latitude' => null,
            'last_longitude' => null,
            'location_accuracy' => null,
            'location_updated_at' => null,
        ]);

        Sanctum::actingAs($rider);

        $this->mock(MapboxClient::class, function ($mock) {
            $mock->shouldReceive('snapToRoad')
                ->once()
                ->andReturn([
                    'matched' => true,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[0, 0], [1, 1]],
                    ],
                ]);
        });

        $payload = [
            'latitude' => 29.993827,
            'longitude' => 31.241999,
            'bearing' => 120.2,
            'speed_kph' => 15.5,
            'accuracy_m' => 4.5,
            'battery_percent' => 87.5,
            'odometer_km' => 342.89,
        ];

        $response = $this->postJson("/api/v1/bikes/{$bike->id}/telemetry", $payload);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.current_location.latitude', $payload['latitude'])
            ->assertJsonPath('data.current_location.longitude', $payload['longitude'])
            ->assertJsonPath('data.current_location.accuracy_m', $payload['accuracy_m'])
            ->assertJsonPath('data.metadata.telemetry.latitude', $payload['latitude']);

        $this->assertNotNull($response->json('data.current_location.updated_at'));

        $this->assertDatabaseHas('bikes', [
            'id' => $bike->id,
            'last_latitude' => '29.9938270',
            'last_longitude' => '31.2419990',
            'location_accuracy' => '4.50',
        ]);
    }
}
