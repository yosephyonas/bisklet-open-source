@extends('layouts.admin')

@section('title', 'Station - ' . $station->name)
@section('subtitle', 'Code ' . $station->code . ' - Capacity ' . $station->capacity)

@section('actions')
    <a href="{{ route('admin.stations.edit', $station) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4l10-10a2.828 2.828 0 10-4-4L4 16v4z" />
        </svg>
        <span>Edit</span>
    </a>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">Overview</p>
                <dl class="mt-4 grid gap-4 text-sm text-slate-600 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Status</dt>
                        <dd class="mt-1 inline-flex rounded-lg bg-slate-900/10 px-3 py-1 text-xs font-semibold text-slate-900">{{ ucfirst($station->status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Available docks</dt>
                        <dd class="mt-1">{{ number_format($station->available_docks) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Latitude</dt>
                        <dd class="mt-1">{{ $station->latitude ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Longitude</dt>
                        <dd class="mt-1">{{ $station->longitude ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Description</dt>
                        <dd class="mt-1">{{ $station->description ?? 'No description provided.' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-900">Assigned bikes</p>
                    <a href="{{ route('admin.bikes.index', ['station_id' => $station->id]) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900">Manage</a>
                </div>
                <div class="mt-4 -mx-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Bike</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Battery</th>
                                <th class="px-6 py-3">Last service</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600">
                            @forelse ($station->bikes as $bike)
                                <tr>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('admin.bikes.show', $bike) }}" class="font-semibold text-slate-900 hover:text-slate-700">{{ $bike->code }}</a>
                                        <p class="text-xs text-slate-500">{{ $bike->model ?? ucfirst($bike->type) }}</p>
                                    </td>
                                    <td class="px-6 py-4">{{ ucfirst($bike->status->value ?? $bike->status) }}</td>
                                    <td class="px-6 py-4">{{ $bike->battery_level !== null ? $bike->battery_level . '%' : '-' }}</td>
                                    <td class="px-6 py-4 text-xs text-slate-500">{{ optional($bike->last_service_at)->format('M d, Y') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-6 text-center text-sm text-slate-500">No bikes assigned.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">Metadata</p>
                @if ($station->metadata)
                    <pre class="mt-4 rounded-xl bg-slate-950/90 px-4 py-3 text-xs text-slate-100">{{ json_encode($station->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <p class="mt-4 text-xs text-slate-500">No metadata recorded.</p>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">Timestamps</p>
                <dl class="mt-4 space-y-3 text-xs text-slate-600">
                    <div class="flex items-center justify-between">
                        <dt>Created</dt>
                        <dd>{{ optional($station->created_at)->format('M d, Y H:i') }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt>Updated</dt>
                        <dd>{{ optional($station->updated_at)->diffForHumans() }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
@endsection
