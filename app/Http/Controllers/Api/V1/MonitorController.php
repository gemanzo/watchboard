<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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

        $plan         = $request->user()->planConfig();
        $minInterval  = (int) $plan['min_interval_minutes'];
        $maxThreshold = (int) $plan['max_confirmation_threshold'];
        $responseTimeAlertsAllowed = (bool) $plan['response_time_alerts'];

        $validated = $request->validate([
            'name'                       => ['nullable', 'string', 'max:255'],
            'url'                        => ['required', 'string', 'max:2048'],
            'check_type'                 => ['nullable', 'in:http,tcp,ping'],
            'method'                     => ['nullable', 'in:GET,HEAD'],
            'port'                       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'interval_minutes'           => ['required', 'integer', 'min:' . $minInterval],
            'confirmation_threshold'     => ['nullable', 'integer', 'min:1', 'max:' . $maxThreshold],
            'response_time_threshold_ms' => $responseTimeAlertsAllowed
                ? ['nullable', 'integer', 'min:100']
                : ['prohibited'],
            'keyword_check'              => ['nullable', 'string', 'max:255', 'required_with:keyword_check_type'],
            'keyword_check_type'         => ['nullable', 'in:contains,not_contains', 'required_with:keyword_check'],
        ]);

        $this->validateCheckTypePayload($validated);
        $this->validateCheckTypePlanLimit($request, $validated);
        $this->validateKeywordLimitOnStore($request, $validated);
        $validated = $this->normalizeCheckPayload($validated);

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

        $plan         = $request->user()->planConfig();
        $minInterval  = (int) $plan['min_interval_minutes'];
        $maxThreshold = (int) $plan['max_confirmation_threshold'];
        $responseTimeAlertsAllowed = (bool) $plan['response_time_alerts'];

        $validated = $request->validate([
            'name'                       => ['nullable', 'string', 'max:255'],
            'url'                        => ['required', 'string', 'max:2048'],
            'check_type'                 => ['nullable', 'in:http,tcp,ping'],
            'method'                     => ['nullable', 'in:GET,HEAD'],
            'port'                       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'interval_minutes'           => ['required', 'integer', 'min:' . $minInterval],
            'confirmation_threshold'     => ['nullable', 'integer', 'min:1', 'max:' . $maxThreshold],
            'response_time_threshold_ms' => $responseTimeAlertsAllowed
                ? ['nullable', 'integer', 'min:100']
                : ['prohibited'],
            'keyword_check'              => ['nullable', 'string', 'max:255', 'required_with:keyword_check_type'],
            'keyword_check_type'         => ['nullable', 'in:contains,not_contains', 'required_with:keyword_check'],
        ]);

        $this->validateCheckTypePayload($validated);
        $this->validateCheckTypePlanLimitOnUpdate($request, $monitor, $validated);
        $this->validateKeywordLimitOnUpdate($request, $monitor, $validated);
        $validated = $this->normalizeCheckPayload($validated);

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

    private function validateKeywordLimitOnStore(Request $request, array $validated): void
    {
        if (empty($validated['keyword_check'])) {
            return;
        }

        if (($validated['check_type'] ?? 'http') !== 'http') {
            throw ValidationException::withMessages([
                'keyword_check' => 'Il keyword check è disponibile solo per monitor HTTP.',
            ]);
        }

        $maxKeyword = $request->user()->planConfig()['max_keyword_monitors'] ?? null;
        if ($maxKeyword === null) {
            return;
        }

        $usedKeyword = $request->user()->monitors()->whereNotNull('keyword_check')->count();
        if ($usedKeyword >= $maxKeyword) {
            throw ValidationException::withMessages([
                'keyword_check' => 'Hai raggiunto il limite di monitor con keyword check per il tuo piano.',
            ]);
        }
    }

    private function validateKeywordLimitOnUpdate(Request $request, Monitor $monitor, array $validated): void
    {
        $isEnablingKeyword = ! empty($validated['keyword_check']) && empty($monitor->keyword_check);
        if (! $isEnablingKeyword) {
            return;
        }

        if (($validated['check_type'] ?? 'http') !== 'http') {
            throw ValidationException::withMessages([
                'keyword_check' => 'Il keyword check è disponibile solo per monitor HTTP.',
            ]);
        }

        $maxKeyword = $request->user()->planConfig()['max_keyword_monitors'] ?? null;
        if ($maxKeyword === null) {
            return;
        }

        $usedKeyword = $request->user()->monitors()
            ->whereNotNull('keyword_check')
            ->where('id', '!=', $monitor->id)
            ->count();

        if ($usedKeyword >= $maxKeyword) {
            throw ValidationException::withMessages([
                'keyword_check' => 'Hai raggiunto il limite di monitor con keyword check per il tuo piano.',
            ]);
        }
    }

    private function validateCheckTypePlanLimit(Request $request, array $validated): void
    {
        $checkType = $validated['check_type'] ?? 'http';
        $allowed   = $request->user()->planConfig()['allowed_check_types'] ?? ['http', 'ping', 'tcp'];

        if (! in_array($checkType, $allowed, true)) {
            throw ValidationException::withMessages([
                'check_type' => 'Il tipo di check selezionato non è disponibile per il tuo piano.',
            ]);
        }
    }

    private function validateCheckTypePlanLimitOnUpdate(Request $request, Monitor $monitor, array $validated): void
    {
        $checkType = $validated['check_type'] ?? 'http';

        if ($checkType === $monitor->check_type) {
            return;
        }

        $allowed = $request->user()->planConfig()['allowed_check_types'] ?? ['http', 'ping', 'tcp'];

        if (! in_array($checkType, $allowed, true)) {
            throw ValidationException::withMessages([
                'check_type' => 'Il tipo di check selezionato non è disponibile per il tuo piano.',
            ]);
        }
    }

    private function validateCheckTypePayload(array $validated): void
    {
        $checkType = $validated['check_type'] ?? 'http';

        if ($checkType === 'http') {
            if (! filter_var($validated['url'], FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages(['url' => 'The url field must be a valid URL.']);
            }

            if (! in_array($validated['method'] ?? null, ['GET', 'HEAD'], true)) {
                throw ValidationException::withMessages(['method' => 'The method field is required for HTTP checks.']);
            }
        }

        if ($checkType === 'tcp' && empty($validated['port'])) {
            throw ValidationException::withMessages(['port' => 'The port field is required for TCP checks.']);
        }
    }

    private function normalizeCheckPayload(array $payload): array
    {
        $payload['check_type'] = $payload['check_type'] ?? 'http';

        if ($payload['check_type'] !== 'http') {
            $payload['method'] = 'GET';
        }

        if ($payload['check_type'] !== 'tcp') {
            $payload['port'] = null;
        }

        return $payload;
    }
}
