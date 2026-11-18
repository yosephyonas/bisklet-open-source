<?php

namespace App\Http\Requests\Ride;

use Illuminate\Foundation\Http\FormRequest;

class RideLocationRequest extends FormRequest
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
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'speed_mps' => ['nullable', 'numeric', 'min:0'],
            'bearing' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'captured_at' => ['nullable', 'date'],
        ];
    }
}
