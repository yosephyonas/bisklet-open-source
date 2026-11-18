@extends('layouts.admin')

@section('title', 'Audit Log')
@section('subtitle', 'Track admin changes without exposing sensitive credentials.')

@section('content')
    <form method="GET" class="admin-compact-filter">
        <div class="admin-filter-field grow">
            <label for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Admin, route, action, path">
        </div>
        <div class="admin-filter-field">
            <label for="method">Method</label>
            <select id="method" name="method">
                <option value="">All</option>
                @foreach ($methods as $method)
                    <option value="{{ $method }}" @selected($filters['method'] === $method)>{{ $method }}</option>
                @endforeach
            </select>
        </div>
        <div class="admin-filter-actions">
            <a href="{{ route('admin.audit-logs.index') }}">Reset</a>
            <button type="submit">Filter</button>
        </div>
    </form>

    <div class="table-card mt-6">
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th class="text-right">Result</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>
                            <p class="font-semibold text-slate-900">{{ $log->created_at?->format('M d, H:i') }}</p>
                            <p class="text-xs text-slate-500">{{ $log->created_at?->diffForHumans() }}</p>
                        </td>
                        <td>
                            <p class="font-semibold text-slate-900">
                                {{ trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: 'System admin' }}
                            </p>
                            <p class="text-xs text-slate-500">{{ $log->user?->email ?? $log->ip_address ?? '-' }}</p>
                        </td>
                        <td>
                            <span class="status-pill neutral">{{ $log->method }}</span>
                            <p class="mt-2 font-semibold text-slate-900">{{ $log->description ?? $log->action }}</p>
                        </td>
                        <td>
                            <p class="font-mono text-xs text-slate-700">{{ $log->path }}</p>
                            <p class="text-xs text-slate-500">{{ $log->route_name }}</p>
                        </td>
                        <td class="text-right">
                            @php
                                $statusCode = (int) data_get($log->metadata, 'status_code', 0);
                                $statusClass = $statusCode >= 500 ? 'danger' : ($statusCode >= 400 ? 'warning' : 'success');
                            @endphp
                            <span class="status-pill {{ $statusClass }}">{{ $statusCode ?: 'OK' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-10 text-center text-sm text-slate-500">No admin actions have been recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $logs->withQueryString()->links() }}
    </div>
@endsection
