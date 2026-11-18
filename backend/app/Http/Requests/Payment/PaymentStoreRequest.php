<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class PaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:100'],
            'provider_reference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }
}
