@extends('layouts.admin')

@section('title', 'Bikes')

@section('actions')
    <a href="{{ route('admin.bikes.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
        </svg>
        <span>New bike</span>
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Code, model, type" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status" name="status" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst(str_replace('_', ' ', $status->value)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="station_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Station</label>
            <select id="station_id" name="station_id" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All stations</option>
                @foreach ($stations as $station)
                    <option value="{{ $station->id }}" @selected(($filters['station_id'] ?? '') == $station->id)>{{ $station->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label for="battery" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Battery</label>
            <select id="battery" name="battery" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All levels</option>
                <option value="low" @selected(($filters['battery'] ?? '') === 'low')>Low (&lt;= 20%)</option>
                <option value="healthy" @selected(($filters['battery'] ?? '') === 'healthy')>Healthy (&gt; 20%)</option>
                <option value="unknown" @selected(($filters['battery'] ?? '') === 'unknown')>Unknown</option>
            </select>
        </div>
        <div class="sm:col-span-4 flex justify-end gap-3">
            <a href="{{ route('admin.bikes.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Reset</a>
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3">Bike</th>
                    <th class="px-6 py-3">Station</th>
                    <th class="px-6 py-3">Lock</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Battery</th>
                    <th class="px-6 py-3">Price</th>
                    <th class="px-6 py-3 text-right">Last service</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($bikes as $bike)
                    <tr class="hover:bg-slate-50">
                        @php
                            $statusValue = strtolower($bike->status?->value ?? $bike->status);
                            $statusClass = match ($statusValue) {
                                'available' => 'success',
                                'reserved', 'in_ride' => 'warning',
                                'maintenance', 'offline' => 'danger',
                                default => 'neutral',
                            };
                            $batteryLevel = $bike->battery_level;
                            $batteryClass = $batteryLevel === null
                                ? 'neutral'
                                : ($batteryLevel <= 20 ? 'danger' : ($batteryLevel <= 40 ? 'warning' : 'success'));
                        @endphp
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.bikes.show', $bike) }}" class="font-semibold text-slate-900 hover:text-slate-700">{{ $bike->code }}</a>
                            <p class="text-xs text-slate-500">{{ $bike->model ?? ucfirst($bike->type) }}</p>
                        </td>
                        <td class="px-6 py-4">{{ $bike->station?->name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if ($bike->imei)
                                <p class="text-xs text-slate-900">IMEI {{ $bike->imei }}</p>
                                <p class="text-xs text-slate-500">Code {{ $bike->device?->lock_code ?? '-' }}</p>
                            @else
                                <span class="text-xs text-slate-400">Unlinked</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="status-pill {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $statusValue)) }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="status-pill {{ $batteryClass }}">{{ $batteryLevel !== null ? $batteryLevel . '%' : 'Unknown' }}</span>
                        </td>
                        <td class="px-6 py-4">ETB {{ number_format(config('pricing.rides.price_birr', 10), 2) }} / {{ config('pricing.rides.minutes', 20) }} min</td>
                        <td class="px-6 py-4 text-right text-xs text-slate-500">{{ optional($bike->last_service_at)->format('M d, Y') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500">No bikes found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $bikes->withQueryString()->links() }}
    </div>
@endsection
