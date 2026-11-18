<?php

namespace Tests\Feature\Mobility;

use App\Enums\BikeStatus;
use App\Enums\UserRole;
use App\Models\Bike;
use App\Models\Device;
use App\Models\OperationalAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\SignsGatewayRequests;
use Tests\TestCase;

class OperationalAlertFlowTest extends TestCase
{
    use RefreshDatabase;
    use SignsGatewayRequests;

    public function test_locked_movement_alarm_creates_critical_operational_alert(): void
    {
        config(['services.go_gateway.webhook_token' => 'test-gateway-token']);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $device = Device::query()->create([
            'imei' => '867250377777777',
            'lock_status' => 1,
            'is_active' => true,
        ]);
        $bike = Bike::factory()->create([
            'station_id' => null,
            'imei' => $device->imei,
            'code' => 'BIKE-777',
            'status' => BikeStatus::Available->value,
        ]);

        $payload = [
            'event' => 'alarm.triggered',
            'payload' => [
                'imei' => $device->imei,
                'type' => 1,
                'lat' => 9.0123456,
                'lng' => 38.7654321,
                'received_at' => now()->toIso8601String(),
            ],
        ];
        $this->withHeaders($this->gatewaySignatureHeaders('/api/v1/lock/events', $payload))
            ->postJson('/api/v1/lock/events', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $alert = OperationalAlert::query()->first();

        $this->assertNotNull($alert);
        $this->assertSame('lock_alarm', $alert->source);
        $this->assertSame('critical', $alert->severity);
        $this->assertSame($bike->id, $alert->bike_id);
        $this->assertSame('Locked bike movement detected', $alert->title);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => 'operational_alert',
            'imei' => $device->imei,
        ]);
    }

    public function test_low_battery_alert_is_resolved_when_battery_recovers(): void
    {
        config(['services.go_gateway.webhook_token' => 'test-gateway-token']);
        config(['services.locks.low_battery_threshold' => 20]);

        $device = Device::query()->create([
            'imei' => '867250388888888',
            'lock_status' => 1,
            'is_active' => true,
        ]);

        $lowBattery = [
            'event' => 'telemetry.heartbeat',
            'payload' => [
                'imei' => $device->imei,
                'lock_status' => 1,
                'battery_voltage' => 350,
                'signal_strength' => 22,
                'received_at' => now()->toIso8601String(),
            ],
        ];
        $this->withHeaders($this->gatewaySignatureHeaders('/api/v1/lock/events', $lowBattery))
            ->postJson('/api/v1/lock/events', $lowBattery)
            ->assertOk();

        $this->assertDatabaseHas('operational_alerts', [
            'imei' => $device->imei,
            'source' => 'low_battery',
            'status' => 'open',
        ]);

        $recovered = [
            'event' => 'telemetry.heartbeat',
            'payload' => [
                'imei' => $device->imei,
                'lock_status' => 1,
                'battery_voltage' => 412,
                'signal_strength' => 22,
                'received_at' => now()->toIso8601String(),
            ],
        ];
        $this->withHeaders($this->gatewaySignatureHeaders('/api/v1/lock/events', $recovered))
            ->postJson('/api/v1/lock/events', $recovered)
            ->assertOk();

        $this->assertDatabaseHas('operational_alerts', [
            'imei' => $device->imei,
            'source' => 'low_battery',
            'status' => 'resolved',
        ]);
    }

    public function test_offline_scan_creates_offline_lock_alert(): void
    {
        $device = Device::query()->create([
            'imei' => '867250399999998',
            'is_active' => true,
            'last_seen_at' => now()->subHour(),
        ]);

        Artisan::call('locks:scan-offline', ['--minutes' => 15]);

        $this->assertDatabaseHas('operational_alerts', [
            'imei' => $device->imei,
            'source' => 'offline_lock',
            'status' => 'open',
        ]);
    }
}
