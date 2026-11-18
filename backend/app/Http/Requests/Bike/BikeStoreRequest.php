<?php

namespace App\Http\Requests\Bike;

use Illuminate\Foundation\Http\FormRequest;

class BikeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'station_id' => ['nullable', 'exists:stations,id'],
            'code' => ['required', 'string', 'max:50', 'unique:bikes,code'],
            'imei' => ['nullable', 'string', 'max:20', 'unique:bikes,imei', 'exists:devices,imei'],
            'model' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'battery_level' => ['nullable', 'numeric', 'between:0,100'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
            'price_per_minute' => ['nullable', 'numeric', 'min:0'],
            'unlock_fee' => ['nullable', 'numeric', 'min:0'],
            'hardware' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'last_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'last_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'location_updated_at' => ['nullable', 'date'],
        ];
    }
}
