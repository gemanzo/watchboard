<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonitorRequest;
use App\Http\Requests\UpdateMonitorRequest;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    public function index(Request $request): Response
    {
        $monitors = $request->user()
            ->monitors()
            ->with('latestCheckResult')
            ->orderByRaw("CASE current_status WHEN 'down' THEN 0 WHEN 'unknown' THEN 1 WHEN 'up' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->get()
            ->map(fn (Monitor $monitor) => [
                'id'                    => $monitor->id,
                'name'                  => $monitor->name,
                'url'                   => $monitor->url,
                'method'                => $monitor->method,
                'interval_minutes'      => $monitor->interval_minutes,
                'current_status'        => $monitor->current_status,
                'is_paused'             => $monitor->is_paused,
                'last_status_code'      => $monitor->latestCheckResult?->status_code,
                'last_response_time_ms' => $monitor->latestCheckResult?->response_time_ms,
                'last_checked_at_human' => $monitor->latestCheckResult?->checked_at?->diffForHumans(),
                'uptime_24h'            => $monitor->uptimePercentage('24h'),
            ]);

        return Inertia::render('Dashboard', [
            'monitors' => $monitors,
        ]);
    }

    public function create(Request $request): Response
    {
        $plan = $request->user()->planConfig();

        return Inertia::render('Monitors/Create', [
            'availableIntervals'         => $plan['intervals'],
            'maxThreshold'               => (int) $plan['max_confirmation_threshold'],
            'responseTimeAlertsEnabled'  => (bool) $plan['response_time_alerts'],
        ]);
    }

    public function store(StoreMonitorRequest $request): RedirectResponse
    {
        $request->user()->monitors()->create(
            $request->validated() + ['current_status' => 'unknown']
        );

        return redirect()->route('dashboard')
            ->with('message', 'Monitor creato con successo.');
    }

    public function show(Request $request, Monitor $monitor): Response
    {
        Gate::authorize('view', $monitor);

        $incidents = $monitor->incidents()
            ->latest('started_at')
            ->limit(20)
            ->get()
            ->map(fn ($incident) => [
                'id'               => $incident->id,
                'started_at'       => $incident->started_at->toIso8601String(),
                'resolved_at'      => $incident->resolved_at?->toIso8601String(),
                'duration_seconds' => $incident->duration_seconds,
            ]);

        return Inertia::render('Monitors/Show', [
            'monitor' => [
                'id'               => $monitor->id,
                'name'             => $monitor->name,
                'url'              => $monitor->url,
                'method'           => $monitor->method,
                'interval_minutes' => $monitor->interval_minutes,
                'current_status'   => $monitor->current_status,
                'is_paused'        => $monitor->is_paused,
            ],
            'uptime'     => $monitor->uptimeAll(),
            'incidents'  => $incidents,
        ]);
    }

    public function edit(Request $request, Monitor $monitor): Response
    {
        Gate::authorize('update', $monitor);

        $plan = $request->user()->planConfig();

        return Inertia::render('Monitors/Edit', [
            'monitor'            => [
                'id'                     => $monitor->id,
                'name'                   => $monitor->name,
                'url'                    => $monitor->url,
                'method'                 => $monitor->method,
                'interval_minutes'       => $monitor->interval_minutes,
                'current_status'         => $monitor->current_status,
                'is_paused'              => $monitor->is_paused,
                'confirmation_threshold'     => $monitor->confirmation_threshold,
                'response_time_threshold_ms' => $monitor->response_time_threshold_ms,
            ],
            'availableIntervals'        => $plan['intervals'],
            'maxThreshold'              => (int) $plan['max_confirmation_threshold'],
            'responseTimeAlertsEnabled' => (bool) $plan['response_time_alerts'],
        ]);
    }

    public function update(UpdateMonitorRequest $request, Monitor $monitor): RedirectResponse
    {
        $monitor->update($request->validated());

        return redirect()->route('dashboard')
            ->with('message', 'Monitor aggiornato con successo.');
    }

    public function destroy(Request $request, Monitor $monitor): RedirectResponse
    {
        Gate::authorize('delete', $monitor);

        $monitor->delete();

        return redirect()->route('dashboard')
            ->with('message', 'Monitor eliminato con successo.');
    }

    public function togglePause(Request $request, Monitor $monitor): RedirectResponse
    {
        Gate::authorize('update', $monitor);

        $monitor->update(['is_paused' => ! $monitor->is_paused]);

        $message = $monitor->is_paused ? 'Monitor messo in pausa.' : 'Monitor ripreso.';

        return redirect()->back()->with('message', $message);
    }

    public function metrics(Request $request, Monitor $monitor): JsonResponse
    {
        Gate::authorize('view', $monitor);

        $validated = $request->validate([
            'range' => ['sometimes', Rule::in(['24h', '7d', '30d'])],
        ]);

        $range = $validated['range'] ?? '24h';

        $since = match ($range) {
            '24h' => now()->subHours(24),
            '7d'  => now()->subDays(7),
            '30d' => now()->subDays(30),
        };

        $results = $monitor->checkResults()
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at')
            ->get(['response_time_ms', 'checked_at']);

        $data = $results
            ->groupBy(function ($r) {
                $bucket = intdiv($r->checked_at->minute, 15) * 15;

                return $r->checked_at->copy()
                    ->setTime($r->checked_at->hour, $bucket, 0)
                    ->toIso8601String();
            })
            ->map(fn ($group, $key) => [
                'timestamp'            => $key,
                'avg_response_time_ms' => (int) round($group->avg('response_time_ms')),
                'check_count'          => $group->count(),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }
}
