<?php

namespace App\Http\Requests\Ride;

use Illuminate\Foundation\Http\FormRequest;

class RideCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_station_id' => ['nullable', 'exists:stations,id'],
            'end_latitude' => ['nullable', 'numeric'],
            'end_longitude' => ['nullable', 'numeric'],
            'distance_m' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
