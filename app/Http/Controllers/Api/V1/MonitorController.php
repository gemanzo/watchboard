<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MonitorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $monitors = $request->user()
            ->monitors()
            ->latest()
            ->paginate(15);

        return MonitorResource::collection($monitors);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Monitor::class);

        $minimumInterval = (int) $request->user()->planConfig()['min_interval_minutes'];

        $validated = $request->validate([
            'name'             => ['nullable', 'string', 'max:255'],
            'url'              => ['required', 'url', 'max:2048'],
            'method'           => ['required', 'in:GET,HEAD'],
            'interval_minutes' => ['required', 'integer', 'min:' . $minimumInterval],
        ]);

        $monitor = $request->user()->monitors()->create(
            $validated + ['current_status' => 'unknown']
        );

        return (new MonitorResource($monitor))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Monitor $monitor): MonitorResource
    {
        Gate::authorize('view', $monitor);

        return new MonitorResource($monitor);
    }

    public function update(Request $request, Monitor $monitor): MonitorResource
    {
        Gate::authorize('update', $monitor);

        $minimumInterval = (int) $request->user()->planConfig()['min_interval_minutes'];

        $validated = $request->validate([
            'name'             => ['nullable', 'string', 'max:255'],
            'url'              => ['required', 'url', 'max:2048'],
            'method'           => ['required', 'in:GET,HEAD'],
            'interval_minutes' => ['required', 'integer', 'min:' . $minimumInterval],
        ]);

        $monitor->update($validated);

        return new MonitorResource($monitor);
    }

    public function destroy(Request $request, Monitor $monitor): JsonResponse
    {
        Gate::authorize('delete', $monitor);

        $monitor->delete();

        return response()->json(null, 204);
    }
}
