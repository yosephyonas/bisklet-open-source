<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label for="provider" class="block text-sm font-medium text-slate-700">Provider</label>
        <input type="text" id="provider" name="provider" value="{{ old('provider', $payment->provider) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20" placeholder="Telebirr, Chapa, Manual">
    </div>
    <div>
        <label for="provider_reference" class="block text-sm font-medium text-slate-700">Provider reference</label>
        <input type="text" id="provider_reference" name="provider_reference" value="{{ old('provider_reference', $payment->provider_reference) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20" placeholder="Transaction ID">
    </div>
    <div>
        <label for="amount" class="block text-sm font-medium text-slate-700">Amount</label>
        <input type="number" step="0.01" min="0" id="amount" name="amount" value="{{ old('amount', $payment->amount) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="currency" class="block text-sm font-medium text-slate-700">Currency</label>
        <input type="text" id="currency" name="currency" maxlength="3" value="{{ old('currency', $payment->currency) }}" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm uppercase focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div>
        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            @foreach ($statuses as $statusOption)
                <option value="{{ $statusOption }}" @selected(old('status', $payment->status) === $statusOption)>{{ ucfirst($statusOption) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="paid_at" class="block text-sm font-medium text-slate-700">Paid at</label>
        <input type="datetime-local" id="paid_at" name="paid_at" value="{{ old('paid_at', optional($payment->paid_at)->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
    </div>
    <div class="md:col-span-2">
        <label for="payload" class="block text-sm font-medium text-slate-700">Provider payload (JSON)</label>
        <textarea id="payload" name="payload" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-xs focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('payload', $payment->payload ? json_encode($payment->payload, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
    <div class="md:col-span-2">
        <label for="metadata" class="block text-sm font-medium text-slate-700">Metadata (JSON)</label>
        <textarea id="metadata" name="metadata" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-xs focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">{{ old('metadata', $payment->metadata ? json_encode($payment->metadata, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
</div>
