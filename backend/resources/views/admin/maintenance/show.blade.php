@extends('layouts.admin')

@section('title', 'Maintenance #' . $log->id)
@section('subtitle', 'Full audit trail for this maintenance task and its current status.')

@section('actions')
    <a href="{{ route('admin.maintenance.edit', $log) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 013.182 3.182l-9.546 9.546a4.5 4.5 0 01-1.897 1.13l-3.141.898.898-3.141a4.5 4.5 0 011.13-1.897z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.875 4.5" />
        </svg>
        <span>Edit</span>
    </a>
    <form action="{{ route('admin.maintenance.destroy', $log) }}" method="POST" onsubmit="return confirm('Delete this maintenance record?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-9 3v7m6-7v7M9 4h6a1 1 0 011 1v2H8V5a1 1 0 011-1z" />
            </svg>
            <span>Delete</span>
        </button>
    </form>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ ucfirst(str_replace('_', ' ', $log->status)) }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Performed</dt><dd>{{ optional($log->performed_at)->format('M d, Y H:i') ?? 'Pending' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Created</dt><dd>{{ optional($log->created_at)->format('M d, Y H:i') }}</dd></div>
                <div class="flex items-center justify-between"><dt>Updated</dt><dd>{{ optional($log->updated_at)->format('M d, Y H:i') }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bike</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $log->bike?->code }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Type</dt><dd>{{ $log->type }}</dd></div>
                <div class="flex items-center justify-between"><dt>Technician</dt><dd>{{ $log->user?->first_name }} {{ $log->user?->last_name }}</dd></div>
                <div class="flex items-center justify-between"><dt>Cost</dt><dd>{{ $log->cost ? 'ETB ' . number_format($log->cost, 2) : 'N/A' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Links</p>
            <div class="mt-4 space-y-3 text-sm">
                <a href="{{ route('admin.bikes.show', $log->bike_id) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-slate-600 hover:border-slate-300 hover:text-slate-900">
                    <span>Go to bike</span>
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                <a href="{{ route('admin.maintenance.index', ['status' => $log->status]) }}" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-slate-600 hover:border-slate-300 hover:text-slate-900">
                    <span>See similar tasks</span>
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>
    </div>

    @if ($log->notes)
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Technician notes</p>
            <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $log->notes }}</p>
        </div>
    @endif

    @if ($log->metadata)
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Metadata</p>
                <span class="text-xs text-slate-500">JSON</span>
            </div>
            <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
@endsection
