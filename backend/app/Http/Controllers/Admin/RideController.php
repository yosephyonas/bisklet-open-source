<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RideStatus;
use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RideController extends Controller
{
    public function index(Request $request): View
    {
        $query = Ride::query()->with([
            'user:id,first_name,last_name,email',
            'bike:id,code',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user')) {
            $search = trim((string) $request->input('user'));
            if ($search !== '') {
                $query->whereHas('user', function (Builder $builder) use ($search) {
                    $builder->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('national_id', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('bike_code')) {
            $code = trim((string) $request->input('bike_code'));
            if ($code !== '') {
                $query->whereHas('bike', fn (Builder $builder) => $builder->where('code', 'like', "%{$code}%"));
            }
        }

        $rides = $query->latest('created_at')->paginate(20)->withQueryString();

        return view('admin.rides.index', [
            'rides' => $rides,
            'statuses' => RideStatus::cases(),
            'filters' => $request->only(['status', 'user', 'bike_code']),
        ]);
    }

    public function show(Ride $ride): View
    {
        $ride->load([
            'user:id,first_name,last_name,email,phone,national_id',
            'bike:id,code',
            'startStation:id,name',
            'endStation:id,name',
            'payments' => fn ($query) => $query->orderByDesc('created_at'),
        ]);

        return view('admin.rides.show', [
            'ride' => $ride,
            'statuses' => RideStatus::cases(),
            'stations' => Station::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Ride $ride): View
    {
        $ride->load(['user:id,first_name,last_name']);

        return view('admin.rides.edit', [
            'ride' => $ride,
            'statuses' => RideStatus::cases(),
            'stations' => Station::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Ride $ride): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (RideStatus $status) => $status->value, RideStatus::cases()))],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'distance_m' => ['nullable', 'numeric', 'min:0'],
            'unlock_fee' => ['nullable', 'numeric', 'min:0'],
            'per_minute_rate' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'start_station_id' => ['nullable', 'exists:stations,id'],
            'end_station_id' => ['nullable', 'exists:stations,id'],
            'start_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'end_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'end_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pricing_breakdown' => ['nullable', 'json'],
            'telemetry' => ['nullable', 'json'],
            'metadata' => ['nullable', 'json'],
        ]);

        $ride->fill([
            'status' => $validated['status'],
            'started_at' => $validated['started_at'] ?? null,
            'ended_at' => $validated['ended_at'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'distance_m' => $validated['distance_m'] ?? null,
            'unlock_fee' => $validated['unlock_fee'] ?? null,
            'per_minute_rate' => $validated['per_minute_rate'] ?? null,
            'total_amount' => $validated['total_amount'] ?? null,
            'currency' => $validated['currency'] ?? 'ETB',
            'start_station_id' => $validated['start_station_id'] ?? null,
            'end_station_id' => $validated['end_station_id'] ?? null,
            'start_latitude' => $validated['start_latitude'] ?? null,
            'start_longitude' => $validated['start_longitude'] ?? null,
            'end_latitude' => $validated['end_latitude'] ?? null,
            'end_longitude' => $validated['end_longitude'] ?? null,
            'pricing_breakdown' => isset($validated['pricing_breakdown']) ? json_decode($validated['pricing_breakdown'], true) : $ride->pricing_breakdown,
            'telemetry' => isset($validated['telemetry']) ? json_decode($validated['telemetry'], true) : $ride->telemetry,
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : $ride->metadata,
        ]);
        $ride->save();

        return redirect()
            ->route('admin.rides.show', $ride)
            ->with('success', 'Ride updated successfully.');
    }
}
