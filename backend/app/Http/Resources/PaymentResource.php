<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_id' => $this->ride_id,
            'user_id' => $this->user_id,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
