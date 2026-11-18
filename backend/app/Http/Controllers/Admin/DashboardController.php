<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Http\Controllers\Controller;
use App\Models\Bike;
use App\Models\Device;
use App\Models\FydaVerification;
use App\Models\MaintenanceLog;
use App\Models\OperationalAlert;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = Carbon::today();
        $thirtyDaysAgo = Carbon::today()->subDays(29)->startOfDay();

        $totals = [
            'users' => [
                'all' => User::count(),
                'verified' => User::whereNotNull('fyda_verified_at')->count(),
            ],
            'bikes' => [
                'all' => Bike::count(),
                'available' => Bike::where('status', BikeStatus::Available->value)->count(),
                'inRide' => Bike::where('status', BikeStatus::InRide->value)->count(),
                'maintenance' => Bike::whereIn('status', [BikeStatus::Maintenance->value, BikeStatus::Offline->value])->count(),
            ],
            'rides' => [
                'active' => Ride::where('status', RideStatus::Active->value)->count(),
                'completedToday' => Ride::where('status', RideStatus::Completed->value)
                    ->whereDate('ended_at', $today)
                    ->count(),
            ],
        ];

        $revenueSummary = [
            'total' => round((float) Payment::sum('amount'), 2),
            'today' => round(
                (float) Payment::where(function ($query) use ($today) {
                    $query->whereDate('paid_at', $today)
                        ->orWhere(fn ($sub) => $sub->whereNull('paid_at')->whereDate('created_at', $today));
                })->sum('amount'),
                2
            ),
            'last30Days' => round((float) Payment::where('created_at', '>=', $thirtyDaysAgo)->sum('amount'), 2),
        ];

        $recentRides = Ride::with([
            'user:id,first_name,last_name,email',
            'bike:id,code',
            'startStation:id,name',
            'endStation:id,name',
        ])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $activeRides = Ride::with([
            'user:id,first_name,last_name,phone,email',
            'bike:id,code',
            'startStation:id,name',
            'endStation:id,name',
        ])
            ->where('status', RideStatus::Active->value)
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->map(function ($ride) {
                $riderName = trim(($ride->user?->first_name ?? '') . ' ' . ($ride->user?->last_name ?? ''));
                if ($riderName === '') {
                    $riderName = 'Rider #' . $ride->user_id;
                }
                $bikeLabel = $ride->bike?->code ? 'Bike ' . $ride->bike->code : 'Bike -';
                $startedAt = $ride->started_at ?? $ride->created_at;
                $elapsed = $startedAt ? $startedAt->diffForHumans(null, true) : 'N/A';
                $statusValue = is_object($ride->status) ? ($ride->status->value ?? (string) $ride->status) : $ride->status;

                return [
                    'id' => $ride->id,
                    'rider_name' => $riderName,
                    'bike_label' => $bikeLabel,
                    'start_station_name' => $ride->startStation?->name,
                    'status_label' => ucfirst($statusValue),
                    'elapsed' => $elapsed,
                ];
            });

        $recentPayments = Payment::with([
            'user:id,first_name,last_name,email',
            'ride:id,user_id,bike_id,total_amount,status',
            'ride.bike:id,code',
        ])
            ->latest('created_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'totals' => $totals,
            'revenueSummary' => $revenueSummary,
            'recentRides' => $recentRides,
            'activeRides' => $activeRides,
            'recentPayments' => $recentPayments,
            'attentionItems' => $this->attentionItems(),
        ]);
    }

    private function attentionItems(): array
    {
        $openAlerts = OperationalAlert::where('status', 'open')->count();
        $offlineLocks = Device::where(function ($query) {
            $query->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subMinutes(15));
        })->count();
        $openMaintenance = MaintenanceLog::whereIn('status', ['open', 'pending', 'in_progress'])->count();
        $pendingPayments = Payment::where('status', 'pending')->count();
        $failedFyda = FydaVerification::where('status', 'failed')->count();

        return [
            [
                'label' => 'Open alerts',
                'value' => $openAlerts,
                'hint' => 'Lock and ride issues waiting for review',
                'route' => route('admin.locks.alerts'),
                'tone' => $openAlerts > 0 ? 'danger' : 'success',
            ],
            [
                'label' => 'Offline locks',
                'value' => $offlineLocks,
                'hint' => 'No heartbeat in the last 15 minutes',
                'route' => route('admin.locks.devices.index'),
                'tone' => $offlineLocks > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Maintenance queue',
                'value' => $openMaintenance,
                'hint' => 'Bikes with pending work',
                'route' => route('admin.maintenance.index', ['status' => 'open']),
                'tone' => $openMaintenance > 0 ? 'warning' : 'success',
            ],
            [
                'label' => 'Pending payments',
                'value' => $pendingPayments,
                'hint' => 'Transactions not finalized yet',
                'route' => route('admin.payments.index', ['status' => 'pending']),
                'tone' => $pendingPayments > 0 ? 'neutral' : 'success',
            ],
            [
                'label' => 'FYDA failures',
                'value' => $failedFyda,
                'hint' => 'Identity attempts needing follow-up',
                'route' => route('admin.fyda-verifications.index', ['status' => 'failed']),
                'tone' => $failedFyda > 0 ? 'danger' : 'success',
            ],
        ];
    }
}
