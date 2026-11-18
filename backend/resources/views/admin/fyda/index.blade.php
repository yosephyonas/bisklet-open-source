@extends('layouts.admin')

@section('title', 'FYDA Verifications')
@section('subtitle', 'Audit national ID lookups and monitor verification success rates.')

@section('actions')
    <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h12a1 1 0 001-1V10" />
        </svg>
        <span>Back to dashboard</span>
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status" name="status" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All</option>
                <option value="verified" @selected(($filters['status'] ?? '') === 'verified')>Verified</option>
                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
            </select>
        </div>
        <div>
            <label for="national_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">National ID</label>
            <input type="text" id="national_id" name="national_id" value="{{ $filters['national_id'] ?? '' }}" placeholder="11 digit ID" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div class="md:col-span-2">
            <label for="user" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">User</label>
            <input type="text" id="user" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="Name, email, phone" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div class="md:col-span-4 flex items-center justify-end gap-3">
            <a href="{{ route('admin.fyda-verifications.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Reset</a>
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3">Verification</th>
                    <th class="px-6 py-3">User</th>
                    <th class="px-6 py-3">National ID</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Requested</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($verifications as $verification)
                    <tr class="hover:bg-slate-50">
                        @php
                            $statusValue = strtolower($verification->status ?? 'unknown');
                            $statusClass = match ($statusValue) {
                                'verified' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.fyda-verifications.show', $verification) }}" class="font-semibold text-slate-900 hover:text-slate-700">#{{ $verification->id }}</a>
                            <p class="text-xs text-slate-500">{{ optional($verification->created_at)->diffForHumans() }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p>{{ $verification->user?->first_name }} {{ $verification->user?->last_name }}</p>
                            <p class="text-xs text-slate-500">{{ $verification->user?->email ?? $verification->user?->phone ?? '-' }}</p>
                        </td>
                        <td class="px-6 py-4 font-mono text-sm">{{ $verification->national_id }}</td>
                        <td class="px-6 py-4">
                            <span class="status-pill {{ $statusClass }}">{{ strtoupper($statusValue) }}</span>
                        </td>
                        <td class="px-6 py-4 text-right text-xs text-slate-500">{{ optional($verification->verified_at ?? $verification->created_at)->format('M d, Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">No FYDA verifications found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $verifications->withQueryString()->links() }}
    </div>
@endsection
