<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="bike_id" class="block text-sm font-medium text-slate-700">Bike</label>
        <select id="bike_id" name="bike_id" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            <option value="">Select bike</option>
            @foreach ($bikes as $bikeOption)
                <option value="{{ $bikeOption->id }}" @selected(old('bike_id', $log->bike_id) == $bikeOption->id)>{{ $bikeOption->code }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="user_id" class="block text-sm font-medium text-slate-700">Assigned technician</label>
        <select id="user_id" name="user_id" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            <option value="">Unassigned</option>
            @foreach ($users as $technician)
                <option value="{{ $technician->id }}" @selected(old('user_id', $log->user_id) == $technician->id)>{{ $technician->first_name }} {{ $technician->last_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="type" class="block text-sm font-medium text-slate-700">Task type</label>
        <input type="text" id="type" name="type" value="{{ old('type', $log->type) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20" placeholder="Eg. Brake tune, Tire replacement">
    </div>
    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            @foreach ($statuses as $statusOption)
                <option value="{{ $statusOption }}" @selected(old('status', $log->status) === $statusOption)>{{ ucfirst(str_replace('_', ' ', $statusOption)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label for="notes" class="block text-sm font-medium text-slate-700">Notes</label>
        <textarea id="notes" name="notes" rows="4" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20" placeholder="Describe the issue, parts used, or follow-up steps">{{ old('notes', $log->notes) }}</textarea>
    </div>
    <div>
        <label for="performed_at" class="block text-sm font-medium text-slate-700">Performed at</label>
        <input type="datetime-local" id="performed_at" name="performed_at" value="{{ old('performed_at', optional($log->performed_at)->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="cost" class="block text-sm font-medium text-slate-700">Cost (ETB)</label>
        <input type="number" id="cost" name="cost" step="0.01" min="0" value="{{ old('cost', $log->cost) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div class="md:col-span-2">
        <label for="metadata" class="block text-sm font-medium text-slate-700">Metadata (JSON)</label>
        <textarea id="metadata" name="metadata" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-xs focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('metadata', $log->metadata ? json_encode($log->metadata, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
</div>
