<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            @foreach ($statuses as $statusOption)
                <option value="{{ $statusOption->value }}" @selected(old('status', $ride->status?->value ?? $ride->status) === $statusOption->value)>{{ ucfirst($statusOption->value) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="currency" class="block text-sm font-medium text-slate-700">Currency</label>
        <input type="text" id="currency" name="currency" maxlength="3" value="{{ old('currency', $ride->currency ?? 'ETB') }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm uppercase focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="started_at" class="block text-sm font-medium text-slate-700">Started at</label>
        <input type="datetime-local" id="started_at" name="started_at" value="{{ old('started_at', optional($ride->started_at)->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="ended_at" class="block text-sm font-medium text-slate-700">Ended at</label>
        <input type="datetime-local" id="ended_at" name="ended_at" value="{{ old('ended_at', optional($ride->ended_at)->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="duration_seconds" class="block text-sm font-medium text-slate-700">Duration (seconds)</label>
        <input type="number" id="duration_seconds" name="duration_seconds" min="0" value="{{ old('duration_seconds', $ride->duration_seconds) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="distance_m" class="block text-sm font-medium text-slate-700">Distance (meters)</label>
        <input type="number" id="distance_m" name="distance_m" step="0.01" min="0" value="{{ old('distance_m', $ride->distance_m) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="unlock_fee" class="block text-sm font-medium text-slate-700">Unlock fee (ETB)</label>
        <input type="number" id="unlock_fee" name="unlock_fee" step="0.01" min="0" value="{{ old('unlock_fee', $ride->unlock_fee) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="per_minute_rate" class="block text-sm font-medium text-slate-700">Per minute rate (ETB)</label>
        <input type="number" id="per_minute_rate" name="per_minute_rate" step="0.01" min="0" value="{{ old('per_minute_rate', $ride->per_minute_rate) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="total_amount" class="block text-sm font-medium text-slate-700">Total amount (ETB)</label>
        <input type="number" id="total_amount" name="total_amount" step="0.01" min="0" value="{{ old('total_amount', $ride->total_amount) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="start_station_id" class="block text-sm font-medium text-slate-700">Start station</label>
        <select id="start_station_id" name="start_station_id" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            <option value="">Unknown</option>
            @foreach ($stations as $station)
                <option value="{{ $station->id }}" @selected(old('start_station_id', $ride->start_station_id) == $station->id)>{{ $station->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="end_station_id" class="block text-sm font-medium text-slate-700">End station</label>
        <select id="end_station_id" name="end_station_id" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            <option value="">Unknown</option>
            @foreach ($stations as $station)
                <option value="{{ $station->id }}" @selected(old('end_station_id', $ride->end_station_id) == $station->id)>{{ $station->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="start_latitude" class="block text-sm font-medium text-slate-700">Start latitude</label>
        <input type="number" id="start_latitude" name="start_latitude" step="0.000001" value="{{ old('start_latitude', $ride->start_latitude) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="start_longitude" class="block text-sm font-medium text-slate-700">Start longitude</label>
        <input type="number" id="start_longitude" name="start_longitude" step="0.000001" value="{{ old('start_longitude', $ride->start_longitude) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="end_latitude" class="block text-sm font-medium text-slate-700">End latitude</label>
        <input type="number" id="end_latitude" name="end_latitude" step="0.000001" value="{{ old('end_latitude', $ride->end_latitude) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="end_longitude" class="block text-sm font-medium text-slate-700">End longitude</label>
        <input type="number" id="end_longitude" name="end_longitude" step="0.000001" value="{{ old('end_longitude', $ride->end_longitude) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div class="md:col-span-2">
        <label for="pricing_breakdown" class="block text-sm font-medium text-slate-700">Pricing breakdown (JSON)</label>
        <textarea id="pricing_breakdown" name="pricing_breakdown" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-xs focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('pricing_breakdown', $ride->pricing_breakdown ? json_encode($ride->pricing_breakdown, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label for="telemetry" class="block text-sm font-medium text-slate-700">Telemetry (JSON)</label>
        <textarea id="telemetry" name="telemetry" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-xs focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('telemetry', $ride->telemetry ? json_encode($ride->telemetry, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label for="metadata" class="block text-sm font-medium text-slate-700">Metadata (JSON)</label>
        <textarea id="metadata" name="metadata" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-xs focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('metadata', $ride->metadata ? json_encode($ride->metadata, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
</div>
