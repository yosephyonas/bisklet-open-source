@extends('layouts.admin')

@section('title', 'Create station')

@section('actions')
    <a href="{{ route('admin.stations.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        <span>Back</span>
    </a>
@endsection

@section('content')
    <form action="{{ route('admin.stations.store') }}" method="POST" class="space-y-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('admin.stations._form', ['station' => $station, 'statuses' => $statuses])

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.stations.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Create station</button>
        </div>
    </form>
@endsection
