@extends('layouts.admin')

@section('title', 'Schedule Maintenance')
@section('subtitle', 'Log a repair or inspection so the team stays on top of fleet health.')

@section('actions')
    <a href="{{ route('admin.maintenance.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
        </svg>
        <span>Back to list</span>
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.maintenance.store') }}" class="space-y-8">
        @csrf
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Maintenance details</h2>
            <p class="mt-1 text-sm text-slate-500">Provide enough context for whoever picks up this task.</p>

            <div class="mt-6">
                @include('admin.maintenance._form')
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.maintenance.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>Create task</span>
            </button>
        </div>
    </form>
@endsection
