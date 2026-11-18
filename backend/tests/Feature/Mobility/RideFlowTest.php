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
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_start_and_complete_ride(): void
    {
        $startedAt = Carbon::create(2026, 1, 1, 10, 0, 0);
        Carbon::setTestNow($startedAt);

        $station = Station::factory()->create();

        $bike = Bike::factory()->create([
            'station_id' => $station->id,
            'status' => BikeStatus::Available->value,
            'unlock_fee' => 5,
            'price_per_minute' => 2,
        ]);

        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        Sanctum::actingAs($rider);

        $startResponse = $this->postJson('/api/v1/rides', [
            'bike_id' => $bike->id,
        ]);

        $startResponse->assertCreated();

        $rideId = $startResponse->json('data.id');

        $this->assertDatabaseHas('rides', [
            'id' => $rideId,
            'status' => RideStatus::Active->value,
            'user_id' => $rider->id,
        ]);

        $this->assertDatabaseHas('bikes', [
            'id' => $bike->id,
            'status' => BikeStatus::InRide->value,
        ]);

        Carbon::setTestNow($startedAt->copy()->addMinutes(5));

        $completeResponse = $this->postJson("/api/v1/rides/{$rideId}/complete", [
            'end_station_id' => $station->id,
            'distance_m' => 1500,
            'per_minute_rate' => 0,
            'unlock_fee' => 0,
        ]);

        $completeResponse->assertOk()
            ->assertJsonPath('data.status', RideStatus::Completed->value)
            ->assertJsonPath('data.unlock_fee', 5)
            ->assertJsonPath('data.per_minute_rate', 0.5)
            ->assertJsonPath('data.total_amount', 15)
            ->assertJsonPath('data.pricing_breakdown.billing_model', 'started_time_blocks')
            ->assertJsonPath('data.pricing_breakdown.block_price', 10)
            ->assertJsonPath('data.pricing_breakdown.block_minutes', 20)
            ->assertJsonPath('data.pricing_breakdown.billed_blocks', 1)
            ->assertJsonPath('data.duration_seconds', 300)
            ->assertJsonPath('data.minutes', 5);

        $this->assertDatabaseHas('rides', [
            'id' => $rideId,
            'status' => RideStatus::Completed->value,
            'unlock_fee' => 5.0,
            'per_minute_rate' => 0.5,
            'total_amount' => 15.0,
            'duration_seconds' => 300,
            'minutes' => 5,
        ]);

        $this->assertDatabaseHas('bikes', [
            'id' => $bike->id,
            'status' => BikeStatus::Available->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_rider_cannot_start_second_active_ride(): void
    {
        $bikeOne = Bike::factory()->create([
            'status' => BikeStatus::Available->value,
        ]);

        $bikeTwo = Bike::factory()->create([
            'status' => BikeStatus::Available->value,
        ]);

        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        Sanctum::actingAs($rider);

        $this->postJson('/api/v1/rides', [
            'bike_id' => $bikeOne->id,
        ])->assertCreated();

        $secondAttempt = $this->postJson('/api/v1/rides', [
            'bike_id' => $bikeTwo->id,
        ]);

        $secondAttempt->assertStatus(422);
    }

    public function test_rider_can_complete_lock_only_ride_without_linked_bike(): void
    {
        $startedAt = Carbon::create(2026, 1, 1, 10, 0, 0);
        Carbon::setTestNow($startedAt);

        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        $device = Device::query()->create([
            'imei' => '867250377777777',
            'lock_code' => 'LOCK-777',
            'status' => 'online',
            'lock_status' => 0,
            'is_active' => true,
        ]);

        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => null,
            'imei' => $device->imei,
            'status' => RideStatus::Active->value,
            'started_at' => $startedAt,
            'unlock_fee' => 5,
            'per_minute_rate' => 2,
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);
        Carbon::setTestNow($startedAt->copy()->addMinutes(4));

        $this->postJson("/api/v1/rides/{$ride->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', RideStatus::Completed->value)
            ->assertJsonPath('data.bike_id', null)
            ->assertJsonPath('data.total_amount', 15);

        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'bike_id' => null,
            'status' => RideStatus::Completed->value,
            'minutes' => 4,
            'total_amount' => 15.0,
        ]);

        Carbon::setTestNow();
    }

    public function test_completing_already_completed_ride_returns_quick_completed_response(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);

        $device = Device::query()->create([
            'imei' => '867250377777778',
            'lock_code' => 'LOCK-778',
            'status' => 'online',
            'lock_status' => 1,
            'is_active' => true,
        ]);

        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => null,
            'imei' => $device->imei,
            'status' => RideStatus::Completed->value,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
            'duration_seconds' => 300,
            'minutes' => 5,
            'unlock_fee' => 5,
            'per_minute_rate' => 2,
            'total_amount' => 15,
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);

        $this->postJson("/api/v1/rides/{$ride->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', RideStatus::Completed->value)
            ->assertJsonPath('data.total_amount', 15);
    }

    public function test_started_twenty_minute_blocks_are_charged_at_the_boundary(): void
    {
        $startedAt = Carbon::create(2026, 1, 1, 10, 0, 0);
        Carbon::setTestNow($startedAt);

        $bike = Bike::factory()->create([
            'status' => BikeStatus::Available->value,
            'unlock_fee' => 99,
            'price_per_minute' => 99,
        ]);
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);
        Sanctum::actingAs($rider);

        $rideId = $this->postJson('/api/v1/rides', ['bike_id' => $bike->id])
            ->assertCreated()
            ->assertJsonPath('data.estimated_total_amount', 15)
            ->json('data.id');

        Carbon::setTestNow($startedAt->copy()->addMinutes(20)->addSecond());

        $this->postJson("/api/v1/rides/{$rideId}/complete")
            ->assertOk()
            ->assertJsonPath('data.minutes', 21)
            ->assertJsonPath('data.total_amount', 25)
            ->assertJsonPath('data.pricing_breakdown.billed_blocks', 2)
            ->assertJsonPath('data.pricing_breakdown.ride_charge', 20)
            ->assertJsonPath('data.pricing_breakdown.unlock_fee', 5);

        Carbon::setTestNow();
    }
}
