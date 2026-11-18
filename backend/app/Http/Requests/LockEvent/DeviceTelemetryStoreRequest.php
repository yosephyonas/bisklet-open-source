<?php

namespace App\Http\Requests\LockEvent;

use Illuminate\Foundation\Http\FormRequest;

class DeviceTelemetryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'imei' => ['required', 'string', 'min:10', 'max:32'],
            'type' => ['required', 'string', 'max:10'],
            'raw_payload' => ['nullable', 'string'],
            'parsed_json' => ['nullable', 'array'],
            'last_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'last_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'battery_voltage' => ['nullable', 'numeric', 'min:0'],
            'lock_state' => ['nullable', 'string', 'max:50'],
            'firmware_version' => ['nullable', 'string', 'max:100'],
        ];
    }
}
