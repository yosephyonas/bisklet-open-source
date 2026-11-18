@extends('layouts.admin')

@section('title', 'Maintenance')
@section('subtitle', 'Track open issues and completed work across the fleet.')

@section('actions')
    <a href="{{ route('admin.maintenance.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
        </svg>
        <span>New task</span>
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status" name="status" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All statuses</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ ucfirst(str_replace('_', ' ', $statusOption)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="bike_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Bike</label>
            <select id="bike_id" name="bike_id" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All bikes</option>
                @foreach ($bikes as $bike)
                    <option value="{{ $bike->id }}" @selected(($filters['bike_id'] ?? '') == $bike->id)>{{ $bike->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2 flex items-end justify-end gap-3">
            <a href="{{ route('admin.maintenance.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Reset</a>
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3">Task</th>
                    <th class="px-6 py-3">Bike</th>
                    <th class="px-6 py-3">Technician</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3">Performed</th>
                    <th class="px-6 py-3 text-right">Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($logs as $log)
                    <tr class="hover:bg-slate-50">
                        @php
                            $statusValue = strtolower($log->status ?? 'unknown');
                            $statusClass = match ($statusValue) {
                                'completed' => 'success',
                                'pending', 'in_progress' => 'warning',
                                'cancelled' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.maintenance.show', $log) }}" class="font-semibold text-slate-900 hover:text-slate-700">{{ $log->type }}</a>
                            <p class="text-xs text-slate-500">#{{ $log->id }}</p>
                        </td>
                        <td class="px-6 py-4">{{ $log->bike?->code }}</td>
                        <td class="px-6 py-4">{{ $log->user?->first_name }} {{ $log->user?->last_name }}</td>
                        <td class="px-6 py-4">
                            <span class="status-pill {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $statusValue)) }}</span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">{{ optional($log->performed_at ?? $log->created_at)->diffForHumans() }}</td>
                        <td class="px-6 py-4 text-right">{{ $log->cost ? 'ETB ' . number_format($log->cost, 2) : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No maintenance tasks found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $logs->withQueryString()->links() }}
    </div>
@endsection
