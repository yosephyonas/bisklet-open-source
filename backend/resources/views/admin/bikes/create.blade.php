@extends('layouts.admin')

@section('title', 'Add Bike')
@section('subtitle', 'Register a new bike into the BISKLET fleet and assign it to a station if needed.')

@section('actions')
    <a href="{{ route('admin.bikes.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
        </svg>
        <span>Back to bikes</span>
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.bikes.store') }}" class="space-y-8">
        @csrf
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Bike details</h2>
            <p class="mt-1 text-sm text-slate-500">Capture the hardware and pricing information we need to keep this bike on the road.</p>

            <div class="mt-6">
                @include('admin.bikes._form')
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.bikes.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>Save bike</span>
            </button>
        </div>
    </form>
@endsection
