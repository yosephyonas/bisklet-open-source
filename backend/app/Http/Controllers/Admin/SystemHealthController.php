<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\FydaVerification;
use App\Models\OperationalAlert;
use App\Models\Payment;
use App\Models\Ride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.system-health.index', [
            'summary' => $this->summary(),
            'checks' => $this->checks(),
            'operations' => $this->operations(),
        ]);
    }

    private function summary(): array
    {
        $checks = collect($this->checks());

        return [
            'ready' => $checks->where('state', 'ready')->count(),
            'attention' => $checks->where('state', 'attention')->count(),
            'missing' => $checks->where('state', 'missing')->count(),
        ];
    }

    private function checks(): array
    {
        return [
            $this->check('Database', $this->databaseReady(), 'Primary data store responds to queries.'),
            $this->check('App URL', filled(config('app.url')), (string) config('app.url')),
            $this->check('Storage writable', is_writable(storage_path()) && is_writable(storage_path('framework')), 'Required for logs, cache, and generated files.'),
            $this->check('Cache driver', filled(config('cache.default')), 'Driver: ' . (string) config('cache.default')),
            $this->check('Queue driver', filled(config('queue.default')), 'Driver: ' . (string) config('queue.default')),
            $this->check('Mapbox token', filled(config('services.mapbox.access_token')), 'Needed for maps and route tools.'),
            $this->check('Go gateway URL', filled(config('services.go_gateway.base_url')), (string) config('services.go_gateway.base_url')),
            $this->check('Go gateway command token', filled(config('services.go_gateway.command_token')), 'Needed for lock commands.'),
            $this->check('FYDA client ID', filled(config('fyda.oidc.client_id')), 'OIDC partner client.'),
            $this->check('FYDA redirect URI', filled(config('fyda.oidc.redirect_uri')), (string) config('fyda.oidc.redirect_uri')),
            $this->check('FYDA private key', filled(config('fyda.oidc.private_key_base64')), 'Used to sign client assertions.'),
            $this->check('Telebirr webhook secret', filled(config('services.payments.providers.telebirr.webhook_secret')), 'Needed to verify payment callbacks.'),
        ];
    }

    private function operations(): array
    {
        return [
            [
                'label' => 'Open alerts',
                'value' => Schema::hasTable('operational_alerts') ? OperationalAlert::where('status', 'open')->count() : 0,
                'route' => route('admin.locks.alerts'),
                'tone' => 'danger',
            ],
            [
                'label' => 'Offline locks',
                'value' => Schema::hasTable('devices') ? Device::where(function ($query) {
                    $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes(15));
                })->count() : 0,
                'route' => route('admin.locks.devices.index'),
                'tone' => 'warning',
            ],
            [
                'label' => 'Low battery locks',
                'value' => Schema::hasTable('devices') ? Device::whereNotNull('battery_percent')->where('battery_percent', '<=', 20)->count() : 0,
                'route' => route('admin.locks.devices.index', ['battery' => 'low']),
                'tone' => 'warning',
            ],
            [
                'label' => 'Pending payments',
                'value' => Schema::hasTable('payments') ? Payment::where('status', 'pending')->count() : 0,
                'route' => route('admin.payments.index', ['status' => 'pending']),
                'tone' => 'neutral',
            ],
            [
                'label' => 'Failed FYDA checks',
                'value' => Schema::hasTable('fyda_verifications') ? FydaVerification::where('status', 'failed')->count() : 0,
                'route' => route('admin.fyda-verifications.index', ['status' => 'failed']),
                'tone' => 'danger',
            ],
            [
                'label' => 'Active rides',
                'value' => Schema::hasTable('rides') ? Ride::where('status', 'active')->count() : 0,
                'route' => route('admin.rides.index', ['status' => 'active']),
                'tone' => 'success',
            ],
        ];
    }

    private function databaseReady(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function check(string $label, bool $ready, string $detail): array
    {
        return [
            'label' => $label,
            'detail' => $detail,
            'state' => $ready ? 'ready' : 'missing',
        ];
    }
}
