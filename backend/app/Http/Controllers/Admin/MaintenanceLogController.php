<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bike;
use App\Models\MaintenanceLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceLogController extends Controller
{
    private const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    public function index(Request $request): View
    {
        $query = MaintenanceLog::query()->with(['bike:id,code', 'user:id,first_name,last_name']);

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'open') {
                $query->whereIn('status', ['pending', 'in_progress']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('bike_id')) {
            $query->where('bike_id', $request->input('bike_id'));
        }

        $logs = $query->latest('performed_at')->paginate(20)->withQueryString();

        return view('admin.maintenance.index', [
            'logs' => $logs,
            'statuses' => array_merge(['open'], self::STATUSES),
            'bikes' => Bike::orderBy('code')->get(['id', 'code']),
            'filters' => $request->only(['status', 'bike_id']),
        ]);
    }

    public function create(): View
    {
        return view('admin.maintenance.create', [
            'log' => new MaintenanceLog(),
            'bikes' => Bike::orderBy('code')->get(['id', 'code']),
            'users' => User::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bike_id' => ['required', 'exists:bikes,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string'],
            'performed_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'json'],
        ]);

        $log = new MaintenanceLog();
        $log->fill([
            'bike_id' => $validated['bike_id'],
            'user_id' => $validated['user_id'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'performed_at' => $validated['performed_at'] ?? null,
            'cost' => $validated['cost'] ?? null,
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
        ]);
        $log->save();

        return redirect()
            ->route('admin.maintenance.show', $log)
            ->with('success', 'Maintenance log created successfully.');
    }

    public function show(MaintenanceLog $maintenance): View
    {
        $maintenance->load(['bike:id,code', 'user:id,first_name,last_name']);

        return view('admin.maintenance.show', [
            'log' => $maintenance,
            'statuses' => self::STATUSES,
        ]);
    }

    public function edit(MaintenanceLog $maintenance): View
    {
        return view('admin.maintenance.edit', [
            'log' => $maintenance,
            'bikes' => Bike::orderBy('code')->get(['id', 'code']),
            'users' => User::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, MaintenanceLog $maintenance): RedirectResponse
    {
        $validated = $request->validate([
            'bike_id' => ['required', 'exists:bikes,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string'],
            'performed_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'json'],
        ]);

        $maintenance->fill([
            'bike_id' => $validated['bike_id'],
            'user_id' => $validated['user_id'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'performed_at' => $validated['performed_at'] ?? null,
            'cost' => $validated['cost'] ?? null,
            'metadata' => isset($validated['metadata']) ? json_decode($validated['metadata'], true) : null,
        ]);
        $maintenance->save();

        return redirect()
            ->route('admin.maintenance.show', $maintenance)
            ->with('success', 'Maintenance log updated successfully.');
    }

    public function destroy(MaintenanceLog $maintenance): RedirectResponse
    {
        $maintenance->delete();

        return redirect()
            ->route('admin.maintenance.index')
            ->with('success', 'Maintenance log removed successfully.');
    }
}
