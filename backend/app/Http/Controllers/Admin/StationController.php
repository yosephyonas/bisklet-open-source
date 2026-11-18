<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StationController extends Controller
{
    private const STATUSES = ['active', 'inactive', 'maintenance', 'offline'];

    public function index(Request $request): View
    {
        $query = Station::query()->withCount('bikes');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $stations = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.stations.index', [
            'stations' => $stations,
            'statuses' => self::STATUSES,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.stations.create', [
            'station' => new Station(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:stations,code'],
            'description' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity' => ['required', 'integer', 'min:0'],
            'available_docks' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'metadata' => ['nullable', 'json'],
        ]);

        $station = new Station();
        $station->fill([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'capacity' => $validated['capacity'],
            'available_docks' => $validated['available_docks'] ?? 0,
            'status' => $validated['status'],
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
        ]);
        $station->save();

        return redirect()
            ->route('admin.stations.show', $station)
            ->with('success', 'Station created successfully.');
    }

    public function show(Station $station): View
    {
        $station->load(['bikes' => fn ($query) => $query->orderBy('code')]);

        return view('admin.stations.show', [
            'station' => $station,
        ]);
    }

    public function edit(Station $station): View
    {
        return view('admin.stations.edit', [
            'station' => $station,
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, Station $station): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('stations', 'code')->ignore($station->id)],
            'description' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'capacity' => ['required', 'integer', 'min:0'],
            'available_docks' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'metadata' => ['nullable', 'json'],
        ]);

        $station->fill([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'capacity' => $validated['capacity'],
            'available_docks' => $validated['available_docks'] ?? 0,
            'status' => $validated['status'],
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
        ]);
        $station->save();

        return redirect()
            ->route('admin.stations.show', $station)
            ->with('success', 'Station updated successfully.');
    }

    public function destroy(Station $station): RedirectResponse
    {
        $station->delete();

        return redirect()
            ->route('admin.stations.index')
            ->with('success', 'Station removed successfully.');
    }
}
