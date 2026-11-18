<?php

namespace App\Http\Requests\Telemetry;

use Illuminate\Foundation\Http\FormRequest;

class TelemetryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'bearing' => ['nullable', 'numeric', 'between:0,360'],
            'speed_kph' => ['nullable', 'numeric', 'min:0'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'battery_percent' => ['nullable', 'numeric', 'between:0,100'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
