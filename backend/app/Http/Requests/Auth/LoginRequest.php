<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize incoming fields so the controller can always use 'identifier'.
     * Accepts:
     *  - identifier (preferred)
     *  - email
     *  - phone
     *  - national_id
     */
    protected function prepareForValidation(): void
    {
        $identifier = $this->input('identifier');

        if (empty($identifier)) {
            if ($this->filled('email')) {
                $identifier = $this->input('email');
            } elseif ($this->filled('phone')) {
                $identifier = $this->input('phone');
            } elseif ($this->filled('national_id')) {
                $identifier = $this->input('national_id');
            }
        }

        if ($identifier !== null) {
            $this->merge([
                'identifier' => is_string($identifier) ? trim($identifier) : $identifier,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // We validate identifier as a required string. Backend lookup will attempt
            // to match it against national_id, email or phone as appropriate.
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Accessors used by controllers/services.
     */
    public function identifier(): string
    {
        return (string) $this->input('identifier');
    }

    public function email(): ?string
    {
        // Return email if input was specifically email or looks like an email.
        if ($this->filled('email')) {
            return (string) $this->input('email');
        }

        $identifier = $this->identifier();
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : null;
    }

    public function password(): string
    {
        return (string) $this->input('password');
    }

    public function remember(): bool
    {
        return (bool) $this->boolean('remember');
    }
}
