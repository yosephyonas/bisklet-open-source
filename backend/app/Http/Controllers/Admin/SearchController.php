<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bike;
use App\Models\FydaVerification;
use App\Models\MaintenanceLog;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->input('q', ''));
        $searchNumber = $query !== '' && ctype_digit($query) ? (int) $query : null;

        $users = collect();
        $usersCount = 0;
        $bikes = collect();
        $bikesCount = 0;
        $stations = collect();
        $stationsCount = 0;
        $rides = collect();
        $ridesCount = 0;
        $payments = collect();
        $paymentsCount = 0;
        $maintenanceLogs = collect();
        $maintenanceCount = 0;
        $fydaVerifications = collect();
        $fydaCount = 0;

        if ($query !== '') {
            $userFilter = function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $inner) use ($query): void {
                    $inner
                        ->where('first_name', 'like', "%{$query}%")
                        ->orWhere('last_name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('national_id', 'like', "%{$query}%");
                });
            };

            $usersQuery = User::query();
            $userFilter($usersQuery);
            $usersCount = (clone $usersQuery)->count();
            $users = $usersQuery->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'national_id', 'role']);

            $bikeFilter = function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $inner) use ($query): void {
                    $inner
                        ->where('code', 'like', "%{$query}%")
                        ->orWhere('model', 'like', "%{$query}%")
                        ->orWhere('type', 'like', "%{$query}%");
                });
            };

            $bikesQuery = Bike::query()->with('station:id,name');
            $bikeFilter($bikesQuery);
            $bikesCount = (clone $bikesQuery)->toBase()->count();
            $bikes = $bikesQuery->orderBy('code')
                ->limit(5)
                ->get(['id', 'code', 'model', 'type', 'status', 'station_id', 'battery_level']);

            $stationFilter = function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $inner) use ($query): void {
                    $inner
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                });
            };

            $stationsQuery = Station::query();
            $stationFilter($stationsQuery);
            $stationsCount = (clone $stationsQuery)->count();
            $stations = $stationsQuery->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'code', 'status']);

            $rideFilter = function (Builder $builder) use ($query, $searchNumber): void {
                $builder->where(function (Builder $inner) use ($query, $searchNumber): void {
                    if ($searchNumber !== null) {
                        $inner->orWhere('id', $searchNumber);
                    }

                    $inner
                        ->orWhere('status', 'like', "%{$query}%")
                        ->orWhereHas('bike', fn (Builder $bike) => $bike->where('code', 'like', "%{$query}%"))
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%"));
                });
            };

            $ridesQuery = Ride::query();
            $rideFilter($ridesQuery);
            $ridesCount = (clone $ridesQuery)->count();
            $rides = $ridesQuery->with(['user:id,first_name,last_name', 'bike:id,code'])
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'user_id', 'bike_id', 'status', 'created_at', 'started_at', 'ended_at', 'total_amount']);

            $paymentFilter = function (Builder $builder) use ($query, $searchNumber): void {
                $builder->where(function (Builder $inner) use ($query, $searchNumber): void {
                    if ($searchNumber !== null) {
                        $inner->orWhere('id', $searchNumber);
                        $inner->orWhereHas('ride', fn (Builder $ride) => $ride->where('id', $searchNumber));
                    }

                    $inner
                        ->orWhere('provider', 'like', "%{$query}%")
                        ->orWhere('provider_reference', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%")
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%"));
                });
            };

            $paymentsQuery = Payment::query();
            $paymentFilter($paymentsQuery);
            $paymentsCount = (clone $paymentsQuery)->count();
            $payments = $paymentsQuery->with(['user:id,first_name,last_name', 'ride:id,bike_id'])
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'ride_id', 'user_id', 'provider', 'status', 'amount', 'created_at', 'paid_at']);

            $maintenanceFilter = function (Builder $builder) use ($query, $searchNumber): void {
                $builder->where(function (Builder $inner) use ($query, $searchNumber): void {
                    if ($searchNumber !== null) {
                        $inner->orWhere('id', $searchNumber);
                    }

                    $inner
                        ->orWhere('type', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%")
                        ->orWhereHas('bike', fn (Builder $bike) => $bike->where('code', 'like', "%{$query}%"))
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%"));
                });
            };

            $maintenanceQuery = MaintenanceLog::query();
            $maintenanceFilter($maintenanceQuery);
            $maintenanceCount = (clone $maintenanceQuery)->count();
            $maintenanceLogs = $maintenanceQuery->with(['bike:id,code', 'user:id,first_name,last_name'])
                ->latest('performed_at')
                ->limit(5)
                ->get(['id', 'bike_id', 'user_id', 'type', 'status', 'performed_at', 'created_at']);

            $fydaFilter = function (Builder $builder) use ($query, $searchNumber): void {
                $builder->where(function (Builder $inner) use ($query, $searchNumber): void {
                    if ($searchNumber !== null) {
                        $inner->orWhere('id', $searchNumber);
                    }

                    $inner
                        ->orWhere('national_id', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%")
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%"));
                });
            };

            $fydaQuery = FydaVerification::query();
            $fydaFilter($fydaQuery);
            $fydaCount = (clone $fydaQuery)->count();
            $fydaVerifications = $fydaQuery->with('user:id,first_name,last_name')
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'user_id', 'national_id', 'status', 'created_at']);
        }

        return view('admin.search', [
            'query' => $query,
            'users' => $users,
            'usersCount' => $usersCount,
            'bikes' => $bikes,
            'bikesCount' => $bikesCount,
            'stations' => $stations,
            'stationsCount' => $stationsCount,
            'rides' => $rides,
            'ridesCount' => $ridesCount,
            'payments' => $payments,
            'paymentsCount' => $paymentsCount,
            'maintenanceLogs' => $maintenanceLogs,
            'maintenanceCount' => $maintenanceCount,
            'fydaVerifications' => $fydaVerifications,
            'fydaCount' => $fydaCount,
        ]);
    }
}
