<?php

namespace Tests\Feature\Mobility;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Models\Bike;
use App\Models\Device;
use App\Models\Ride;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BikeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_bike(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'fyda_verified_at' => now(),
        ]);

        $station = Station::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/bikes', [
            'station_id' => $station->id,
            'code' => 'BK1001',
            'model' => 'Urban Rider',
            'status' => BikeStatus::Available->value,
            'price_per_minute' => 2.5,
            'unlock_fee' => 10,
            'last_latitude' => 40.7128,
            'last_longitude' => -74.0060,
            'location_accuracy' => 6.5,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'BK1001')
            ->assertJsonPath('data.price_per_minute', 0.5)
            ->assertJsonPath('data.unlock_fee', 5)
            ->assertJsonPath('data.price_birr', 10)
            ->assertJsonPath('data.price_minutes', 20)
            ->assertJsonPath('data.current_location.latitude', 40.7128)
            ->assertJsonPath('data.current_location.longitude', -74.0060);

        $this->assertDatabaseHas('bikes', [
            'code' => 'BK1001',
            'station_id' => $station->id,
            'price_per_minute' => 0.5,
            'unlock_fee' => 5,
            'last_latitude' => '40.7128000',
            'last_longitude' => '-74.0060000',
        ]);
    }

    public function test_rider_cannot_manage_bikes(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        Sanctum::actingAs($rider);

        $response = $this->postJson('/api/v1/bikes', [
            'code' => 'BK2001',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_delete_bike_without_active_ride(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'fyda_verified_at' => now(),
        ]);

        $bike = Bike::factory()->create([
            'status' => BikeStatus::Available->value,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson('/api/v1/bikes/'.$bike->id);

        $response->assertOk()->assertJson(['message' => 'Bike deleted.']);

        $this->assertDatabaseMissing('bikes', [
            'id' => $bike->id,
        ]);
    }

    public function test_active_ride_bike_is_hidden_from_default_bike_feed(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        $availableBike = Bike::factory()->create([
            'station_id' => null,
            'code' => 'BK-FREE',
            'status' => BikeStatus::Available->value,
            'is_active' => true,
        ]);

        $inUseImei = '867250377777777';
        Device::query()->create([
            'imei' => $inUseImei,
            'status' => 'online',
            'is_active' => true,
        ]);

        $inUseBike = Bike::factory()->create([
            'station_id' => null,
            'code' => 'BK-RIDING',
            'imei' => $inUseImei,
            'status' => BikeStatus::Available->value,
            'is_active' => true,
        ]);

        Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $inUseBike->id,
            'imei' => $inUseBike->imei,
            'status' => RideStatus::Active->value,
            'started_at' => now()->subMinutes(4),
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);

        $response = $this->getJson('/api/v1/bikes');

        $response->assertOk()
            ->assertJsonFragment(['code' => $availableBike->code])
            ->assertJsonMissing(['code' => $inUseBike->code]);
    }

    public function test_active_ride_bike_shows_in_ride_when_included_explicitly(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        $imei = '867250388888888';
        Device::query()->create([
            'imei' => $imei,
            'status' => 'online',
            'is_active' => true,
        ]);

        $bike = Bike::factory()->create([
            'station_id' => null,
            'code' => 'BK-INRIDE',
            'imei' => $imei,
            'status' => BikeStatus::Available->value,
            'is_active' => true,
        ]);

        Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $bike->id,
            'imei' => $bike->imei,
            'status' => RideStatus::Active->value,
            'started_at' => now()->subMinutes(4),
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);

        $this->getJson('/api/v1/bikes?include_unavailable=1')
            ->assertOk()
            ->assertJsonFragment([
                'code' => $bike->code,
                'status' => BikeStatus::InRide->value,
                'is_available' => false,
            ]);
    }
}
