<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bike;
use App\Models\Device;
use App\Models\DeviceTelemetry;
use App\Models\LockEvent;
use App\Models\OperationalAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LockOpsController extends Controller
{
    public function listenerTerminal(Request $request)
    {
        return view('admin.locks.terminal', [
            'listenerLabel' => env('GO_GATEWAY_TCP_ADDR', '0.0.0.0:9000'),
            'logSource' => storage_path('logs/laravel.log'),
        ]);
    }

    public function index(Request $request)
    {
        $batteryThreshold = max(0, (int) $request->input('battery_threshold', 20));
        $liveImeis = $this->liveGatewayImeis();
        $listenerLabel = env('GO_GATEWAY_TCP_ADDR', '0.0.0.0:9000');

        $totals = [
            'devices' => Device::query()->count(),
            'online' => Device::query()
                ->whereIn('imei', $liveImeis)
                ->count(),
            'offline' => Device::query()
                ->whereNotIn('imei', $liveImeis)
                ->count(),
            'lowBattery' => Device::query()
                ->whereNotNull('battery_percent')
                ->where('battery_percent', '<=', $batteryThreshold)
                ->count(),
            'alarms' => LockEvent::query()
                ->where('type', 'alarm.triggered')
                ->count(),
        ];

        $recentAlarms = LockEvent::query()
            ->where('type', 'alarm.triggered')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentTelemetry = DeviceTelemetry::query()
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentDevices = Device::query()
            ->with('bike')
            ->whereIn('imei', $liveImeis)
            ->orderByDesc('last_seen_at')
            ->limit(8)
            ->get();

        $dashboardImeiPool = collect()
            ->merge($recentAlarms->pluck('imei'))
            ->merge($recentTelemetry->pluck('imei'))
            ->merge($recentDevices->pluck('imei'))
            ->filter()
            ->unique()
            ->values();
        $bikeNames = Bike::query()
            ->whereIn('imei', $dashboardImeiPool)
            ->get()
            ->mapWithKeys(fn (Bike $bike) => [
                $bike->imei => trim(($bike->code ?: 'Bike').($bike->model ? ' ('.$bike->model.')' : '')),
            ]);

        return view('admin.locks.dashboard', [
            'totals' => $totals,
            'recentAlarms' => $recentAlarms,
            'recentTelemetry' => $recentTelemetry,
            'recentDevices' => $recentDevices,
            'bikeNames' => $bikeNames,
            'batteryThreshold' => $batteryThreshold,
            'liveImeis' => $liveImeis,
            'listenerLabel' => $listenerLabel,
        ]);
    }

    public function alerts(Request $request)
    {
        $batteryThreshold = max(0, (int) $request->input('battery_threshold', 20));
        $liveImeis = $this->liveGatewayImeis();

        $alarms = LockEvent::query()
            ->where('type', 'alarm.triggered')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $alarmDevices = Device::query()
            ->with('bike')
            ->whereIn('imei', $alarms->pluck('imei')->filter()->unique())
            ->get()
            ->keyBy('imei');

        $offlineDevices = Device::query()
            ->with('bike')
            ->whereNotIn('imei', $liveImeis)
            ->orderBy('last_seen_at')
            ->limit(50)
            ->get();

        $lowBatteryDevices = Device::query()
            ->with('bike')
            ->whereNotNull('battery_percent')
            ->where('battery_percent', '<=', $batteryThreshold)
            ->orderBy('battery_percent')
            ->limit(50)
            ->get();

        $operationalAlerts = OperationalAlert::query()
            ->with(['bike:id,code,model', 'ride:id,user_id,status', 'user:id,first_name,last_name'])
            ->where('status', 'open')
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.locks.alerts', [
            'operationalAlerts' => $operationalAlerts,
            'alarms' => $alarms,
            'offlineDevices' => $offlineDevices,
            'lowBatteryDevices' => $lowBatteryDevices,
            'alarmDevices' => $alarmDevices,
            'batteryThreshold' => $batteryThreshold,
            'liveImeis' => $liveImeis,
        ]);
    }

    public function telemetry(Request $request)
    {
        $filters = $request->only(['imei', 'type', 'event']);

        $telemetryQuery = DeviceTelemetry::query();
        if (! empty($filters['imei'])) {
            $telemetryQuery->where('imei', 'like', '%'.$filters['imei'].'%');
        }
        if (! empty($filters['type'])) {
            $telemetryQuery->where('type', $filters['type']);
        }

        $eventQuery = LockEvent::query();
        if (! empty($filters['imei'])) {
            $eventQuery->where('imei', 'like', '%'.$filters['imei'].'%');
        }
        if (! empty($filters['event'])) {
            $eventQuery->where('type', $filters['event']);
        }

        $telemetry = $telemetryQuery
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $lockEvents = $eventQuery
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $telemetryTypes = DeviceTelemetry::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        $eventTypes = LockEvent::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        $telemetryImeiPool = collect()
            ->merge($telemetry->pluck('imei'))
            ->merge($lockEvents->pluck('imei'))
            ->filter()
            ->unique()
            ->values();
        $bikeNames = Bike::query()
            ->whereIn('imei', $telemetryImeiPool)
            ->get()
            ->mapWithKeys(fn (Bike $bike) => [
                $bike->imei => trim(($bike->code ?: 'Bike').($bike->model ? ' ('.$bike->model.')' : '')),
            ]);

        $recentCounts = [
            'telemetryHour' => DeviceTelemetry::query()
                ->where(function ($query) {
                    $cutoff = now()->subHour();
                    $query->where('received_at', '>=', $cutoff)
                        ->orWhere('created_at', '>=', $cutoff);
                })
                ->count(),
            'eventsHour' => LockEvent::query()->where('created_at', '>=', now()->subHour())->count(),
        ];

        return view('admin.locks.telemetry', [
            'filters' => $filters,
            'telemetry' => $telemetry,
            'lockEvents' => $lockEvents,
            'telemetryTypes' => $telemetryTypes,
            'eventTypes' => $eventTypes,
            'recentCounts' => $recentCounts,
            'bikeNames' => $bikeNames,
        ]);
    }

    public function listenerLogs(Request $request): JsonResponse
    {
        $limit = max(30, min(300, (int) $request->query('limit', 150)));
        $logPath = storage_path('logs/laravel.log');
        $rawLog = $this->readTailChunk($logPath, 1024 * 1024);

        $lines = preg_split('/\r\n|\r|\n/', $rawLog) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line) => trim($line) !== ''));

        $filtered = array_values(array_filter($lines, static function (string $line): bool {
            $lower = strtolower($line);

            return str_contains($lower, 'lock.')
                || str_contains($lower, 'lock_')
                || str_contains($lower, 'gateway')
                || str_contains($lower, 'tcp')
                || str_contains($lower, 'omni')
                || str_contains($line, ':9000')
                || str_contains($line, ':9001');
        }));

        if (count($filtered) > $limit) {
            $filtered = array_slice($filtered, -$limit);
        }

        $listenerLabel = env('GO_GATEWAY_TCP_ADDR', '0.0.0.0:9000');

        return response()->json([
            'listener' => [
                'label' => $listenerLabel,
            ],
            'source' => [
                'file' => $logPath,
            ],
            'lines' => $filtered,
            'fetched_at' => now()->toDateTimeString(),
        ]);
    }

    public function restartListenerConnections(Request $request)
    {
        $requestId = (string) Str::ulid();
        $gatewayUrl = rtrim(config('services.go_gateway.base_url', 'http://127.0.0.1:9001'), '/');

        try {
            Log::info('admin.lock.listener.restart.request', [
                'request_id' => $requestId,
                'gateway' => $gatewayUrl,
                'admin_user_id' => $request->user()?->id,
            ]);

            $response = Http::timeout(8)->post($gatewayUrl.'/commands/restart-all-connections', [
                'request_id' => $requestId,
            ]);

            $payload = $response->json();
            Log::info('admin.lock.listener.restart.response', [
                'request_id' => $requestId,
                'http_status' => $response->status(),
                'payload' => $payload,
                'admin_user_id' => $request->user()?->id,
            ]);

            $closed = (int) data_get($payload, 'closed_connections', 0);

            return back()->with('status', "Listener restart sent. Closed {$closed} active connection(s).");
        } catch (\Throwable $exception) {
            Log::error('admin.lock.listener.restart.exception', [
                'request_id' => $requestId,
                'error' => $exception->getMessage(),
                'admin_user_id' => $request->user()?->id,
            ]);
        }

        return back()->with('status', 'Listener restart request sent.');
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
