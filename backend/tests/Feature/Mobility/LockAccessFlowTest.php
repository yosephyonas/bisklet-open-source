<?php

namespace Tests\Feature\Mobility;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Models\Bike;
use App\Models\Device;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LockAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_rescan_invalidates_previous_unused_token(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        [$device, $bike] = $this->createLinkedLock(
            imei: '867250311111111',
            lockCode: 'LOCK-111',
            bikeCode: 'BIKE-111',
        );

        Sanctum::actingAs($rider);

        $firstScan = $this->postJson('/api/v1/locks/scan', [
            'identifier' => $bike->code,
        ])->assertOk()
            ->assertJsonPath('bike_code', $bike->code)
            ->assertJsonPath('bike_status', BikeStatus::Available->value)
            ->assertJsonPath('bike_is_active', true)
            ->assertJsonPath('device_is_active', true)
            ->assertJsonPath('price_per_minute', 0.5)
            ->assertJsonPath('unlock_fee', 5)
            ->assertJsonPath('price_birr', 10)
            ->assertJsonPath('price_minutes', 20)
            ->assertJsonPath('billing_model', 'started_time_blocks');

        $secondScan = $this->postJson('/api/v1/locks/scan', [
            'identifier' => $bike->code,
        ])->assertOk();

        Http::fake([
            '*' => Http::response(['result' => 0], 200),
        ]);

        $this->postJson("/api/v1/locks/{$device->imei}/unlock", [
            'scan_token' => $firstScan->json('scan_token'),
        ])->assertStatus(403);

        $this->postJson("/api/v1/locks/{$device->imei}/unlock", [
            'scan_token' => $secondScan->json('scan_token'),
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('active_ride.bike_id', $bike->id);

        Http::assertSentCount(1);
    }

    public function test_unlock_returns_existing_active_ride_for_same_bike_without_gateway_fanout(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        [$device, $bike] = $this->createLinkedLock(
            imei: '867250322222222',
            lockCode: 'LOCK-222',
            bikeCode: 'BIKE-222',
        );

        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $bike->id,
            'imei' => $device->imei,
            'status' => RideStatus::Active->value,
            'started_at' => now()->subMinutes(3),
            'unlock_fee' => $bike->unlock_fee,
            'per_minute_rate' => $bike->price_per_minute,
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);
        Http::fake();

        $response = $this->postJson("/api/v1/locks/{$device->imei}/unlock", [
            'scan_token' => 'already-active',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('result.message', 'Ride already active')
            ->assertJsonPath('active_ride.id', $ride->id);

        Http::assertNothingSent();
    }

    public function test_unlock_blocks_starting_second_bike_when_user_already_has_active_ride(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        [$activeDevice, $activeBike] = $this->createLinkedLock(
            imei: '867250333333333',
            lockCode: 'LOCK-333',
            bikeCode: 'BIKE-333',
        );
        [$secondDevice, $secondBike] = $this->createLinkedLock(
            imei: '867250344444444',
            lockCode: 'LOCK-444',
            bikeCode: 'BIKE-444',
        );

        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $activeBike->id,
            'imei' => $activeDevice->imei,
            'status' => RideStatus::Active->value,
            'started_at' => now()->subMinutes(5),
            'unlock_fee' => $activeBike->unlock_fee,
            'per_minute_rate' => $activeBike->price_per_minute,
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);

        $scan = $this->postJson('/api/v1/locks/scan', [
            'identifier' => $secondBike->code,
        ])->assertOk();

        Http::fake();

        $response = $this->postJson("/api/v1/locks/{$secondDevice->imei}/unlock", [
            'scan_token' => $scan->json('scan_token'),
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error', 'You already have an active ride')
            ->assertJsonPath('active_ride.id', $ride->id);

        Http::assertNothingSent();
    }

    public function test_unlock_blocks_bike_already_in_ride_by_another_user(): void
    {
        $firstRider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);
        $secondRider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        [$device, $bike] = $this->createLinkedLock(
            imei: '867250399999999',
            lockCode: 'LOCK-999',
            bikeCode: 'BIKE-999',
        );

        Sanctum::actingAs($secondRider);

        $scan = $this->postJson('/api/v1/locks/scan', [
            'identifier' => $bike->code,
        ])->assertOk();

        Ride::query()->create([
            'user_id' => $firstRider->id,
            'bike_id' => $bike->id,
            'imei' => $device->imei,
            'status' => RideStatus::Active->value,
            'started_at' => now()->subMinutes(5),
            'unlock_fee' => $bike->unlock_fee,
            'per_minute_rate' => $bike->price_per_minute,
            'currency' => 'ETB',
        ]);

        Http::fake();

        $this->postJson("/api/v1/locks/{$device->imei}/unlock", [
            'scan_token' => $scan->json('scan_token'),
        ])->assertStatus(409)
            ->assertJsonPath('error', 'Bike is already in use');

        Http::assertNothingSent();
    }

    public function test_scan_blocks_bike_already_in_ride_by_another_user(): void
    {
        $firstRider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);
        $secondRider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        [$device, $bike] = $this->createLinkedLock(
            imei: '867250300000000',
            lockCode: 'LOCK-000',
            bikeCode: 'BIKE-000',
        );

        Ride::query()->create([
            'user_id' => $firstRider->id,
            'bike_id' => $bike->id,
            'imei' => $device->imei,
            'status' => RideStatus::Active->value,
            'started_at' => now()->subMinutes(5),
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($secondRider);

        $this->postJson('/api/v1/locks/scan', [
            'identifier' => $bike->code,
        ])->assertStatus(409)
            ->assertJsonPath('error', 'Bike is already in use');
    }

    public function test_scan_blocks_bike_under_maintenance(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        [, $bike] = $this->createLinkedLock(
            imei: '867250300000001',
            lockCode: 'LOCK-001',
            bikeCode: 'BIKE-001',
        );
        $bike->update(['status' => BikeStatus::Maintenance->value]);

        Sanctum::actingAs($rider);

        $this->postJson('/api/v1/locks/scan', [
            'identifier' => $bike->code,
        ])->assertStatus(409)
            ->assertJsonPath('error', 'Bike is under maintenance');
    }

    public function test_unlock_rechecks_bike_availability_after_confirmation(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        [$device, $bike] = $this->createLinkedLock(
            imei: '867250300000002',
            lockCode: 'LOCK-002',
            bikeCode: 'BIKE-002',
        );

        Sanctum::actingAs($rider);
        $scan = $this->postJson('/api/v1/locks/scan', [
            'identifier' => $bike->code,
        ])->assertOk();

        $bike->update(['status' => BikeStatus::Maintenance->value]);
        Http::fake();

        $this->postJson("/api/v1/locks/{$device->imei}/unlock", [
            'scan_token' => $scan->json('scan_token'),
        ])->assertStatus(409)
            ->assertJsonPath('error', 'Bike is under maintenance');

        Http::assertNothingSent();
    }

    public function test_database_rejects_duplicate_active_ride_for_same_user(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        [, $bikeOne] = $this->createLinkedLock(
            imei: '867250355555555',
            lockCode: 'LOCK-555',
            bikeCode: 'BIKE-555',
        );
        [, $bikeTwo] = $this->createLinkedLock(
            imei: '867250366666666',
            lockCode: 'LOCK-666',
            bikeCode: 'BIKE-666',
        );

        Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $bikeOne->id,
            'imei' => $bikeOne->imei,
            'status' => RideStatus::Active->value,
            'started_at' => now()->subMinute(),
            'unlock_fee' => $bikeOne->unlock_fee,
            'per_minute_rate' => $bikeOne->price_per_minute,
            'currency' => 'ETB',
        ]);

        $this->expectException(QueryException::class);

        Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $bikeTwo->id,
            'imei' => $bikeTwo->imei,
            'status' => RideStatus::Active->value,
            'started_at' => now(),
            'unlock_fee' => $bikeTwo->unlock_fee,
            'per_minute_rate' => $bikeTwo->price_per_minute,
            'currency' => 'ETB',
        ]);
    }

    /**
     * @return array{0: Device, 1: Bike}
     */
    private function createLinkedLock(string $imei, string $lockCode, string $bikeCode): array
    {
        $device = Device::query()->create([
            'imei' => $imei,
            'lock_code' => $lockCode,
            'status' => 'online',
            'lock_status' => 1,
            'is_active' => true,
        ]);

        $bike = Bike::factory()->create([
            'station_id' => null,
            'imei' => $imei,
            'code' => $bikeCode,
            'status' => BikeStatus::Available->value,
            'is_active' => true,
        ]);

        return [$device, $bike];
    }
}
