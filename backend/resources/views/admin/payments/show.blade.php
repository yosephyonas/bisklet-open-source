@extends('layouts.admin')

@section('title', 'Payment #' . $payment->id)
@section('subtitle', 'Full payment record including provider details and linked ride.')

@section('actions')
    <a href="{{ route('admin.payments.edit', $payment) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 013.182 3.182l-9.546 9.546a4.5 4.5 0 01-1.897 1.13l-3.141.898.898-3.141a4.5 4.5 0 011.13-1.897z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.875 4.5" />
        </svg>
        <span>Edit</span>
    </a>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">ETB {{ number_format($payment->amount, 2) }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Status</dt><dd class="font-semibold text-slate-900">{{ strtoupper($payment->status) }}</dd></div>
                <div class="flex items-center justify-between"><dt>Currency</dt><dd>{{ strtoupper($payment->currency) }}</dd></div>
                <div class="flex items-center justify-between"><dt>Paid at</dt><dd>{{ optional($payment->paid_at ?? $payment->created_at)->format('M d, Y H:i') }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Provider</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ strtoupper($payment->provider ?? 'MANUAL') }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Reference</dt><dd>{{ $payment->provider_reference ?? '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Recorded</dt><dd>{{ optional($payment->created_at)->format('M d, Y H:i') }}</dd></div>
                <div class="flex items-center justify-between"><dt>Updated</dt><dd>{{ optional($payment->updated_at)->format('M d, Y H:i') }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Linked records</p>
            <div class="mt-4 space-y-3 text-sm">
                @if ($payment->user)
                    <a href="{{ route('admin.users.show', $payment->user) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-slate-600 hover:border-slate-300 hover:text-slate-900">
                        <span>{{ $payment->user->first_name }} {{ $payment->user->last_name }}</span>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif
                @if ($payment->ride)
                    <a href="{{ route('admin.rides.show', $payment->ride) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-slate-600 hover:border-slate-300 hover:text-slate-900">
                        <span>Ride #{{ $payment->ride->id }}</span>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if ($payment->payload)
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Provider payload</p>
                <span class="text-xs text-slate-500">JSON</span>
            </div>
            <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($payment->payload, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    @if ($payment->metadata)
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Metadata</p>
                <span class="text-xs text-slate-500">JSON</span>
            </div>
            <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
@endsection
