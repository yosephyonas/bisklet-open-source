<?php

namespace Tests\Feature\Mobility;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Models\Bike;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function testRiderPaymentRequestCannotMarkPaymentPaid(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);
        $bike = Bike::factory()->create();

        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $bike->id,
            'status' => RideStatus::Completed->value,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
            'total_amount' => 25,
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);

        $this->postJson("/api/v1/rides/{$ride->id}/payments", [
            'provider' => 'manual',
            'provider_reference' => 'claimed-paid-ref',
            'amount' => 25,
            'currency' => 'ETB',
            'status' => 'completed',
            'paid_at' => now()->toIso8601String(),
            'payload' => ['trusted' => true],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.paid_at', null)
            ->assertJsonPath('data.payload', null);

        $this->assertDatabaseHas('payments', [
            'ride_id' => $ride->id,
            'user_id' => $rider->id,
            'status' => 'pending',
        ]);
    }

    public function testPaymentCreationIsIdempotentPerUserKey(): void
    {
        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);
        $bike = Bike::factory()->create();

        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $bike->id,
            'status' => RideStatus::Completed->value,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
            'total_amount' => 25,
            'currency' => 'ETB',
        ]);

        Sanctum::actingAs($rider);

        $payload = [
            'provider' => 'chapa',
            'provider_reference' => 'checkout-123',
            'amount' => 25,
            'currency' => 'ETB',
        ];

        $first = $this->withHeader('Idempotency-Key', 'pay-once-123')
            ->postJson("/api/v1/rides/{$ride->id}/payments", $payload)
            ->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'pay-once-123')
            ->postJson("/api/v1/rides/{$ride->id}/payments", [
                ...$payload,
                'provider_reference' => 'checkout-123-retry',
            ])
            ->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Payment::query()->where('ride_id', $ride->id)->count());
        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'billing_status' => 'payment_pending',
        ]);
    }

    public function testSignedPaymentWebhookMarksPaymentPaid(): void
    {
        config(['services.payments.webhook_secret' => 'payment-secret']);

        $rider = User::factory()->create([
            'role' => UserRole::Rider,
            'fyda_verified_at' => now(),
        ]);
        $bike = Bike::factory()->create();

        $ride = Ride::query()->create([
            'user_id' => $rider->id,
            'bike_id' => $bike->id,
            'status' => RideStatus::Completed->value,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
            'total_amount' => 25,
            'billing_status' => 'payment_pending',
            'currency' => 'ETB',
        ]);

        $payment = Payment::query()->create([
            'ride_id' => $ride->id,
            'user_id' => $rider->id,
            'provider' => 'chapa',
            'provider_reference' => 'checkout-paid-123',
            'amount' => 25,
            'currency' => 'ETB',
            'status' => 'pending',
        ]);

        $payload = [
            'event_id' => 'evt-paid-123',
            'status' => 'success',
            'reference' => 'checkout-paid-123',
            'paid_at' => now()->toIso8601String(),
        ];
        $json = json_encode($payload);
        $signature = hash_hmac('sha256', $json, 'payment-secret');

        $this->withHeader('X-Bisklet-Signature', 'sha256='.$signature)
            ->postJson('/api/v1/payments/webhooks/chapa', $payload)
            ->assertAccepted()
            ->assertJsonPath('status', 'processed')
            ->assertJsonPath('payment_id', $payment->id);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('rides', [
            'id' => $ride->id,
            'billing_status' => 'paid',
        ]);
    }

    public function testPaymentWebhookRequiresValidSignature(): void
    {
        config(['services.payments.webhook_secret' => 'payment-secret']);

        $this->postJson('/api/v1/payments/webhooks/chapa', [
            'event_id' => 'evt-missing-signature',
        ])->assertUnauthorized();
    }
}
