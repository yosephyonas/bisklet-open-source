<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    private const STATUSES = ['pending', 'completed', 'failed', 'refunded', 'cancelled'];

    public function index(Request $request): View
    {
        $query = Payment::query()->with([
            'user:id,first_name,last_name,email',
            'ride:id,user_id,bike_id,status',
            'ride.bike:id,code',
        ]);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $provider = trim((string) $request->input('provider', ''));
        if ($provider !== '') {
            $query->where('provider', 'like', "%{$provider}%");
        }

        if ($request->filled('user')) {
            $search = trim((string) $request->input('user'));
            if ($search !== '') {
                $query->whereHas('user', function (Builder $builder) use ($search) {
                    $builder->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('ride')) {
            $query->where('ride_id', $request->input('ride'));
        }

        $payments = $query->latest('created_at')->paginate(20)->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'statuses' => self::STATUSES,
            'filters' => [
                'status' => $status,
                'provider' => $provider,
                'user' => $request->input('user'),
                'ride' => $request->input('ride'),
            ],
        ]);
    }

    public function show(Payment $payment): View
    {
        $payment->load([
            'user:id,first_name,last_name,email,phone',
            'ride:id,user_id,bike_id,total_amount,status',
            'ride.bike:id,code',
        ]);

        return view('admin.payments.show', [
            'payment' => $payment,
            'statuses' => self::STATUSES,
        ]);
    }

    public function edit(Payment $payment): View
    {
        return view('admin.payments.edit', [
            'payment' => $payment,
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'max:255'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:3'],
            'status' => ['required', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'json'],
            'metadata' => ['nullable', 'json'],
        ]);

        $payment->fill([
            'provider' => $validated['provider'] ?? null,
            'provider_reference' => $validated['provider_reference'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'status' => $validated['status'],
            'paid_at' => $validated['paid_at'] ?? null,
            'payload' => isset($validated['payload']) ? json_decode($validated['payload'], true) : $payment->payload,
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : $payment->metadata,
        ]);
        $payment->save();

        return redirect()
            ->route('admin.payments.show', $payment)
            ->with('success', 'Payment updated successfully.');
    }
}
