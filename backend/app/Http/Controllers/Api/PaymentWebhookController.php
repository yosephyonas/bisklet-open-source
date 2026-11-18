<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function store(Request $request, PaymentService $payments, string $provider): JsonResponse
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        $event = $payments->ingestWebhook(
            provider: $provider,
            payload: $payload,
            signature: $request->header('X-Bisklet-Signature')
                ?? $request->header('X-Webhook-Signature')
                ?? $request->header('X-Chapa-Signature'),
        );

        return response()->json([
            'status' => $event->status,
            'event_id' => $event->event_id,
            'payment_id' => $event->payment_id,
        ], Response::HTTP_ACCEPTED);
    }
}
