@extends('layouts.admin')

@section('title', 'Rides')
@section('subtitle', 'Follow live trips, investigate incidents, and audit completed rides.')

@section('actions')
    <a href="{{ route('admin.rides.index', ['status' => 'active']) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
        </svg>
        <span>Active rides</span>
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status" name="status" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All statuses</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected(($filters['status'] ?? '') === $statusOption->value)>{{ ucfirst($statusOption->value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="user" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Rider</label>
            <input type="text" id="user" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="Name, email, phone" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div>
            <label for="bike_code" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Bike code</label>
            <input type="text" id="bike_code" name="bike_code" value="{{ $filters['bike_code'] ?? '' }}" placeholder="Bike code" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div class="md:col-span-1 flex items-end justify-end gap-3">
            <a href="{{ route('admin.rides.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Reset</a>
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3">Ride</th>
                    <th class="px-6 py-3">Rider</th>
                    <th class="px-6 py-3">Bike</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Amount</th>
                    <th class="px-6 py-3 text-right">Started</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($rides as $ride)
                    <tr class="hover:bg-slate-50">
                        @php
                            $statusValue = strtolower($ride->status?->value ?? $ride->status);
                            $statusClass = match ($statusValue) {
                                'completed' => 'success',
                                'active' => 'success',
                                'pending' => 'warning',
                                'failed', 'cancelled' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.rides.show', $ride) }}" class="font-semibold text-slate-900 hover:text-slate-700">Ride #{{ $ride->id }}</a>
                            <p class="text-xs text-slate-500">{{ optional($ride->created_at)->diffForHumans() }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p>{{ $ride->user?->first_name }} {{ $ride->user?->last_name }}</p>
                            <p class="text-xs text-slate-500">{{ $ride->user?->email ?? $ride->user?->phone ?? '-' }}</p>
                        </td>
                        <td class="px-6 py-4">{{ $ride->bike?->code ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="status-pill {{ $statusClass }}">{{ strtoupper($statusValue) }}</span>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold text-slate-900">{{ $ride->total_amount ? 'ETB ' . number_format($ride->total_amount, 2) : '-' }}</td>
                        <td class="px-6 py-4 text-right text-xs text-slate-500">{{ optional($ride->started_at)->format('M d, Y H:i') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No rides match your filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rides->withQueryString()->links() }}
    </div>
@endsection
