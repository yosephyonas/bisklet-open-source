<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Station\StationStoreRequest;
use App\Http\Requests\Station\StationUpdateRequest;
use App\Http\Resources\StationResource;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class StationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));

        $stations = Station::query()
            ->when($request->boolean('include_bikes'), fn ($query) => $query->with('bikes'))
            ->orderBy('name')
            ->paginate($perPage);

        return StationResource::collection($stations)->response();
    }

    public function store(StationStoreRequest $request): JsonResponse
    {
        $station = Station::create($request->validated());

        if ($request->boolean('include_bikes')) {
            $station->load('bikes');
        }

        return StationResource::make($station)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Station $station): JsonResponse
    {
        if ($request->boolean('include_bikes')) {
            $station->load('bikes');
        }

        return StationResource::make($station)->response();
    }

    public function update(StationUpdateRequest $request, Station $station): JsonResponse
    {
        $station->update($request->validated());
        $station->refresh();

        if ($request->boolean('include_bikes')) {
            $station->load('bikes');
        }

        return StationResource::make($station)->response();
    }

    public function destroy(Station $station): JsonResponse
    {
        if ($station->bikes()->exists()) {
            throw ValidationException::withMessages([
                'station' => 'Station still has bikes assigned and cannot be deleted.',
            ]);
        }

        $station->delete();

        return response()->json([
            'message' => 'Station deleted.',
        ]);
    }
}
