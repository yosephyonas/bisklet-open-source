<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="station_id" class="block text-sm font-medium text-slate-700">Station</label>
        <select id="station_id" name="station_id" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            <option value="">Unassigned</option>
            @foreach ($stations as $station)
                <option value="{{ $station->id }}" @selected(old('station_id', $bike->station_id) == $station->id)>{{ $station->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
        <input type="text" id="code" name="code" value="{{ old('code', $bike->code) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="lock_code" class="block text-sm font-medium text-slate-700">Lock code</label>
        <input type="text" id="lock_code" name="lock_code" value="{{ old('lock_code', $bike->device?->lock_code) }}" data-lock-lookup-url="{{ route('admin.bikes.lock-lookup') }}" data-bike-id="{{ $bike->id }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20" placeholder="Enter lock code to link">
        <p class="mt-2 text-xs text-slate-500">Enter the lock code to fetch its IMEI and bind it to this bike.</p>
        <p id="lock_lookup_message" class="mt-2 text-xs text-slate-500"></p>
    </div>
    <div>
        <label for="imei" class="block text-sm font-medium text-slate-700">IMEI</label>
        <input type="text" id="imei" name="imei" value="{{ old('imei', $bike->imei) }}" readonly class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20" placeholder="Auto-filled from lock code">
    </div>
    <div>
        <label for="model" class="block text-sm font-medium text-slate-700">Model</label>
        <input type="text" id="model" name="model" value="{{ old('model', $bike->model) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="type" class="block text-sm font-medium text-slate-700">Type</label>
        <input type="text" id="type" name="type" value="{{ old('type', $bike->type) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $bike->status?->value ?? $bike->status) === $status->value)>{{ ucfirst(str_replace('_', ' ', $status->value)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
        <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $bike->is_active)) class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900/40">
        <label for="is_active" class="text-sm text-slate-600">Bike is active</label>
    </div>
    <div>
        <label for="battery_level" class="block text-sm font-medium text-slate-700">Battery level (%)</label>
        <input type="number" id="battery_level" name="battery_level" value="{{ old('battery_level', $bike->battery_level) }}" min="0" max="100" step="0.1" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="odometer_km" class="block text-sm font-medium text-slate-700">Odometer (km)</label>
        <input type="number" id="odometer_km" name="odometer_km" value="{{ old('odometer_km', $bike->odometer_km) }}" min="0" step="0.1" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        Fixed ride pricing: ETB {{ number_format(config('pricing.rides.price_birr', 10), 2) }} per started {{ config('pricing.rides.minutes', 20) }}-minute block, plus ETB {{ number_format(config('pricing.rides.unlock_fee', 5), 2) }} unlock fee.
    </div>
    <div>
        <label for="last_service_at" class="block text-sm font-medium text-slate-700">Last service date</label>
        <input type="datetime-local" id="last_service_at" name="last_service_at" value="{{ old('last_service_at', optional($bike->last_service_at)->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div class="md:col-span-2">
        <label for="hardware" class="block text-sm font-medium text-slate-700">Hardware (JSON)</label>
        <textarea id="hardware" name="hardware" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('hardware', $bike->hardware ? json_encode($bike->hardware, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label for="metadata" class="block text-sm font-medium text-slate-700">Metadata (JSON)</label>
        <textarea id="metadata" name="metadata" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('metadata', $bike->metadata ? json_encode($bike->metadata, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lockInput = document.getElementById('lock_code');
            const imeiInput = document.getElementById('imei');
            const messageEl = document.getElementById('lock_lookup_message');

            if (!lockInput || !imeiInput || !messageEl) {
                return;
            }

            const lookupUrl = lockInput.dataset.lockLookupUrl;
            const bikeId = lockInput.dataset.bikeId;
            let debounceId = null;

            const setMessage = (text, tone) => {
                messageEl.textContent = text;
                messageEl.classList.remove('text-slate-500', 'text-rose-600', 'text-emerald-600');

                if (tone === 'error') {
                    messageEl.classList.add('text-rose-600');
                } else if (tone === 'success') {
                    messageEl.classList.add('text-emerald-600');
                } else {
                    messageEl.classList.add('text-slate-500');
                }
            };

            const clearLock = () => {
                imeiInput.value = '';
                setMessage('', 'idle');
            };

            const lookupLock = () => {
                const code = lockInput.value.trim();

                if (!code) {
                    clearLock();
                    return;
                }

                if (!lookupUrl) {
                    setMessage('Lock lookup route missing.', 'error');
                    return;
                }

                setMessage('Looking up lock...', 'info');

                const url = new URL(lookupUrl, window.location.origin);
                url.searchParams.set('lock_code', code);
                if (bikeId) {
                    url.searchParams.set('bike_id', bikeId);
                }

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(async (response) => {
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(data.message || 'Lock not found.');
                        }
                        return data;
                    })
                    .then((data) => {
                        if (data.assigned) {
                            imeiInput.value = '';
                            const assignedCode = data.assigned_bike ? data.assigned_bike.code : 'another bike';
                            setMessage(`Lock already assigned to ${assignedCode}.`, 'error');
                            return;
                        }
                        imeiInput.value = data.imei || '';
                        setMessage(`IMEI ${data.imei} linked.`, 'success');
                    })
                    .catch((error) => {
                        imeiInput.value = '';
                        setMessage(error.message || 'Lock lookup failed.', 'error');
                    });
            };

            lockInput.addEventListener('input', function () {
                if (debounceId) {
                    clearTimeout(debounceId);
                }
                debounceId = setTimeout(lookupLock, 350);
            });

            lockInput.addEventListener('blur', lookupLock);
        });
    </script>
@endpush
