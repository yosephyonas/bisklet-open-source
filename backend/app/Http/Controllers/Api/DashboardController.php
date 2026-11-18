<?php

namespace App\Http\Controllers\Api;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Bike;
use App\Models\Device;
use App\Models\FydaVerification;
use App\Models\LockCommand;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\Station;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json(Cache::remember('api.dashboard.summary', now()->addSeconds(30), function () {
            $today = Carbon::today();
            $startOfMonth = Carbon::today()->subDays(29)->startOfDay();
            $startOfWeek = Carbon::today()->subDays(6)->startOfDay();
            $staleLockCutoff = now()->subMinutes(15);
            $lowBatteryThreshold = 20;

            $avgDurationSeconds = (float) (Ride::query()
                ->where('status', RideStatus::Completed->value)
                ->whereNotNull('duration_seconds')
                ->avg('duration_seconds') ?? 0.0);

            $fydaTotal7d = FydaVerification::query()
                ->where('created_at', '>=', $startOfWeek)
                ->count();

            $fydaVerified7d = FydaVerification::query()
                ->where('created_at', '>=', $startOfWeek)
                ->where('status', 'verified')
                ->count();

            $fydaSuccessRate = $fydaTotal7d > 0
                ? round(($fydaVerified7d / $fydaTotal7d) * 100, 1)
                : null;

            $todayRevenue = Payment::query()
                ->where(function ($query) use ($today) {
                    $query->whereDate('paid_at', $today)
                        ->orWhere(fn ($sub) => $sub->whereNull('paid_at')->whereDate('created_at', $today));
                })
                ->sum('amount');

            return [
                'bikes' => [
                    'total' => Bike::count(),
                    'available' => Bike::where('status', BikeStatus::Available->value)->count(),
                    'in_ride' => Bike::where('status', BikeStatus::InRide->value)->count(),
                    'maintenance' => Bike::where('status', BikeStatus::Maintenance->value)->count(),
                ],
                'stations' => [
                    'total' => Station::count(),
                    'with_bikes' => Station::has('bikes')->count(),
                ],
                'rides' => [
                    'active' => Ride::where('status', RideStatus::Active->value)->count(),
                    'completed_today' => Ride::where('status', RideStatus::Completed->value)
                        ->whereDate('ended_at', $today)
                        ->count(),
                    'average_duration_minutes' => $avgDurationSeconds > 0
                        ? round($avgDurationSeconds / 60, 1)
                        : null,
                    'active_riders_today' => Ride::whereDate('created_at', $today)
                        ->distinct()
                        ->count('user_id'),
                ],
                'revenue' => [
                    'total' => round((float) Payment::sum('amount'), 2),
                    'today' => round((float) $todayRevenue, 2),
                    'last_30_days' => round((float) Payment::where('created_at', '>=', $startOfMonth)->sum('amount'), 2),
                    'pending' => round((float) Payment::where('status', 'pending')->sum('amount'), 2),
                    'failed_24h' => Payment::where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count(),
                ],
                'locks' => [
                    'total' => Device::count(),
                    'active' => Device::where('is_active', true)->count(),
                    'stale_15m' => Device::whereNotNull('last_seen_at')->where('last_seen_at', '<', $staleLockCutoff)->count(),
                    'never_seen' => Device::whereNull('last_seen_at')->count(),
                    'low_battery' => Device::whereNotNull('battery_percent')->where('battery_percent', '<=', $lowBatteryThreshold)->count(),
                    'commands_pending' => LockCommand::where('status', 'pending')->count(),
                    'commands_failed_24h' => LockCommand::whereIn('status', ['failed', 'timeout', 'offline'])
                        ->where('requested_at', '>=', now()->subDay())
                        ->count(),
                ],
                'fyda' => [
                    'verifications_today' => FydaVerification::whereDate('created_at', $today)->count(),
                    'success_rate_7d' => $fydaSuccessRate,
                ],
                'users' => [
                    'total' => User::count(),
                    'verified' => User::whereNotNull('fyda_verified_at')->count(),
                    'operators' => User::where('role', UserRole::Admin->value)->count(),
                ],
            ];
        }));
    }
}
