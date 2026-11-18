<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $station->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
        <input type="text" id="code" name="code" value="{{ old('code', $station->code) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div class="md:col-span-2">
        <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
        <textarea id="description" name="description" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('description', $station->description) }}</textarea>
    </div>
    <div>
        <label for="latitude" class="block text-sm font-medium text-slate-700">Latitude</label>
        <input type="number" step="0.000001" id="latitude" name="latitude" value="{{ old('latitude', $station->latitude) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="longitude" class="block text-sm font-medium text-slate-700">Longitude</label>
        <input type="number" step="0.000001" id="longitude" name="longitude" value="{{ old('longitude', $station->longitude) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="capacity" class="block text-sm font-medium text-slate-700">Capacity</label>
        <input type="number" id="capacity" name="capacity" value="{{ old('capacity', $station->capacity) }}" min="0" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="available_docks" class="block text-sm font-medium text-slate-700">Available docks</label>
        <input type="number" id="available_docks" name="available_docks" value="{{ old('available_docks', $station->available_docks) }}" min="0" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $station->status) === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label for="metadata" class="block text-sm font-medium text-slate-700">Metadata (JSON)</label>
        <textarea id="metadata" name="metadata" rows="3" placeholder="{&quot;supports_kiosks&quot;: true}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('metadata', $station->metadata ? json_encode($station->metadata, JSON_PRETTY_PRINT) : '') }}</textarea>
        <p class="mt-2 text-xs text-slate-400">Optional structured data to sync with IoT devices.</p>
    </div>
</div>
