<?php

namespace App\Http\Requests\Station;

use Illuminate\Foundation\Http\FormRequest;

class StationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:stations,code'],
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
