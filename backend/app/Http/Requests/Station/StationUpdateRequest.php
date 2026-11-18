<?php

namespace App\Http\Requests\Station;

use Illuminate\Foundation\Http\FormRequest;

class StationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'available_docks' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
