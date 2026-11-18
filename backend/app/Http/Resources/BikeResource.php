<?php

namespace App\Http\Resources;

use App\Services\Rides\RidePricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin \App\Models\Bike */
class BikeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status?->value ?? $this->status;
        $activeRide = $this->whenLoaded('activeRide');
        $hasActiveRide = $activeRide !== null && ! $activeRide instanceof \Illuminate\Http\Resources\MissingValue;
        $visibleStatus = $hasActiveRide ? 'in_ride' : $status;
        $pricing = RidePricing::snapshot();

        return [
            'id' => $this->id,
            'station_id' => $this->station_id,
            'code' => $this->code,
            'imei' => $this->imei,
            'lock_code' => $this->device?->lock_code,
            'model' => $this->model,
            'type' => $this->type,
            'status' => $visibleStatus,
            'is_available' => $this->is_active && ! $hasActiveRide && $status === 'available',
            'is_active' => $this->is_active,
            'battery_level' => $this->battery_level,
            'odometer_km' => $this->odometer_km,
            'price_per_minute' => $pricing['per_minute_rate'],
            'unlock_fee' => $pricing['unlock_fee'],
            'price_birr' => $pricing['block_price'],
            'price_minutes' => $pricing['block_minutes'],
            'billing_model' => $pricing['billing_model'],
            'last_service_at' => optional($this->last_service_at)->toIso8601String(),
            'hardware' => $this->hardware,
            'metadata' => $this->metadata,
            'telemetry' => Arr::get($this->metadata, 'telemetry'),
            'current_location' => [
                'latitude' => $this->last_latitude,
                'longitude' => $this->last_longitude,
                'accuracy_m' => $this->location_accuracy,
                'updated_at' => optional($this->location_updated_at)->toIso8601String(),
            ],
            'distance_m' => $this->when(
                array_key_exists('distance_m', $this->resource->getAttributes()),
                $this->distance_m
            ),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'station' => new StationResource($this->whenLoaded('station')),
        ];
    }
}
