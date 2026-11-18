<?php

namespace Tests\Feature\Mobility;

use App\Models\Device;
use App\Models\LockLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SignsGatewayRequests;
use Tests\TestCase;

class GatewayWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;
    use SignsGatewayRequests;

    public function test_lock_events_require_gateway_token(): void
    {
        config(['services.go_gateway.webhook_token' => 'test-gateway-token']);

        $this->postJson('/api/v1/lock/events', [
            'event' => 'device.online',
            'payload' => ['imei' => '867250300000001'],
        ])->assertUnauthorized();

        $this->withHeader('X-Gateway-Token', 'test-gateway-token')
            ->postJson('/api/v1/lock/events', [
                'event' => 'device.online',
                'payload' => ['imei' => '867250300000001'],
            ])->assertUnauthorized();

        $payload = [
            'event' => 'device.online',
            'payload' => ['imei' => '867250300000001'],
        ];
        $this->withHeaders($this->gatewaySignatureHeaders('/api/v1/lock/events', $payload))
            ->postJson('/api/v1/lock/events', $payload)
            ->assertOk();
    }

    public function test_gateway_webhook_rejects_expired_and_tampered_signatures(): void
    {
        config(['services.go_gateway.webhook_token' => 'test-gateway-token']);
        $uri = '/api/v1/lock/events';
        $payload = [
            'event' => 'device.online',
            'payload' => ['imei' => '867250300000009'],
        ];

        $headers = $this->gatewaySignatureHeaders($uri, $payload);
        $headers['X-Gateway-Timestamp'] = (string) now()->subMinutes(10)->timestamp;
        $this->withHeaders($headers)->postJson($uri, $payload)->assertUnauthorized();

        $headers = $this->gatewaySignatureHeaders($uri, $payload);
        $payload['payload']['imei'] = '867250300000010';
        $this->withHeaders($headers)->postJson($uri, $payload)->assertUnauthorized();
    }

    public function test_laravel_signs_outbound_gateway_commands(): void
    {
        $token = str_repeat('x', 32); // Generated test-only placeholder, not a real credential.
        config([
            'services.go_gateway.base_url' => 'http://gateway:9001',
            'services.go_gateway.command_token' => $token,
        ]);
        Http::fake(['http://gateway:9001/*' => Http::response(['status' => 'sent'])]);

        Http::post('http://gateway:9001/commands/reboot', [
            'imei' => '867250300000001',
            'request_id' => 'request-1',
        ])->throw();

        Http::assertSent(function (ClientRequest $request) use ($token): bool {
            $timestamp = $request->header('X-Gateway-Timestamp')[0] ?? '';
            $nonce = $request->header('X-Gateway-Nonce')[0] ?? '';
            $signature = $request->header('X-Gateway-Signature')[0] ?? '';
            $canonical = implode("\n", [
                $timestamp,
                $nonce,
                'POST',
                '/commands/reboot',
                hash('sha256', $request->body()),
            ]);

            return ctype_digit($timestamp)
                && $nonce !== ''
                && hash_equals(hash_hmac('sha256', $canonical, $token), $signature);
        });
    }

    public function test_device_telemetry_requires_gateway_token(): void
    {
        config(['services.go_gateway.webhook_token' => 'test-gateway-token']);

        $payload = [
            'imei' => '867250300000002',
            'type' => 'H0',
        ];

        $this->postJson('/api/v1/devices/telemetry', $payload)
            ->assertUnauthorized();

        $this->withHeaders($this->gatewaySignatureHeaders('/api/v1/devices/telemetry', $payload))
            ->postJson('/api/v1/devices/telemetry', $payload)
            ->assertAccepted();
    }

    public function test_invalid_gps_does_not_overwrite_device_last_known_location(): void
    {
        config(['services.go_gateway.webhook_token' => 'test-gateway-token']);

        $device = Device::query()->create([
            'imei' => '867250300000003',
            'last_lat' => 9.0100000,
            'last_lng' => 38.7600000,
            'last_gps_at' => now()->subHour(),
        ]);

        $event = [
            'event' => 'telemetry.gps',
            'imei' => $device->imei,
            'payload' => [
                'imei' => $device->imei,
                'lat' => 0,
                'lng' => 0,
                'is_valid' => false,
                'sat' => 0,
                'hdop' => 0,
                'source' => 'D0',
                'received_at' => now()->toIso8601String(),
            ],
        ];
        $this->withHeaders($this->gatewaySignatureHeaders('/api/v1/lock/events', $event))
            ->postJson('/api/v1/lock/events', [
                ...$event,
            ])->assertOk();

        $device->refresh();

        $this->assertEqualsWithDelta(9.01, (float) $device->last_lat, 0.000001);
        $this->assertEqualsWithDelta(38.76, (float) $device->last_lng, 0.000001);
        $this->assertDatabaseHas('lock_locations', [
            'imei' => $device->imei,
            'lat' => '0.0000000',
            'lng' => '0.0000000',
            'is_valid' => false,
        ]);
    }

    public function test_gateway_event_receipts_dedupe_retries(): void
    {
        config(['services.go_gateway.webhook_token' => 'test-gateway-token']);

        $device = Device::query()->create([
            'imei' => '867250300000004',
            'is_active' => true,
        ]);

        $payload = [
            'event' => 'telemetry.gps',
            'event_id' => 'gateway-event-123',
            'occurred_at' => '2026-05-12T00:00:00Z',
            'payload' => [
                'imei' => $device->imei,
                'lat' => 9.0123456,
                'lng' => 38.7654321,
                'is_valid' => true,
                'sat' => 7,
                'hdop' => 1.2,
                'source' => 'D0',
                'received_at' => '2026-05-12T00:00:00Z',
            ],
        ];

        $this->withHeaders($this->gatewaySignatureHeaders('/api/v1/lock/events', $payload))
            ->postJson('/api/v1/lock/events', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->withHeaders($this->gatewaySignatureHeaders('/api/v1/lock/events', $payload))
            ->postJson('/api/v1/lock/events', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, LockLocation::query()->where('imei', $device->imei)->count());
    }
}
