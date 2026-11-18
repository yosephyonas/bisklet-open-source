<?php

namespace App\Http\Requests\Navigation;

use Illuminate\Foundation\Http\FormRequest;

class RoutePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origin_latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination_latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['required', 'numeric', 'between:-180,180'],
            'avoid_tolls' => ['sometimes', 'boolean'],
            'avoid_ferries' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'string', 'max:10'],
        ];
    }
}
