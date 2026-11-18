<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceTelemetry;
use App\Models\LockEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LockDeviceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status', 'provision_status', 'battery', 'online']);
        $liveImeis = $this->liveGatewayImeis();

        $query = Device::query()->with('bike');

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($builder) use ($q) {
                $builder->where('imei', 'like', '%'.$q.'%')
                    ->orWhere('lock_code', 'like', '%'.$q.'%')
                    ->orWhere('sim_iccid', 'like', '%'.$q.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provision_status'])) {
            $query->where('provision_status', $filters['provision_status']);
        }

        if (! empty($filters['battery'])) {
            if ($filters['battery'] === 'low') {
                $query->whereNotNull('battery_percent')->where('battery_percent', '<=', 20);
            } elseif ($filters['battery'] === 'healthy') {
                $query->whereNotNull('battery_percent')->where('battery_percent', '>', 20);
            } elseif ($filters['battery'] === 'unknown') {
                $query->whereNull('battery_percent');
            }
        }

        if (! empty($filters['online'])) {
            if ($filters['online'] === 'online') {
                $query->whereIn('imei', $liveImeis);
            } elseif ($filters['online'] === 'offline') {
                $query->whereNotIn('imei', $liveImeis);
            }
        }

        $devices = $query
            ->orderByDesc('last_seen_at')
            ->paginate(25);

        return view('admin.locks.devices.index', [
            'devices' => $devices,
            'filters' => $filters,
            'liveImeis' => $liveImeis,
        ]);
    }

    public function create()
    {
        $connectedCutoff = now()->subHours(24);

        $connectedRows = DeviceTelemetry::query()
            ->select('imei', DB::raw('MAX(received_at) as last_seen'))
            ->whereNotNull('imei')
            ->where('received_at', '>=', $connectedCutoff)
            ->groupBy('imei')
            ->orderByDesc('last_seen')
            ->get();

        $connectedImeis = $connectedRows->pluck('imei')->all();
        $registeredDevices = Device::query()
            ->with('bike')
            ->whereIn('imei', $connectedImeis)
            ->get()
            ->keyBy('imei');

        $connectedRegistered = [];
        $connectedUnregistered = [];

        foreach ($connectedRows as $row) {
            $device = $registeredDevices->get($row->imei);
            $autoFill = $this->resolveAutoFillData($row->imei);
            $suggestedLockCode = $device?->lock_code ?: ($autoFill['lock_code'] ?? null);
            $entry = [
                'imei' => $row->imei,
                'last_seen' => $row->last_seen,
                'device' => $device,
                'auto_fill' => $autoFill,
                'suggested_lock_code' => $suggestedLockCode,
            ];

            if ($device) {
                $connectedRegistered[] = $entry;
            } else {
                $connectedUnregistered[] = $entry;
            }
        }

        return view('admin.locks.devices.create', [
            'connectedRegistered' => $connectedRegistered,
            'connectedUnregistered' => $connectedUnregistered,
            'connectedCutoff' => $connectedCutoff,
        ]);
    }

    public function store(Request $request)
    {
        $imeiInput = trim((string) $request->input('imei', ''));
        $existingDevice = $imeiInput !== ''
            ? Device::query()->where('imei', $imeiInput)->first()
            : null;

        $validated = $request->validate([
            'imei' => ['required', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:255'],
            'hardware_version' => ['nullable', 'string', 'max:255'],
            'device_code' => ['nullable', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:255'],
            'firmware_build_date' => ['nullable', 'string', 'max:255'],
            'ble_mac' => ['nullable', 'string', 'max:255'],
            'sim_iccid' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'target_host' => ['nullable', 'string', 'max:255'],
            'target_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'lock_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('devices', 'lock_code')->ignore($existingDevice?->id),
            ],
        ]);

        $imei = trim((string) $validated['imei']);
        $device = $existingDevice ?? new Device;
        $autoFill = $this->resolveAutoFillData($imei);
        $fillableOptional = [
            'model',
            'hardware_version',
            'device_code',
            'firmware_version',
            'firmware_build_date',
            'ble_mac',
            'sim_iccid',
            'status',
            'target_host',
            'target_port',
        ];

        $payload = [
            'imei' => $imei,
            'lock_code' => trim((string) $validated['lock_code']),
        ];

        foreach ($fillableOptional as $field) {
            $inputValue = $validated[$field] ?? null;
            $inputHasValue = ! is_null($inputValue) && trim((string) $inputValue) !== '';
            if ($inputHasValue) {
                $payload[$field] = $inputValue;

                continue;
            }

            if (array_key_exists($field, $autoFill) && $autoFill[$field] !== null && $autoFill[$field] !== '') {
                $payload[$field] = $autoFill[$field];
            }
        }

        $payload['provision_status'] = 'applied';
        $device->fill($payload);
        $device->is_active = true;
        $device->provision_status = 'applied';
        if (! $device->exists) {
            $device->created_at = now();
        }
        $device->updated_at = now();
        $device->save();

        return redirect()
            ->route('admin.locks.devices.show', $device->imei)
            ->with('status', $existingDevice ? 'Device registration details updated.' : 'Device registered.');
    }

    public function show(Device $device)
    {
        $telemetry = DeviceTelemetry::query()
            ->where('imei', $device->imei)
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $lockEvents = LockEvent::query()
            ->where('imei', $device->imei)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.locks.devices.show', [
            'device' => $device,
            'telemetry' => $telemetry,
            'lockEvents' => $lockEvents,
            'isLiveConnected' => in_array($device->imei, $this->liveGatewayImeis(), true),
        ]);
    }

    public function logs(Request $request, Device $device): JsonResponse
    {
        $limit = max(20, min(200, (int) $request->query('limit', 100)));
        $logPath = storage_path('logs/laravel.log');
        $rawLog = $this->readTailChunk($logPath, 512 * 1024);

        $lines = preg_split('/\r\n|\r|\n/', $rawLog) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line) => trim($line) !== ''));

        $imei = trim((string) $device->imei);
        $lockCode = trim((string) ($device->lock_code ?? ''));

        $filtered = array_values(array_filter($lines, static function (string $line) use ($imei, $lockCode): bool {
            $lower = strtolower($line);

            if (str_contains($line, $imei)) {
                return true;
            }

            if ($lockCode !== '' && str_contains($line, $lockCode)) {
                return true;
            }

            return str_contains($lower, 'lock.')
                || str_contains($lower, 'lock_')
                || str_contains($lower, 'gateway')
                || str_contains($lower, 'tcp')
                || str_contains($lower, '9000');
        }));

        if (count($filtered) > $limit) {
            $filtered = array_slice($filtered, -$limit);
        }

        $listenerHost = $device->target_host ?: '0.0.0.0';
        $listenerPort = $device->target_port ?: 9000;

        return response()->json([
            'listener' => [
                'host' => $listenerHost,
                'port' => $listenerPort,
                'label' => $listenerHost.':'.$listenerPort,
                'configured_from_device' => (bool) ($device->target_host || $device->target_port),
            ],
            'source' => [
                'file' => $logPath,
            ],
            'lines' => $filtered,
            'fetched_at' => now()->toDateTimeString(),
        ]);
    }

    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'model' => ['nullable', 'string', 'max:255'],
            'hardware_version' => ['nullable', 'string', 'max:255'],
            'lock_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('devices', 'lock_code')->ignore($device->id),
            ],
            'device_code' => ['nullable', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:255'],
            'firmware_build_date' => ['nullable', 'string', 'max:255'],
            'ble_mac' => ['nullable', 'string', 'max:255'],
            'sim_iccid' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'target_host' => ['nullable', 'string', 'max:255'],
            'target_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'provision_status' => ['nullable', 'string', 'max:50'],
        ]);

        $targetHost = $validated['target_host'] ?? null;
        $targetPort = $validated['target_port'] ?? null;
        $targetChanged = $targetHost !== $device->target_host || $targetPort !== $device->target_port;
        $validated['lock_code'] = trim((string) $validated['lock_code']);

        $device->fill($validated);
        $device->is_active = $request->boolean('is_active');

        if ($targetChanged) {
            $device->last_provisioned_at = now();
            $device->last_provisioned_by_user_id = $request->user()?->id;
            if ($device->provision_status === 'applied') {
                $device->provision_status = 'pending';
            }
        }

        $device->save();

        return back()->with('status', 'Device details updated.');
    }

    public function restartConnection(Request $request, Device $device)
    {
        $requestId = (string) Str::ulid();
        $gatewayUrl = rtrim(config('services.go_gateway.base_url', 'http://127.0.0.1:9001'), '/');

        try {
            Log::info('admin.lock.restart_connection.request', [
                'imei' => $device->imei,
                'request_id' => $requestId,
                'gateway' => $gatewayUrl,
                'admin_user_id' => $request->user()?->id,
            ]);

            $response = Http::timeout(6)->post($gatewayUrl.'/commands/restart-connection', [
                'imei' => $device->imei,
                'request_id' => $requestId,
            ]);

            Log::info('admin.lock.restart_connection.response', [
                'imei' => $device->imei,
                'request_id' => $requestId,
                'http_status' => $response->status(),
                'payload' => $response->json(),
                'admin_user_id' => $request->user()?->id,
            ]);
        } catch (\Throwable $exception) {
            Log::error('admin.lock.restart_connection.exception', [
                'imei' => $device->imei,
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
                'admin_user_id' => $request->user()?->id,
            ]);
        }

        return back()->with('status', 'Restart connection request sent.');
    }

    public function reboot(Request $request, Device $device)
    {
        return $this->simpleGatewayCommand(
            $request,
            $device,
            '/commands/reboot',
            'admin.lock.reboot',
            'Reboot command sent.'
        );
    }

    public function powerOff(Request $request, Device $device)
    {
        return $this->simpleGatewayCommand(
            $request,
            $device,
            '/commands/power-off',
            'admin.lock.power_off',
            'Power-off command sent.'
        );
    }

    public function wifiPositioning(Request $request, Device $device)
    {
        $validated = $request->validate([
            'mode' => ['required', 'integer', 'in:0,1,2,3'],
            'scan_seconds' => ['nullable', 'integer', 'min:2', 'max:15'],
        ]);

        return $this->payloadGatewayCommand(
            $request,
            $device,
            '/commands/wifi-positioning',
            [
                'mode' => (int) $validated['mode'],
                'scan_seconds' => (int) ($validated['scan_seconds'] ?? 3),
            ],
            'admin.lock.wifi_positioning',
            'WiFi positioning command sent.'
        );
    }

    public function cableLockInfo(Request $request, Device $device)
    {
        return $this->simpleGatewayCommand(
            $request,
            $device,
            '/commands/cable-lock-info',
            'admin.lock.cable_lock_info',
            'Cable lock info request sent.'
        );
    }

    public function externalDevice(Request $request, Device $device)
    {
        $validated = $request->validate([
            'operation' => ['required', 'integer', 'in:1,2,3,4,17,18,19,20,33,34,36,37'],
        ]);

        return $this->payloadGatewayCommand(
            $request,
            $device,
            '/commands/external-device',
            ['operation' => (int) $validated['operation']],
            'admin.lock.external_device',
            'External device command sent.'
        );
    }

    public function beaconValidation(Request $request, Device $device)
    {
        $validated = $request->validate([
            'result' => ['required', 'integer', 'in:0,1,2,3,4'],
        ]);

        return $this->payloadGatewayCommand(
            $request,
            $device,
            '/commands/beacon-validation',
            ['result' => (int) $validated['result']],
            'admin.lock.beacon_validation',
            'Beacon validation command sent.'
        );
    }

    public function rfidAdminCard(Request $request, Device $device)
    {
        $validated = $request->validate([
            'operation' => ['required', 'integer', 'in:0,1,2'],
            'card_number' => ['nullable', 'string', 'max:64'],
        ]);

        $cardNumber = trim((string) ($validated['card_number'] ?? ''));
        if ((int) $validated['operation'] === 0 && $cardNumber === '') {
            return back()->with('status', 'Admin RFID set requires a card number.');
        }

        return $this->payloadGatewayCommand(
            $request,
            $device,
            '/commands/rfid-admin-card',
            [
                'operation' => (int) $validated['operation'],
                'card_number' => $cardNumber !== '' ? $cardNumber : '0',
            ],
            'admin.lock.rfid_admin_card',
            'RFID admin card command sent.'
        );
    }

    public function otaDetect(Request $request, Device $device)
    {
        $validated = $request->validate([
            'payload' => ['nullable', 'string'],
        ]);

        return $this->otaGatewayCommand(
            $request,
            $device,
            '/commands/ota/detect',
            $validated['payload'] ?? '',
            'admin.lock.ota.detect',
            'OTA detect/init command sent.'
        );
    }

    private function simpleGatewayCommand(
        Request $request,
        Device $device,
        string $endpoint,
        string $logPrefix,
        string $successMessage
    ) {
        return $this->payloadGatewayCommand($request, $device, $endpoint, [], $logPrefix, $successMessage);
    }

    private function payloadGatewayCommand(
        Request $request,
        Device $device,
        string $endpoint,
        array $payload,
        string $logPrefix,
        string $successMessage
    ) {
        $requestId = (string) Str::ulid();
        $gatewayUrl = rtrim(config('services.go_gateway.base_url', 'http://127.0.0.1:9001'), '/');
        $body = array_merge([
            'imei' => $device->imei,
            'request_id' => $requestId,
        ], $payload);

        try {
            Log::info($logPrefix.'.request', [
                'imei' => $device->imei,
                'request_id' => $requestId,
                'payload' => $payload,
                'gateway' => $gatewayUrl,
                'admin_user_id' => $request->user()?->id,
            ]);

            $response = Http::timeout(6)->post($gatewayUrl.$endpoint, $body);

            Log::info($logPrefix.'.response', [
                'imei' => $device->imei,
                'request_id' => $requestId,
                'http_status' => $response->status(),
                'payload' => $response->json(),
                'admin_user_id' => $request->user()?->id,
            ]);
        } catch (\Throwable $exception) {
            Log::error($logPrefix.'.exception', [
                'imei' => $device->imei,
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
                'admin_user_id' => $request->user()?->id,
            ]);
        }

        return back()->with('status', $successMessage);
    }

    private function otaGatewayCommand(
        Request $request,
        Device $device,
        string $endpoint,
        string $payload,
        string $logPrefix,
        string $successMessage
    ) {
        $parts = array_values(array_filter(array_map('trim', explode(',', $payload)), static fn ($value) => $value !== ''));

        return $this->payloadGatewayCommand(
            $request,
            $device,
            $endpoint,
            ['payload' => $parts],
            $logPrefix,
            $successMessage
        );
    }

    private function resolveAutoFillData(string $imei): array
    {
        $data = [];

        $existing = Device::query()->where('imei', $imei)->first();
        if ($existing) {
            $data = array_merge($data, Arr::only($existing->toArray(), [
                'model',
                'hardware_version',
                'device_code',
                'firmware_version',
                'firmware_build_date',
                'ble_mac',
                'sim_iccid',
                'status',
                'target_host',
                'target_port',
                'provision_status',
                'lock_code',
            ]));
        }

        $latestTelemetry = DeviceTelemetry::query()
            ->where('imei', $imei)
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->first();
        $telemetryPayload = is_array($latestTelemetry?->parsed_json) ? $latestTelemetry->parsed_json : [];
        $data = array_merge($data, array_filter([
            'model' => Arr::get($telemetryPayload, 'model'),
            'hardware_version' => Arr::get($telemetryPayload, 'hardware_version'),
            'device_code' => Arr::get($telemetryPayload, 'device_code'),
            'firmware_version' => Arr::get($telemetryPayload, 'firmware_version'),
            'firmware_build_date' => Arr::get($telemetryPayload, 'firmware_build_date'),
            'ble_mac' => Arr::get($telemetryPayload, 'ble_mac'),
            'sim_iccid' => Arr::get($telemetryPayload, 'sim_iccid'),
            'lock_code' => $this->extractLockCodeFromPayload($telemetryPayload),
        ], static fn ($value) => $value !== null && $value !== ''));

        $latestDeviceInfoEvent = LockEvent::query()
            ->where('imei', $imei)
            ->whereIn('type', ['device.info', 'device.iccid', 'device.ble_mac'])
            ->orderByDesc('created_at')
            ->first();
        $eventPayload = is_array($latestDeviceInfoEvent?->payload) ? $latestDeviceInfoEvent->payload : [];
        $data = array_merge($data, array_filter([
            'device_code' => Arr::get($eventPayload, 'device_code'),
            'firmware_version' => Arr::get($eventPayload, 'firmware_version'),
            'firmware_build_date' => Arr::get($eventPayload, 'firmware_build_date'),
            'sim_iccid' => Arr::get($eventPayload, 'sim_iccid'),
            'ble_mac' => Arr::get($eventPayload, 'ble_mac'),
            'lock_code' => $this->extractLockCodeFromPayload($eventPayload),
        ], static fn ($value) => $value !== null && $value !== ''));

        return $data;
    }

    private function extractLockCodeFromPayload(array $payload): ?string
    {
        foreach (['lock_code', 'lockCode', 'lock_sn', 'lockSn', 'sn', 'lock_id', 'lockId', 'lock'] as $key) {
            $value = Arr::get($payload, $key);
            if ($value === null) {
                continue;
            }

            $candidate = trim((string) $value);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function readTailChunk(string $path, int $maxBytes): string
    {
        if (! File::exists($path) || ! File::isReadable($path)) {
            return '';
        }

        $size = File::size($path);
        if (! $size || $size < 1) {
            return '';
        }

        $offset = max(0, $size - $maxBytes);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $chunk = (string) stream_get_contents($handle);
        fclose($handle);

        if ($offset > 0) {
            $firstLineBreak = strpos($chunk, "\n");
            if ($firstLineBreak !== false) {
                $chunk = substr($chunk, $firstLineBreak + 1);
            }
        }

        return $chunk;
    }

    private function liveGatewayImeis(): array
    {
        $gatewayUrl = rtrim(config('services.go_gateway.base_url', 'http://127.0.0.1:9001'), '/');

        try {
            $response = Http::timeout(2)->get($gatewayUrl.'/devices/live');
            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('devices', []))
                ->pluck('imei')
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('admin.lock.live_gateway_unavailable', [
                'gateway' => $gatewayUrl,
                'error' => $exception->getMessage(),
            ]);
        }

        return [];
    }
}
