<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserNotification */
class UserNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'ride_id' => $this->ride_id,
            'imei' => $this->imei,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'read_at' => optional($this->read_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
