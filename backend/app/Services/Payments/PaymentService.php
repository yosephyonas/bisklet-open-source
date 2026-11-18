<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Ride;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function createPendingForRide(Ride $ride, array $data, ?string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($ride, $data, $idempotencyKey) {
            $ride = Ride::query()
                ->whereKey($ride->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = round((float) $data['amount'], 2);
            $expectedAmount = round((float) ($ride->total_amount ?? 0), 2);

            if ($expectedAmount > 0 && abs($amount - $expectedAmount) > 0.01) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must match the ride total.',
                ]);
            }

            if ($idempotencyKey) {
                $existing = Payment::query()
                    ->where('user_id', $ride->user_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $existingProviderPayment = Payment::query()
                ->where('provider', $data['provider'])
                ->where('provider_reference', $data['provider_reference'])
                ->lockForUpdate()
                ->first();

            if ($existingProviderPayment) {
                return $existingProviderPayment;
            }

            $payment = Payment::query()->create([
                'ride_id' => $ride->id,
                'user_id' => $ride->user_id,
                'provider' => $data['provider'],
                'provider_reference' => $data['provider_reference'],
                'amount' => $amount,
                'currency' => $data['currency'] ?? $ride->currency ?? 'ETB',
                'status' => 'pending',
                'payload' => null,
                'metadata' => $data['metadata'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'paid_at' => null,
            ]);

            if ($ride->billing_status !== 'paid') {
                $ride->billing_status = 'payment_pending';
                $ride->save();
            }

            return $payment;
        });
    }

    public function ingestWebhook(string $provider, array $payload, ?string $signature): PaymentWebhookEvent
    {
        $provider = Str::lower($provider);
        $eventId = $this->resolveEventId($provider, $payload);

        try {
            $event = PaymentWebhookEvent::query()->firstOrCreate(
                [
                    'provider' => $provider,
                    'event_id' => $eventId,
                ],
                [
                    'provider_reference' => $this->resolveProviderReference($payload),
                    'status' => 'received',
                    'payload' => $payload,
                    'signature' => $signature,
                    'received_at' => now(),
                ],
            );
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            $event = PaymentWebhookEvent::query()
                ->where('provider', $provider)
                ->where('event_id', $eventId)
                ->firstOrFail();
        }

        if ($event->processed_at !== null) {
            return $event;
        }

        return DB::transaction(function () use ($event, $payload) {
            $event = PaymentWebhookEvent::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($event->processed_at !== null) {
                return $event;
            }

            $providerReference = $event->provider_reference ?: $this->resolveProviderReference($payload);
            $normalizedStatus = $this->normalizeStatus($payload);

            $event->provider_reference = $providerReference;
            $event->status = 'processing';
            $event->save();

            if (! $providerReference) {
                $event->status = 'needs_reconciliation';
                $event->error_message = 'Webhook did not include a provider reference.';
                $event->processed_at = now();
                $event->save();

                return $event;
            }

            $payment = Payment::query()
                ->where('provider', $event->provider)
                ->where('provider_reference', $providerReference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                $event->status = 'needs_reconciliation';
                $event->error_message = 'No local payment matched the webhook provider reference.';
                $event->processed_at = now();
                $event->save();

                return $event;
            }

            $ride = Ride::query()
                ->whereKey($payment->ride_id)
                ->lockForUpdate()
                ->first();

            $payment->payload = $payload;

            if ($normalizedStatus === 'paid') {
                $payment->status = 'paid';
                $payment->paid_at = $this->resolvePaidAt($payload) ?? now();

                if ($ride) {
                    $ride->billing_status = 'paid';
                    $ride->save();
                }
            } elseif ($normalizedStatus === 'failed') {
                $payment->status = 'failed';

                if ($ride && $ride->billing_status !== 'paid') {
                    $ride->billing_status = 'payment_failed';
                    $ride->save();
                }
            } else {
                $payment->status = 'pending';

                if ($ride && $ride->billing_status !== 'paid') {
                    $ride->billing_status = 'payment_pending';
                    $ride->save();
                }
            }

            $payment->save();

            $event->status = 'processed';
            $event->processed_at = now();
            $event->payment_id = $payment->id;
            $event->error_message = null;
            $event->save();

            return $event;
        });
    }

    private function resolveEventId(string $provider, array $payload): string
    {
        $candidate = Arr::get($payload, 'event_id')
            ?? Arr::get($payload, 'id')
            ?? Arr::get($payload, 'data.id')
            ?? Arr::get($payload, 'reference')
            ?? Arr::get($payload, 'tx_ref')
            ?? Arr::get($payload, 'data.reference');

        if ($candidate !== null && trim((string) $candidate) !== '') {
            return trim((string) $candidate);
        }

        return hash('sha256', $provider.'|'.json_encode($payload));
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'constraint');
    }

    private function resolveProviderReference(array $payload): ?string
    {
        $candidate = Arr::get($payload, 'provider_reference')
            ?? Arr::get($payload, 'reference')
            ?? Arr::get($payload, 'tx_ref')
            ?? Arr::get($payload, 'transaction_reference')
            ?? Arr::get($payload, 'data.reference')
            ?? Arr::get($payload, 'data.tx_ref')
            ?? Arr::get($payload, 'data.transaction_reference');

        if ($candidate === null) {
            return null;
        }

        $candidate = trim((string) $candidate);

        return $candidate !== '' ? $candidate : null;
    }

    private function normalizeStatus(array $payload): string
    {
        $status = Str::lower((string) (
            Arr::get($payload, 'status')
            ?? Arr::get($payload, 'event')
            ?? Arr::get($payload, 'data.status')
            ?? ''
        ));

        return match (true) {
            in_array($status, ['success', 'succeeded', 'paid', 'completed', 'charge.success'], true) => 'paid',
            in_array($status, ['failed', 'failure', 'cancelled', 'canceled', 'expired', 'charge.failed'], true) => 'failed',
            default => 'pending',
        };
    }

    private function resolvePaidAt(array $payload): ?Carbon
    {
        $value = Arr::get($payload, 'paid_at')
            ?? Arr::get($payload, 'completed_at')
            ?? Arr::get($payload, 'data.paid_at')
            ?? Arr::get($payload, 'data.completed_at');

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
