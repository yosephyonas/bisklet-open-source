<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DamageReport */
class DamageReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bike_id' => $this->bike_id,
            'ride_id' => $this->ride_id,
            'user_id' => $this->user_id,
            'maintenance_log_id' => $this->maintenance_log_id,
            'category' => $this->category,
            'severity' => $this->severity,
            'description' => $this->description,
            'affects_safety' => $this->affects_safety,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'photo_urls' => $this->photo_urls,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'resolved_at' => optional($this->resolved_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
