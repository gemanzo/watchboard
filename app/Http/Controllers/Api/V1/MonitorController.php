<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @tags Monitors
 */
class MonitorController extends Controller
{
    /**
     * List monitors
     *
     * Returns a paginated list of all monitors belonging to the authenticated user,
     * ordered by creation date descending (newest first).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $monitors = $request->user()
            ->monitors()
            ->latest()
            ->paginate(15);

        return MonitorResource::collection($monitors);
    }

    /**
     * Create a monitor
     *
     * Creates a new HTTP monitor for the authenticated user.
     * Returns `403` if the user has reached the monitor limit for their plan.
     *
     * @response 201 scenario="Created" {"data":{"id":42,"type":"monitor","attributes":{"name":"My API","url":"https://api.example.com","method":"GET","interval_minutes":5,"current_status":"unknown","is_paused":false,"last_checked_at":null,"created_at":"2026-04-20T10:00:00+00:00","updated_at":"2026-04-20T10:00:00+00:00"}}}
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Monitor::class);

        $minimumInterval = (int) $request->user()->planConfig()['min_interval_minutes'];

        $maxThreshold = (int) $request->user()->planConfig()['max_confirmation_threshold'];

        $validated = $request->validate([
            'name'                   => ['nullable', 'string', 'max:255'],
            'url'                    => ['required', 'url', 'max:2048'],
            'method'                 => ['required', 'in:GET,HEAD'],
            'interval_minutes'       => ['required', 'integer', 'min:' . $minimumInterval],
            'confirmation_threshold' => ['nullable', 'integer', 'min:1', 'max:' . $maxThreshold],
        ]);

        $monitor = $request->user()->monitors()->create(
            $validated + ['current_status' => 'unknown']
        );

        return (new MonitorResource($monitor))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a monitor
     *
     * Returns the details of a single monitor.
     * Returns `403` if the monitor belongs to a different user.
     */
    public function show(Request $request, Monitor $monitor): MonitorResource
    {
        Gate::authorize('view', $monitor);

        return new MonitorResource($monitor);
    }

    /**
     * Update a monitor
     *
     * Replaces all editable fields of an existing monitor.
     * Returns `403` if the monitor belongs to a different user.
     */
    public function update(Request $request, Monitor $monitor): MonitorResource
    {
        Gate::authorize('update', $monitor);

        $minimumInterval = (int) $request->user()->planConfig()['min_interval_minutes'];

        $maxThreshold = (int) $request->user()->planConfig()['max_confirmation_threshold'];

        $validated = $request->validate([
            'name'                   => ['nullable', 'string', 'max:255'],
            'url'                    => ['required', 'url', 'max:2048'],
            'method'                 => ['required', 'in:GET,HEAD'],
            'interval_minutes'       => ['required', 'integer', 'min:' . $minimumInterval],
            'confirmation_threshold' => ['nullable', 'integer', 'min:1', 'max:' . $maxThreshold],
        ]);

        $monitor->update($validated);

        return new MonitorResource($monitor);
    }

    /**
     * Delete a monitor
     *
     * Permanently deletes a monitor and all its associated check results.
     * Returns an empty body with status `204` on success.
     * Returns `403` if the monitor belongs to a different user.
     *
     * @response 204 scenario="Deleted" {}
     */
    public function destroy(Request $request, Monitor $monitor): JsonResponse
    {
        Gate::authorize('delete', $monitor);

        $monitor->delete();

        return response()->json(null, 204);
    }
}
