@extends('layouts.admin')

@section('title', 'Users')

@section('actions')
    <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-slate-900/20 transition hover:bg-slate-800">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
        </svg>
        <span>New user</span>
    </a>
@endsection

@section('content')
    <form method="GET" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, phone, national ID" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
        </div>
        <div>
            <label for="role" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Role</label>
            <select id="role" name="role" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ ucfirst($role->value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-3 flex justify-end gap-3">
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Reset</a>
            <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-6 py-3">User</th>
                    <th class="px-6 py-3">Contact</th>
                    <th class="px-6 py-3">Role</th>
                    <th class="px-6 py-3">Rides</th>
                    <th class="px-6 py-3">Payments</th>
                    <th class="px-6 py-3 text-right">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.users.show', $user) }}" class="font-semibold text-slate-900 hover:text-slate-700">{{ $user->first_name }} {{ $user->last_name }}</a>
                            <p class="text-xs text-slate-500">National ID: {{ $user->national_id }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <p>{{ $user->email ?? '-' }}</p>
                            <p class="text-xs text-slate-500">{{ $user->phone ?? '-' }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded-lg bg-slate-900/10 px-3 py-1 text-xs font-semibold text-slate-900">{{ ucfirst($user->role?->value ?? $user->role) }}</span>
                            @if ($user->fyda_verified_at)
                                <p class="mt-1 text-xs text-emerald-600">FYDA verified</p>
                            @endif
                        </td>
                        <td class="px-6 py-4">{{ number_format($user->rides_count) }}</td>
                        <td class="px-6 py-4">{{ number_format($user->payments_count) }}</td>
                        <td class="px-6 py-4 text-right text-xs text-slate-500">{{ optional($user->created_at)->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->withQueryString()->links() }}
    </div>
@endsection
