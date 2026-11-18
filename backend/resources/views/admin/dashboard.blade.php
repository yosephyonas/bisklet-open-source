@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <div class="card">
            <p class="stat-label">Users</p>
            <p class="stat-value">{{ number_format($totals['users']['all']) }}</p>
            <dl class="mt-3 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between">
                    <dt>FYDA verified</dt>
                    <dd class="font-medium text-emerald-600">{{ number_format($totals['users']['verified']) }}</dd>
                </div>
            </dl>
        </div>
        <div class="card">
            <p class="stat-label">Bikes</p>
            <p class="stat-value">{{ number_format($totals['bikes']['all']) }}</p>
            <dl class="mt-3 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between">
                    <dt>Available</dt>
                    <dd class="font-medium text-emerald-600">{{ number_format($totals['bikes']['available']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt>In ride</dt>
                    <dd>{{ number_format($totals['bikes']['inRide']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt>Maintenance</dt>
                    <dd class="text-rose-600">{{ number_format($totals['bikes']['maintenance']) }}</dd>
                </div>
            </dl>
        </div>
        <div class="card">
            <p class="stat-label">Fleet activity</p>
            <p class="stat-value">
                {{ number_format($totals['rides']['active']) }}
                <span class="status-pill">Active</span>
            </p>
            <dl class="mt-3 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between">
                    <dt>Completed today</dt>
                    <dd>{{ number_format($totals['rides']['completedToday']) }}</dd>
                </div>
            </dl>
        </div>
        <div class="card">
            <p class="stat-label">Revenue</p>
            <p class="stat-value">ETB {{ number_format($revenueSummary['total'], 2) }}</p>
            <dl class="mt-3 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between">
                    <dt>Today</dt>
                    <dd class="font-medium text-emerald-600">ETB {{ number_format($revenueSummary['today'], 2) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt>Last 30 days</dt>
                    <dd>ETB {{ number_format($revenueSummary['last30Days'], 2) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-6 card ops-attention-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-slate-900">Needs attention</p>
                <p class="text-xs text-slate-500">The shortest path to what admins should check first.</p>
            </div>
            <a href="{{ route('admin.system-health') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900">Open system health</a>
        </div>
        <div class="ops-attention-grid">
            @foreach ($attentionItems as $item)
                <a href="{{ $item['route'] }}" class="ops-attention-item {{ $item['tone'] }}">
                    <span>
                        <strong>{{ $item['label'] }}</strong>
                        <small>{{ $item['hint'] }}</small>
                    </span>
                    <b>{{ number_format($item['value']) }}</b>
                </a>
            @endforeach
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Active riders</p>
                <a href="{{ route('admin.rides.index', ['status' => 'active']) }}"
                    class="text-xs font-semibold text-slate-500 hover:text-slate-900">View rides</a>
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($activeRides as $ride)
                    <div class="flex items-center justify-between gap-4 py-4 text-sm">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $ride['rider_name'] }}</p>
                            <p class="text-xs text-slate-500">{{ $ride['bike_label'] }}
                                @if (!empty($ride['start_station_name']))
                                    · Started at {{ $ride['start_station_name'] }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <p class="font-semibold text-emerald-600">{{ $ride['status_label'] }}</p>
                            <p>{{ $ride['elapsed'] }} elapsed</p>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">No active riders right now.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Recent rides</p>
                <a href="{{ route('admin.rides.index') }}"
                    class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($recentRides as $ride)
                    <div class="flex items-center justify-between gap-4 py-4 text-sm">
                        <div>
                            <p class="font-semibold text-slate-900">Ride #{{ $ride->id }}</p>
                            <p class="text-xs text-slate-500">{{ $ride->user?->first_name }} {{ $ride->user?->last_name }}
                                - Bike {{ $ride->bike?->code ?? '-' }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <p class="font-semibold text-slate-900">
                                {{ ucfirst(is_object($ride->status) ? $ride->status->value ?? (string) $ride->status : $ride->status) }}
                            </p>
                            <p>{{ optional($ride->created_at)->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">No rides logged yet.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Recent payments</p>
                <a href="{{ route('admin.payments.index') }}"
                    class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($recentPayments as $payment)
                    <div class="flex items-center justify-between gap-4 py-4 text-sm">
                        <div>
                            <p class="font-semibold text-slate-900">ETB {{ number_format($payment->amount, 2) }}</p>
                            <p class="text-xs text-slate-500">{{ $payment->user?->first_name }}
                                {{ $payment->user?->last_name }} - {{ strtoupper($payment->status) }}</p>
                        </div>
                        <div class="w-32 text-right text-xs text-slate-500">
                            <p>{{ optional($payment->paid_at ?? $payment->created_at)->format('M d, H:i') }}</p>
                            <p>{{ strtoupper($payment->provider ?? 'Manual') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">No payments recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
