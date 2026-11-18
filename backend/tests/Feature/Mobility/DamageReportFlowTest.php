<?php

namespace Tests\Feature\Mobility;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Models\Bike;
use App\Models\Device;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DamageReportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function testRiderDamageReportCreatesMaintenanceTaskAndOperationalAlert(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);
        $device = Device::query()->create([
            'imei' => '867250300123456',
            'is_active' => true,
        ]);
        $bike = Bike::factory()->create([
            'station_id' => null,
            'imei' => $device->imei,
            'code' => 'BIKE-456',
            'status' => BikeStatus::Available->value,
            'is_active' => true,
        ]);
        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $bike->id,
            'imei' => $bike->imei,
            'status' => RideStatus::Completed->value,
            'started_at' => now()->subMinutes(20),
            'ended_at' => now()->subMinutes(5),
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);

        $response = $this->postJson("/api/v1/bikes/{$bike->id}/damage-reports", [
            'ride_id' => $ride->id,
            'category' => 'brake',
            'severity' => 'critical',
            'description' => 'Front brake is not responding well.',
            'affects_safety' => true,
            'latitude' => 9.0123456,
            'longitude' => 38.7654321,
            'photo_urls' => ['https://example.com/brake.jpg'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.bike_id', $bike->id)
            ->assertJsonPath('data.ride_id', $ride->id)
            ->assertJsonPath('data.category', 'brake')
            ->assertJsonPath('data.severity', 'critical');

        $this->assertDatabaseHas('damage_reports', [
            'bike_id' => $bike->id,
            'ride_id' => $ride->id,
            'user_id' => $rider->id,
            'category' => 'brake',
            'severity' => 'critical',
            'affects_safety' => true,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('maintenance_logs', [
            'bike_id' => $bike->id,
            'user_id' => $rider->id,
            'type' => 'damage_report:brake',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('operational_alerts', [
            'bike_id' => $bike->id,
            'imei' => $bike->imei,
            'source' => 'damage_report',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => 'operational_alert',
            'imei' => $bike->imei,
        ]);

        $bike->refresh();
        $this->assertSame(BikeStatus::Maintenance, $bike->status);
        $this->assertFalse($bike->is_active);
    }

    public function testDamageReportRejectsRideForDifferentBike(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);
        $bike = Bike::factory()->create(['station_id' => null]);
        $otherBike = Bike::factory()->create(['station_id' => null]);
        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $otherBike->id,
            'status' => RideStatus::Completed->value,
            'started_at' => now()->subMinutes(20),
            'ended_at' => now()->subMinutes(5),
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);

        $this->postJson("/api/v1/bikes/{$bike->id}/damage-reports", [
            'ride_id' => $ride->id,
            'category' => 'tire',
            'severity' => 'moderate',
            'description' => 'Rear tire looks flat.',
        ])->assertStatus(422);
    }
}
