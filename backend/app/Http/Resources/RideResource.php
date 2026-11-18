<?php

namespace App\Http\Resources;

use App\Services\Rides\RidePricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Ride */
class RideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusValue = $this->status?->value ?? $this->status;
        $isActiveRide = $statusValue === 'active';
        $durationSeconds = $this->duration_seconds;
        if ($durationSeconds === null && $this->started_at) {
            $endTime = $this->ended_at ?? now();
            $durationSeconds = max(1, $this->started_at->diffInSeconds($endTime));
        }
        $estimatedMinutes = $durationSeconds !== null
            ? max(1, (int) ceil($durationSeconds / 60))
            : null;
        $estimatedPricing = $estimatedMinutes !== null
            ? RidePricing::calculate(
                $estimatedMinutes,
                is_array($this->pricing_breakdown) ? $this->pricing_breakdown : null,
            )
            : null;
        $estimatedTotal = $estimatedPricing['total_amount'] ?? null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'bike_id' => $this->bike_id,
            'start_station_id' => $this->start_station_id,
            'end_station_id' => $this->end_station_id,
            'imei' => $this->imei,
            'status' => $statusValue,
            'is_active' => $isActiveRide,
            'live_ride_label' => $isActiveRide ? config('app.name', 'BISKLET') : null,
            'started_at' => optional($this->started_at)->toIso8601String(),
            'ended_at' => optional($this->ended_at)->toIso8601String(),
            'duration_seconds' => $durationSeconds,
            'estimated_minutes' => $estimatedMinutes,
            'distance_m' => $this->distance_m,
            'unlock_fee' => $this->unlock_fee,
            'per_minute_rate' => $this->per_minute_rate,
            'total_amount' => $this->total_amount,
            'estimated_total_amount' => $estimatedTotal,
            'currency' => $this->currency,
            'minutes' => $this->minutes,
            'price_total' => $this->price_total,
            'billing_status' => $this->billing_status,
            'start_latitude' => $this->start_latitude,
            'start_longitude' => $this->start_longitude,
            'end_latitude' => $this->end_latitude,
            'end_longitude' => $this->end_longitude,
            'pricing_breakdown' => $isActiveRide && $estimatedPricing !== null
                ? $estimatedPricing
                : $this->pricing_breakdown,
            'telemetry' => $this->telemetry,
            'metadata' => $this->metadata,
            'user_location' => is_array($this->telemetry)
                ? ($this->telemetry['user_location'] ?? null)
                : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'bike' => new BikeResource($this->whenLoaded('bike')),
            'start_station' => new StationResource($this->whenLoaded('startStation')),
            'end_station' => new StationResource($this->whenLoaded('endStation')),
            'lock_code' => $this->whenLoaded('device', fn () => $this->device?->lock_code),
            'unlocked_by' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => trim(($this->user?->first_name ?? '').' '.($this->user?->last_name ?? '')),
                    'national_id' => $this->user?->national_id,
                    'phone' => $this->user?->phone,
                ];
            }),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
