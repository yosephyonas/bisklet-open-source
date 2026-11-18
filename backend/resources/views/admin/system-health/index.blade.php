@extends('layouts.admin')

@section('title', 'System Health')
@section('subtitle', 'A quick readiness view for FYDA, maps, payments, locks, and core services.')

@section('content')
    <div class="grid gap-4 md:grid-cols-3">
        <div class="stat-card">
            <span class="stat-label">Ready</span>
            <strong class="stat-value">{{ number_format($summary['ready']) }}</strong>
            <span class="stat-helper">Configured checks</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Missing</span>
            <strong class="stat-value">{{ number_format($summary['missing']) }}</strong>
            <span class="stat-helper">Needs setup</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Operations</span>
            <strong class="stat-value">{{ number_format(collect($operations)->sum('value')) }}</strong>
            <span class="stat-helper">Items across queues</span>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Readiness checks</p>
                    <p class="text-xs text-slate-500">No secrets are shown here, only whether each dependency is configured.</p>
                </div>
            </div>
            <div class="health-check-list">
                @foreach ($checks as $check)
                    <div class="health-check-row">
                        <span class="status-dot {{ $check['state'] }}"></span>
                        <div>
                            <p class="font-semibold text-slate-900">{{ $check['label'] }}</p>
                            <p class="text-xs text-slate-500">{{ $check['detail'] }}</p>
                        </div>
                        <span class="status-pill {{ $check['state'] === 'ready' ? 'success' : 'danger' }}">
                            {{ $check['state'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <p class="text-sm font-semibold text-slate-900">Operational queues</p>
            <p class="mt-1 text-xs text-slate-500">Direct links to the areas most likely to need action.</p>
            <div class="ops-queue-list">
                @foreach ($operations as $item)
                    <a href="{{ $item['route'] }}" class="ops-queue-item {{ $item['tone'] }}">
                        <span>{{ $item['label'] }}</span>
                        <strong>{{ number_format($item['value']) }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endsection
