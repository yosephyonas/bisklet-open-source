<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BikeStatus;
use App\Http\Controllers\Controller;
use App\Models\Bike;
use App\Models\Device;
use App\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BikeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Bike::query()->with(['station:id,name', 'device']);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('imei', 'like', "%{$search}%")
                    ->orWhereHas('device', function (Builder $deviceQuery) use ($search) {
                        $deviceQuery->where('lock_code', 'like', "%{$search}%");
                    });
            });
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $battery = trim((string) $request->input('battery', ''));
        if ($battery === 'low') {
            $query->whereNotNull('battery_level')
                ->where('battery_level', '<=', 20);
        } elseif ($battery === 'healthy') {
            $query->whereNotNull('battery_level')
                ->where('battery_level', '>', 20);
        } elseif ($battery === 'unknown') {
            $query->whereNull('battery_level');
        }

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        $bikes = $query->orderBy('code')->paginate(15)->withQueryString();

        return view('admin.bikes.index', [
            'bikes' => $bikes,
            'stations' => Station::orderBy('name')->get(['id', 'name']),
            'statuses' => BikeStatus::cases(),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'battery' => $battery,
                'station_id' => $request->input('station_id'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.bikes.create', [
            'bike' => new Bike,
            'stations' => Station::orderBy('name')->get(['id', 'name']),
            'statuses' => BikeStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'station_id' => ['nullable', 'exists:stations,id'],
            'code' => ['required', 'string', 'max:50', 'unique:bikes,code'],
            'lock_code' => ['nullable', 'string', 'max:64'],
            'imei' => ['nullable', 'string', 'max:20'],
            'model' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_map(fn (BikeStatus $status) => $status->value, BikeStatus::cases()))],
            'is_active' => ['nullable', 'boolean'],
            'battery_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
            'price_per_minute' => ['nullable', 'numeric', 'min:0'],
            'unlock_fee' => ['nullable', 'numeric', 'min:0'],
            'last_service_at' => ['nullable', 'date'],
            'hardware' => ['nullable', 'json'],
            'metadata' => ['nullable', 'json'],
        ]);

        $imei = $this->resolveBikeImei($request);

        $bike = new Bike;
        $bike->fill([
            'station_id' => $validated['station_id'] ?? null,
            'code' => $validated['code'],
            'imei' => $imei,
            'model' => $validated['model'] ?? null,
            'type' => $validated['type'] ?? 'standard',
            'status' => $validated['status'],
            'is_active' => $request->boolean('is_active'),
            'battery_level' => $validated['battery_level'] ?? null,
            'odometer_km' => $validated['odometer_km'] ?? 0,
            'price_per_minute' => config('pricing.rides.per_minute_rate', 0.5),
            'unlock_fee' => config('pricing.rides.unlock_fee', 5),
            'last_service_at' => $validated['last_service_at'] ?? null,
            'hardware' => isset($validated['hardware']) ? json_decode($validated['hardware'], true) : null,
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
        ]);
        $bike->save();

        return redirect()
            ->route('admin.bikes.show', $bike)
            ->with('success', 'Bike created successfully.');
    }

    public function show(Bike $bike): View
    {
        $bike->load([
            'station:id,name',
            'device',
            'maintenanceLogs' => fn ($query) => $query->latest('performed_at')->limit(5),
            'rides' => fn ($query) => $query->latest('created_at')->limit(5)->with('user:id,first_name,last_name'),
        ]);

        return view('admin.bikes.show', [
            'bike' => $bike,
        ]);
    }

    public function edit(Bike $bike): View
    {
        return view('admin.bikes.edit', [
            'bike' => $bike,
            'stations' => Station::orderBy('name')->get(['id', 'name']),
            'statuses' => BikeStatus::cases(),
        ]);
    }

    public function update(Request $request, Bike $bike): RedirectResponse
    {
        $validated = $request->validate([
            'station_id' => ['nullable', 'exists:stations,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('bikes', 'code')->ignore($bike->id)],
            'lock_code' => ['nullable', 'string', 'max:64'],
            'imei' => ['nullable', 'string', 'max:20'],
            'model' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_map(fn (BikeStatus $status) => $status->value, BikeStatus::cases()))],
            'is_active' => ['nullable', 'boolean'],
            'battery_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
            'price_per_minute' => ['nullable', 'numeric', 'min:0'],
            'unlock_fee' => ['nullable', 'numeric', 'min:0'],
            'last_service_at' => ['nullable', 'date'],
            'hardware' => ['nullable', 'json'],
            'metadata' => ['nullable', 'json'],
        ]);

        $imei = $this->resolveBikeImei($request, $bike);

        $bike->fill([
            'station_id' => $validated['station_id'] ?? null,
            'code' => $validated['code'],
            'imei' => $imei,
            'model' => $validated['model'] ?? null,
            'type' => $validated['type'] ?? 'standard',
            'status' => $validated['status'],
            'is_active' => $request->boolean('is_active'),
            'battery_level' => $validated['battery_level'] ?? null,
            'odometer_km' => $validated['odometer_km'] ?? 0,
            'price_per_minute' => config('pricing.rides.per_minute_rate', 0.5),
            'unlock_fee' => config('pricing.rides.unlock_fee', 5),
            'last_service_at' => $validated['last_service_at'] ?? null,
            'hardware' => isset($validated['hardware']) ? json_decode($validated['hardware'], true) : null,
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
        ]);
        $bike->save();

        return redirect()
            ->route('admin.bikes.show', $bike)
            ->with('success', 'Bike updated successfully.');
    }

    public function destroy(Bike $bike): RedirectResponse
    {
        $bike->delete();

        return redirect()
            ->route('admin.bikes.index')
            ->with('success', 'Bike removed successfully.');
    }

    public function lockLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lock_code' => ['required', 'string', 'max:64'],
            'bike_id' => ['nullable', 'integer'],
        ]);

        $device = Device::query()->where('lock_code', $validated['lock_code'])->first();

        if (! $device) {
            return response()->json([
                'message' => 'Lock code not found.',
            ], 404);
        }

        $assignedQuery = Bike::query()->where('imei', $device->imei);
        if (! empty($validated['bike_id'])) {
            $assignedQuery->where('id', '!=', $validated['bike_id']);
        }

        $assignedBike = $assignedQuery->first();

        return response()->json([
            'imei' => $device->imei,
            'lock_code' => $device->lock_code,
            'assigned' => (bool) $assignedBike,
            'assigned_bike' => $assignedBike ? [
                'id' => $assignedBike->id,
                'code' => $assignedBike->code,
            ] : null,
        ]);
    }

    private function resolveBikeImei(Request $request, ?Bike $bike = null): ?string
    {
        $lockCode = trim((string) $request->input('lock_code', ''));
        $imeiInput = trim((string) $request->input('imei', ''));
        $imei = $imeiInput !== '' ? $imeiInput : null;

        if ($lockCode !== '') {
            $device = Device::query()->where('lock_code', $lockCode)->first();

            if (! $device) {
                throw ValidationException::withMessages([
                    'lock_code' => 'Lock code not found.',
                ]);
            }

            $imei = $device->imei;
        } elseif ($imei !== null) {
            $device = Device::query()->where('imei', $imei)->first();

            if (! $device) {
                throw ValidationException::withMessages([
                    'imei' => 'IMEI not found.',
                ]);
            }
        }

        if ($imei !== null) {
            $existing = Bike::query()
                ->where('imei', $imei)
                ->when($bike, fn ($query) => $query->where('id', '!=', $bike->id))
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'lock_code' => 'This lock is already assigned to bike '.$existing->code.'.',
                ]);
            }
        }

        return $imei;
    }
}
