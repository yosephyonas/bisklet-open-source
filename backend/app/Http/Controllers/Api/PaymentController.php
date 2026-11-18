<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\PaymentStoreRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Ride;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function store(PaymentStoreRequest $request, Ride $ride): JsonResponse
    {
        $user = $request->user();

        if ($ride->user_id !== $user->id && $user->role !== UserRole::Admin) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to record payments for this ride.');
        }

        if ($ride->status !== RideStatus::Completed) {
            throw ValidationException::withMessages([
                'ride' => 'Payments can only be attached to completed rides.',
            ]);
        }

        $data = $request->validated();
        $idempotencyKey = $request->header('Idempotency-Key') ?: ($data['idempotency_key'] ?? null);

        $payment = $this->payments->createPendingForRide($ride, $data, $idempotencyKey);

        return PaymentResource::make($payment)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
