@extends('layouts.admin')

@section('title', 'Edit user')

@section('actions')
    <a href="{{ route('admin.users.show', $user) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        <span>Back</span>
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('admin.users._form', ['user' => $user, 'roles' => $roles])

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.users.show', $user) }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancel</a>
                <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Save changes</button>
            </div>
        </form>

        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="rounded-2xl border border-rose-200 bg-rose-50/60 px-6 py-5 shadow-sm" onsubmit="return confirm('Delete this user? This action cannot be undone.')">
            @csrf
            @method('DELETE')
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-rose-700">Danger zone</p>
                    <p class="text-xs text-rose-500">Deleting the user will remove their access permanently.</p>
                </div>
                <button type="submit" class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-500">Delete user</button>
            </div>
        </form>
    </div>
@endsection
