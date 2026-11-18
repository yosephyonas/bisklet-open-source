<?php

namespace App\Http\Requests\Ride;

use Illuminate\Foundation\Http\FormRequest;

class RideStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bike_id' => ['required', 'exists:bikes,id'],
            'start_station_id' => ['nullable', 'exists:stations,id'],
            'start_latitude' => ['nullable', 'numeric'],
            'start_longitude' => ['nullable', 'numeric'],
        ];
    }
}
