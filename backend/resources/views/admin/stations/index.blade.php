@extends('layouts.admin')

@section('title', 'Stations')

@section('actions')
    <a href="{{ route('admin.stations.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
        </svg>
        <span>New station</span>
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or code" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status" name="status" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All statuses</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-3 flex justify-end gap-3">
            <a href="{{ route('admin.stations.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Reset</a>
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3">Station</th>
                    <th class="px-6 py-3">Capacity</th>
                    <th class="px-6 py-3">Available docks</th>
                    <th class="px-6 py-3">Bikes</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Updated</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($stations as $station)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.stations.show', $station) }}" class="font-semibold text-slate-900 hover:text-slate-700">{{ $station->name }}</a>
                            <p class="text-xs text-slate-500">Code: {{ $station->code }}</p>
                        </td>
                        <td class="px-6 py-4">{{ number_format($station->capacity) }}</td>
                        <td class="px-6 py-4">{{ number_format($station->available_docks) }}</td>
                        <td class="px-6 py-4">{{ number_format($station->bikes_count) }}</td>
                        <td class="px-6 py-4">
                            @php
                                $statusValue = strtolower($station->status ?? 'unknown');
                                $statusClass = $statusValue === 'active' ? 'success' : 'danger';
                            @endphp
                            <span class="status-pill {{ $statusClass }}">{{ ucfirst($statusValue) }}</span>
                        </td>
                        <td class="px-6 py-4 text-right text-xs text-slate-500">{{ optional($station->updated_at)->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No stations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $stations->withQueryString()->links() }}
    </div>
@endsection
