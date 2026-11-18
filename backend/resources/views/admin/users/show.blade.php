@extends('layouts.admin')

@section('title', 'User - ' . trim($user->first_name . ' ' . $user->last_name))
@section('subtitle', 'FYDA ' . ($user->fyda_verified_at ? 'verified' : 'pending') . ' | Joined ' . optional($user->created_at)->format('M d, Y'))

@section('actions')
    <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
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
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-lg font-semibold text-slate-900">{{ $user->first_name }} {{ $user->last_name }}</p>
                        <p class="text-sm text-slate-500">National ID: {{ $user->national_id }}</p>
                    </div>
                    <span class="rounded-full bg-slate-900/10 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-slate-900">{{ ucfirst($user->role?->value ?? $user->role) }}</span>
                </div>

                <dl class="mt-6 grid gap-4 text-sm text-slate-600 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-1">{{ $user->email ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Phone</dt>
                        <dd class="mt-1">{{ $user->phone ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Father name</dt>
                        <dd class="mt-1">{{ $user->father_name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Date of birth</dt>
                        <dd class="mt-1">{{ optional($user->date_of_birth)->format('M d, Y') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">FYDA verified at</dt>
                        <dd class="mt-1">{{ optional($user->fyda_verified_at)->format('M d, Y H:i') ?? 'Pending' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Last login</dt>
                        <dd class="mt-1">{{ optional($user->last_login_at)->diffForHumans() ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-900">Recent rides</p>
                    <a href="{{ route('admin.rides.index', ['user' => $user->first_name]) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                </div>
                <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                    @forelse ($recentRides as $ride)
                        <div class="flex items-center justify-between py-4">
                            <div>
                                <p class="font-semibold text-slate-900">Ride #{{ $ride->id }}</p>
                                <p class="text-xs text-slate-500">Bike {{ $ride->bike?->code ?? '-' }} - {{ ucfirst($ride->status->value ?? $ride->status) }}</p>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                <p>{{ optional($ride->created_at)->format('M d, H:i') }}</p>
                                <p>{{ $ride->total_amount ? 'ETB ' . number_format($ride->total_amount, 2) : '-' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">No rides yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-900">Recent payments</p>
                    <a href="{{ route('admin.payments.index', ['user' => $user->first_name]) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900">View all</a>
                </div>
                <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                    @forelse ($recentPayments as $payment)
                        <div class="flex items-center justify-between py-4">
                            <div>
                                <p class="font-semibold text-slate-900">ETB {{ number_format($payment->amount, 2) }}</p>
                                <p class="text-xs text-slate-500">{{ strtoupper($payment->status) }} - {{ strtoupper($payment->provider ?? 'Manual') }}</p>
                            </div>
                            <div class="text-right text-xs text-slate-500">
                                <p>{{ optional($payment->paid_at ?? $payment->created_at)->format('M d, H:i') }}</p>
                                <p>Ride #{{ $payment->ride_id }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">No payments yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">Usage summary</p>
                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                    <div class="flex items-center justify-between">
                        <dt>Rides taken</dt>
                        <dd class="font-semibold text-slate-900">{{ number_format($user->rides_count) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt>Payments made</dt>
                        <dd class="font-semibold text-slate-900">{{ number_format($user->payments_count) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt>FYDA checks</dt>
                        <dd class="font-semibold text-slate-900">{{ $fydaVerifications->count() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold text-slate-900">FYDA verification history</p>
                <div class="mt-4 space-y-4 text-xs text-slate-600">
                    @forelse ($fydaVerifications as $verification)
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="font-semibold text-slate-900">{{ ucfirst($verification->status) }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ optional($verification->created_at)->format('M d, Y H:i') }}</p>
                            @if ($verification->error_message)
                                <p class="mt-2 rounded-lg bg-rose-100 px-3 py-2 text-xs text-rose-700">{{ $verification->error_message }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-xs text-slate-500">No FYDA verification attempts yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
