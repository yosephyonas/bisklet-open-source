@extends('layouts.admin')

@section('title', 'Payments')
@section('subtitle', 'Monitor revenue and reconcile inflight transactions.')

@section('actions')
    <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h18v4H3z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 11h18v10H3z" />
        </svg>
        <span>Pending queue</span>
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
        @if ($filters['ride'] ?? false)
            <input type="hidden" name="ride" value="{{ $filters['ride'] }}">
        @endif
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status" name="status" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All statuses</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="provider" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Provider</label>
            <input type="text" id="provider" name="provider" value="{{ $filters['provider'] ?? '' }}" placeholder="Telebirr" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div class="md:col-span-2">
            <label for="user" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Rider</label>
            <input type="text" id="user" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="Name, email, phone" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div class="md:col-span-4 flex items-center justify-end gap-3">
            <a href="{{ route('admin.payments.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Reset</a>
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filter</button>
        </div>
    </form>

    @if ($filters['ride'] ?? false)
        <div class="mb-6 flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <span>Showing payments for ride #{{ $filters['ride'] }}.</span>
            <a href="{{ route('admin.payments.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200/40">Clear ride filter</a>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3">Payment</th>
                    <th class="px-6 py-3">Rider</th>
                    <th class="px-6 py-3">Ride</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Amount</th>
                    <th class="px-6 py-3 text-right">Paid at</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($payments as $payment)
                    <tr class="hover:bg-slate-50">
                        @php
                            $statusValue = strtolower($payment->status ?? 'unknown');
                            $statusClass = match ($statusValue) {
                                'successful', 'success', 'paid', 'completed' => 'success',
                                'pending', 'processing' => 'warning',
                                'failed', 'cancelled' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="font-semibold text-slate-900 hover:text-slate-700">#{{ $payment->id }}</a>
                            <p class="text-xs text-slate-500">{{ strtoupper($payment->provider ?? 'MANUAL') }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p>{{ $payment->user?->first_name }} {{ $payment->user?->last_name }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->user?->email ?? $payment->user?->phone ?? '-' }}</p>
                        </td>
                        <td class="px-6 py-4">
                            @if ($payment->ride)
                                <a href="{{ route('admin.rides.show', $payment->ride) }}" class="text-sm font-medium text-slate-700 hover:text-slate-900">Ride #{{ $payment->ride->id }}</a>
                                <p class="text-xs text-slate-500">Bike {{ $payment->ride->bike?->code ?? '-' }}</p>
                            @else
                                <span class="text-xs text-slate-500">Orphaned</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="status-pill {{ $statusClass }}">{{ strtoupper($statusValue) }}</span>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold text-slate-900">ETB {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-4 text-right text-xs text-slate-500">{{ optional($payment->paid_at ?? $payment->created_at)->format('M d, Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No payments match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $payments->withQueryString()->links() }}
    </div>
@endsection
