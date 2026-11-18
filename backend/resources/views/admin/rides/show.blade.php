@extends('layouts.admin')

@section('title', 'Ride #' . $ride->id)
@section('subtitle', 'Trip timeline, rider details, and payment status for this ride.')

@section('actions')
    <a href="{{ route('admin.rides.edit', $ride) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
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
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ strtoupper($ride->status?->value ?? $ride->status) }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Started</dt><dd>{{ optional($ride->started_at)->format('M d, Y H:i') ?? 'Unknown' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Ended</dt><dd>{{ optional($ride->ended_at)->format('M d, Y H:i') ?? 'In progress' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Duration</dt><dd>{{ $ride->duration_seconds ? gmdate('H:i:s', $ride->duration_seconds) : '-' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rider</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $ride->user?->first_name }} {{ $ride->user?->last_name }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Email</dt><dd>{{ $ride->user?->email ?? '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Phone</dt><dd>{{ $ride->user?->phone ?? '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>National ID</dt><dd>{{ $ride->user?->national_id ?? '-' }}</dd></div>
            </dl>
            @if ($ride->user)
                <a href="{{ route('admin.users.show', $ride->user) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900">
                    <span>Open rider profile</span>
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bike</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $ride->bike?->code ?? 'Unknown' }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Start station</dt><dd>{{ $ride->startStation?->name ?? '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>End station</dt><dd>{{ $ride->endStation?->name ?? '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Distance</dt><dd>{{ $ride->distance_m ? number_format($ride->distance_m / 1000, 2) . ' km' : '-' }}</dd></div>
            </dl>
            @if ($ride->bike)
                <a href="{{ route('admin.bikes.show', $ride->bike) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900">
                    <span>Open bike record</span>
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endif
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Billing</p>
            <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                <div class="flex items-center justify-between"><dt>Unlock fee</dt><dd>{{ $ride->unlock_fee ? 'ETB ' . number_format($ride->unlock_fee, 2) : '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Per minute rate</dt><dd>{{ $ride->per_minute_rate ? 'ETB ' . number_format($ride->per_minute_rate, 2) : '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Total amount</dt><dd class="font-semibold text-slate-900">{{ $ride->total_amount ? 'ETB ' . number_format($ride->total_amount, 2) : '-' }}</dd></div>
            </dl>
            @if ($ride->pricing_breakdown)
                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pricing breakdown</p>
                    <pre class="mt-2 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($ride->pricing_breakdown, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Payments</p>
                <a href="{{ route('admin.payments.index', ['ride' => $ride->id]) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900">View payments list</a>
            </div>
            <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($ride->payments as $payment)
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <a href="{{ route('admin.payments.show', $payment) }}" class="font-semibold text-slate-900 hover:text-slate-700">Payment #{{ $payment->id }}</a>
                            <p class="text-xs text-slate-500">{{ strtoupper($payment->provider ?? 'MANUAL') }} - {{ strtoupper($payment->status) }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <p>{{ optional($payment->paid_at ?? $payment->created_at)->format('M d, H:i') }}</p>
                            <p class="font-semibold text-slate-900">ETB {{ number_format($payment->amount, 2) }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">No payments recorded.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($ride->telemetry)
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Telemetry snapshot</p>
                <span class="text-xs text-slate-500">JSON</span>
            </div>
            <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($ride->telemetry, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    @if ($ride->metadata)
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Metadata</p>
                <span class="text-xs text-slate-500">JSON</span>
            </div>
            <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($ride->metadata, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
@endsection
