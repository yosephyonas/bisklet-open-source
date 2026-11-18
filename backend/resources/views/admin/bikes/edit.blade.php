@extends('layouts.admin')

@section('title', 'Edit Bike')
@section('subtitle', 'Update fleet, pricing, or assignment details for bike ' . $bike->code)

@section('actions')
    <a href="{{ route('admin.bikes.show', $bike) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        <span>View bike</span>
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.bikes.update', $bike) }}" class="space-y-8">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Bike details</h2>
            <p class="mt-1 text-sm text-slate-500">Keep this bike's operational data accurate so dispatchers and riders have what they need.</p>

            <div class="mt-6">
                @include('admin.bikes._form')
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.bikes.show', $bike) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>Update bike</span>
            </button>
        </div>
    </form>
@endsection
