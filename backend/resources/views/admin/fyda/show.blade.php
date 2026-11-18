@extends('layouts.admin')

@section('title', 'FYDA Verification #' . $verification->id)
@section('subtitle', 'Result payload and metadata for this ID verification request.')

@section('actions')
    <a href="{{ route('admin.fyda-verifications.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
        </svg>
        <span>Back to verifications</span>
    </a>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ strtoupper($verification->status) }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Requested</dt><dd>{{ optional($verification->created_at)->format('M d, Y H:i') }}</dd></div>
                <div class="flex items-center justify-between"><dt>Verified</dt><dd>{{ optional($verification->verified_at)->format('M d, Y H:i') ?? 'Pending' }}</dd></div>
                <div class="flex items-center justify-between"><dt>National ID</dt><dd class="font-mono text-sm">{{ $verification->national_id }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">User</p>
            <div class="mt-3 flex items-center justify-between">
                <div>
                    <p class="text-2xl font-semibold text-slate-900">{{ $verification->user?->first_name }} {{ $verification->user?->last_name }}</p>
                    <p class="text-sm text-slate-500">{{ $verification->user?->email ?? $verification->user?->phone ?? '-' }}</p>
                </div>
                @if ($verification->user)
                    <a href="{{ route('admin.users.show', $verification->user) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        <span>Open user</span>
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if ($verification->error_message)
        <div class="mt-8 rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">
            <p class="font-semibold">Provider error</p>
            <p class="mt-2">{{ $verification->error_message }}</p>
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Request payload</p>
                <span class="text-xs text-slate-500">JSON</span>
            </div>
            <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($verification->request_payload, JSON_PRETTY_PRINT) }}</pre>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Response payload</p>
                <span class="text-xs text-slate-500">JSON</span>
            </div>
            <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($verification->response_payload, JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
@endsection
