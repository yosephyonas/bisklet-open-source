@extends('layouts.admin')

@section('title', 'Search')
@section('subtitle', 'Find users, bikes, stations, rides, payments, and FYDA checks fast.')

@section('content')
    <form method="GET" action="{{ route('admin.search') }}"
        class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
        <div class="md:col-span-3">
            <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search query</label>
            <input type="text" id="q" name="q" value="{{ $query }}"
                placeholder="Name, ID, bike code, payment reference, station name"
                class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div class="md:col-span-1 flex items-end justify-end gap-3">
            <a href="{{ route('admin.search') }}"
                class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Reset</a>
            <button type="submit"
                class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Search</button>
        </div>
    </form>

    @php
        $totalMatches = $usersCount + $bikesCount + $stationsCount + $ridesCount + $paymentsCount + $maintenanceCount + $fydaCount;
    @endphp

    @if ($query === '')
        <div class="card">
            <p class="text-sm font-semibold text-slate-900">Start with a keyword</p>
            <p class="mt-2 text-xs text-slate-500">Search by name, phone number, bike code, payment reference, or
                station ID.</p>
            <div class="mt-4 action-grid">
                <a href="{{ route('admin.users.index') }}" class="action-tile">Browse users</a>
                <a href="{{ route('admin.bikes.index') }}" class="action-tile">Browse bikes</a>
                <a href="{{ route('admin.stations.index') }}" class="action-tile">Browse stations</a>
                <a href="{{ route('admin.rides.index') }}" class="action-tile">Browse rides</a>
                <a href="{{ route('admin.payments.index') }}" class="action-tile">Browse payments</a>
                <a href="{{ route('admin.maintenance.index') }}" class="action-tile">Browse maintenance</a>
            </div>
        </div>
    @else
        <div class="mb-6 bg-layer">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Search results for "{{ $query }}"</p>
                    <p class="text-xs text-slate-500">Showing top matches across the admin console.</p>
                </div>
                <div class="badge-grid">
                    <span class="badge-soft-blue">Users: {{ number_format($usersCount) }}</span>
                    <span class="badge-soft-blue">Bikes: {{ number_format($bikesCount) }}</span>
                    <span class="badge-soft-blue">Stations: {{ number_format($stationsCount) }}</span>
                    <span class="badge-soft-blue">Rides: {{ number_format($ridesCount) }}</span>
                    <span class="badge-soft-blue">Payments: {{ number_format($paymentsCount) }}</span>
                    <span class="badge-soft-blue">Maintenance: {{ number_format($maintenanceCount) }}</span>
                    <span class="badge-soft-blue">FYDA: {{ number_format($fydaCount) }}</span>
                </div>
            </div>
        </div>

        @if ($totalMatches === 0)
            <div class="card">
                <p class="text-sm font-semibold text-slate-900">No matches yet</p>
                <p class="mt-2 text-xs text-slate-500">Try another keyword or jump into a module to browse records.</p>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">Users</p>
                        <a href="{{ route('admin.users.index', ['q' => $query]) }}"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                        @forelse ($users as $user)
                            <div class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <a href="{{ route('admin.users.show', $user) }}"
                                        class="font-semibold text-slate-900 hover:text-slate-700">{{ $user->first_name }}
                                        {{ $user->last_name }}</a>
                                    <p class="text-xs text-slate-500">{{ $user->email ?? $user->phone ?? '-' }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="font-semibold text-slate-900">{{ ucfirst($user->role?->value ?? $user->role) }}</p>
                                    <p>ID {{ $user->national_id }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-500">No user matches.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">Bikes</p>
                        <a href="{{ route('admin.bikes.index', ['q' => $query]) }}"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                        @forelse ($bikes as $bike)
                            <div class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <a href="{{ route('admin.bikes.show', $bike) }}"
                                        class="font-semibold text-slate-900 hover:text-slate-700">{{ $bike->code }}</a>
                                    <p class="text-xs text-slate-500">{{ $bike->model ?? ucfirst($bike->type) }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="font-semibold text-slate-900">{{ $bike->station?->name ?? 'Unassigned' }}</p>
                                    <p>{{ ucfirst(str_replace('_', ' ', $bike->status?->value ?? $bike->status)) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-500">No bike matches.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">Stations</p>
                        <a href="{{ route('admin.stations.index', ['q' => $query]) }}"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                        @forelse ($stations as $station)
                            <div class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <a href="{{ route('admin.stations.show', $station) }}"
                                        class="font-semibold text-slate-900 hover:text-slate-700">{{ $station->name }}</a>
                                    <p class="text-xs text-slate-500">Code {{ $station->code }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="font-semibold text-slate-900">{{ ucfirst($station->status) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-500">No station matches.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">Rides</p>
                        <a href="{{ route('admin.rides.index') }}"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                        @forelse ($rides as $ride)
                            <div class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <a href="{{ route('admin.rides.show', $ride) }}"
                                        class="font-semibold text-slate-900 hover:text-slate-700">Ride #{{ $ride->id }}</a>
                                    <p class="text-xs text-slate-500">{{ $ride->user?->first_name }} {{ $ride->user?->last_name }} -
                                        {{ $ride->bike?->code ?? '-' }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="font-semibold text-slate-900">{{ strtoupper($ride->status?->value ?? $ride->status) }}</p>
                                    <p>{{ optional($ride->created_at)->diffForHumans() }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-500">No ride matches.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">Payments</p>
                        <a href="{{ route('admin.payments.index') }}"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                        @forelse ($payments as $payment)
                            <div class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <a href="{{ route('admin.payments.show', $payment) }}"
                                        class="font-semibold text-slate-900 hover:text-slate-700">Payment #{{ $payment->id }}</a>
                                    <p class="text-xs text-slate-500">{{ strtoupper($payment->provider ?? 'MANUAL') }} -
                                        {{ strtoupper($payment->status) }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="font-semibold text-slate-900">ETB {{ number_format($payment->amount, 2) }}</p>
                                    <p>{{ optional($payment->paid_at ?? $payment->created_at)->format('M d, H:i') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-500">No payment matches.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">Maintenance logs</p>
                        <a href="{{ route('admin.maintenance.index') }}"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                        @forelse ($maintenanceLogs as $log)
                            <div class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <a href="{{ route('admin.maintenance.show', $log) }}"
                                        class="font-semibold text-slate-900 hover:text-slate-700">{{ $log->type }}</a>
                                    <p class="text-xs text-slate-500">Bike {{ $log->bike?->code ?? '-' }} ·
                                        {{ $log->user?->first_name }} {{ $log->user?->last_name }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="font-semibold text-slate-900">{{ ucfirst(str_replace('_', ' ', $log->status)) }}</p>
                                    <p>{{ optional($log->performed_at ?? $log->created_at)->format('M d, Y') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-500">No maintenance matches.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">FYDA verifications</p>
                        <a href="{{ route('admin.fyda-verifications.index') }}"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                        @forelse ($fydaVerifications as $verification)
                            <div class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <a href="{{ route('admin.fyda-verifications.show', $verification) }}"
                                        class="font-semibold text-slate-900 hover:text-slate-700">Verification #{{ $verification->id }}</a>
                                    <p class="text-xs text-slate-500">{{ $verification->user?->first_name }}
                                        {{ $verification->user?->last_name }} · {{ $verification->national_id }}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <p class="font-semibold text-slate-900">{{ ucfirst($verification->status) }}</p>
                                    <p>{{ optional($verification->created_at)->format('M d, Y') }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-sm text-slate-500">No FYDA matches.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    @endif
@endsection
