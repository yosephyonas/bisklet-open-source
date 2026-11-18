@extends('layouts.admin')

@section('title', 'Listener Terminal')
@section('subtitle', 'Raw gateway output for troubleshooting live lock connections.')

@section('content')
    @include('admin.locks.partials.nav')

    <section class="ops-terminal">
        <header class="ops-terminal__header">
            <div>
                <p class="ops-terminal__title">Lock listener</p>
                <span class="ops-terminal__sub"><span id="terminal-listener-label">{{ $listenerLabel }}</span> · <span id="terminal-log-source">{{ $logSource }}</span></span>
            </div>
            <div class="ops-actions">
                <span id="terminal-live-state" class="ops-badge ops-badge--success">Live</span>
                <button type="button" id="terminal-toggle-live" class="ops-button ops-button--small">Pause</button>
                <button type="button" id="terminal-refresh" class="ops-button ops-button--small">Refresh</button>
            </div>
        </header>
        <div class="ops-terminal__meta">
            <span>listener output</span>
            <span id="terminal-meta">Loading…</span>
        </div>
        <pre id="terminal-output" class="ops-terminal__output" aria-live="polite">Loading listener logs…</pre>
    </section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const endpoint = @json(route('admin.locks.listener.logs'));
    const output = document.getElementById('terminal-output');
    const meta = document.getElementById('terminal-meta');
    const refreshButton = document.getElementById('terminal-refresh');
    const liveButton = document.getElementById('terminal-toggle-live');
    const liveState = document.getElementById('terminal-live-state');
    const listenerLabel = document.getElementById('terminal-listener-label');
    const sourceLabel = document.getElementById('terminal-log-source');
    let isLive = true;
    let loading = false;

    function setLive(enabled) {
        isLive = enabled;
        liveButton.textContent = enabled ? 'Pause' : 'Resume';
        liveState.textContent = enabled ? 'Live' : 'Paused';
        liveState.className = 'ops-badge ' + (enabled ? 'ops-badge--success' : '');
    }

    async function fetchLogs() {
        if (loading) return;
        loading = true;
        refreshButton.disabled = true;
        const nearBottom = output.scrollHeight - output.scrollTop - output.clientHeight < 32;

        try {
            const response = await fetch(endpoint + '?limit=260', { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            const lines = Array.isArray(data.lines) ? data.lines : [];
            output.textContent = lines.length ? lines.join('\n') : 'No listener logs found.';
            meta.textContent = 'Updated ' + (data.fetched_at || '—');
            if (data.listener?.label) listenerLabel.textContent = data.listener.label;
            if (data.source?.file) sourceLabel.textContent = data.source.file;
            if (nearBottom) output.scrollTop = output.scrollHeight;
        } catch (error) {
            meta.textContent = 'Update failed';
        } finally {
            loading = false;
            refreshButton.disabled = false;
        }
    }

    refreshButton.addEventListener('click', fetchLogs);
    liveButton.addEventListener('click', function () { setLive(!isLive); });
    fetchLogs();
    setInterval(function () { if (isLive) fetchLogs(); }, 3000);
});
</script>
@endpush
