<?php

namespace App\Http\Controllers\Api;

use App\Enums\BikeStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\DamageReportResource;
use App\Models\Bike;
use App\Models\DamageReport;
use App\Models\MaintenanceLog;
use App\Models\Ride;
use App\Services\OperationalAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DamageReportController extends Controller
{
    public function __construct(
        private readonly OperationalAlertService $alerts,
    ) {
    }

    public function store(Request $request, Bike $bike): JsonResponse
    {
        $validated = $request->validate([
            'ride_id' => ['nullable', 'integer', 'exists:rides,id'],
            'category' => ['required', 'string', Rule::in([
                'brake',
                'tire',
                'lock',
                'seat',
                'battery',
                'chain',
                'frame',
                'lights',
                'other',
            ])],
            'severity' => ['required', 'string', Rule::in(['minor', 'moderate', 'major', 'critical'])],
            'description' => ['required', 'string', 'max:2000'],
            'affects_safety' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo_urls' => ['nullable', 'array', 'max:5'],
            'photo_urls.*' => ['url', 'max:2048'],
            'metadata' => ['nullable', 'array'],
        ]);

        $ride = $this->validatedRide($request, $bike, $validated['ride_id'] ?? null);
        $user = $request->user();

        $report = DB::transaction(function () use ($bike, $ride, $user, $validated) {
            $report = DamageReport::query()->create([
                'bike_id' => $bike->id,
                'ride_id' => $ride?->id,
                'user_id' => $user->id,
                'category' => $validated['category'],
                'severity' => $validated['severity'],
                'description' => $validated['description'],
                'affects_safety' => (bool) ($validated['affects_safety'] ?? false),
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'photo_urls' => $validated['photo_urls'] ?? null,
                'status' => 'open',
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $maintenance = MaintenanceLog::query()->create([
                'bike_id' => $bike->id,
                'user_id' => $user->id,
                'type' => 'damage_report:'.$validated['category'],
                'status' => 'pending',
                'notes' => $validated['description'],
                'performed_at' => now(),
                'metadata' => [
                    'damage_report_id' => $report->id,
                    'ride_id' => $ride?->id,
                    'severity' => $validated['severity'],
                    'affects_safety' => (bool) ($validated['affects_safety'] ?? false),
                    'photo_urls' => $validated['photo_urls'] ?? [],
                    'location' => [
                        'latitude' => $validated['latitude'] ?? null,
                        'longitude' => $validated['longitude'] ?? null,
                    ],
                ],
            ]);

            $report->maintenance_log_id = $maintenance->id;
            $report->save();

            if ($report->affects_safety || in_array($report->severity, ['major', 'critical'], true)) {
                $bike->status = BikeStatus::Maintenance;
                $bike->is_active = false;
                $bike->save();
            }

            return $report->fresh(['bike', 'ride', 'user']);
        });

        $this->alerts->recordDamageReport($report);

        return DamageReportResource::make($report)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function validatedRide(Request $request, Bike $bike, ?int $rideId): ?Ride
    {
        if ($rideId === null) {
            return null;
        }

        $ride = Ride::query()->findOrFail($rideId);
        $user = $request->user();

        $isAdmin = $user->role === UserRole::Admin;
        if (! $isAdmin && $ride->user_id !== $user->id) {
            abort(403, 'You cannot report damage for another rider ride.');
        }

        $matchesBike = $ride->bike_id === $bike->id
            || $ride->active_bike_id === $bike->id
            || ($bike->imei && in_array($bike->imei, [$ride->imei, $ride->active_imei], true));

        if (! $matchesBike) {
            abort(422, 'The ride does not match this bike.');
        }

        return $ride;
    }
}
