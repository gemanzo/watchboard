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
                'check_type'            => $monitor->check_type,
                'port'                  => $monitor->port,
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
        $user = $request->user();
        $plan = $user->planConfig();
        $maxSsl = $plan['max_ssl_monitors'] ?? null;
        $maxKeyword = $plan['max_keyword_monitors'] ?? null;

        $sslCheckAvailable = $maxSsl === null
            || $user->monitors()->where('ssl_check_enabled', true)->count() < $maxSsl;
        $keywordCheckAvailable = $maxKeyword === null
            || $user->monitors()->whereNotNull('keyword_check')->count() < $maxKeyword;

        return Inertia::render('Monitors/Create', [
            'availableIntervals'        => $plan['intervals'],
            'maxThreshold'              => (int) $plan['max_confirmation_threshold'],
            'responseTimeAlertsEnabled' => (bool) $plan['response_time_alerts'],
            'sslCheckAvailable'         => $sslCheckAvailable,
            'keywordCheckAvailable'     => $keywordCheckAvailable,
            'allowedCheckTypes'         => $plan['allowed_check_types'] ?? ['http', 'ping', 'tcp'],
        ]);
    }

    public function store(StoreMonitorRequest $request): RedirectResponse
    {
        $payload = $this->normalizeCheckPayload($request->validated());

        $request->user()->monitors()->create(
            $payload + ['current_status' => 'unknown']
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

        $sslCheck = $monitor->ssl_check_enabled
            ? $monitor->latestSslCheck
            : null;

        return Inertia::render('Monitors/Show', [
            'monitor' => [
                'id'               => $monitor->id,
                'name'             => $monitor->name,
                'url'              => $monitor->url,
                'method'           => $monitor->method,
                'check_type'       => $monitor->check_type,
                'port'             => $monitor->port,
                'interval_minutes' => $monitor->interval_minutes,
                'current_status'   => $monitor->current_status,
                'is_paused'        => $monitor->is_paused,
                'ssl_check_enabled' => $monitor->ssl_check_enabled,
            ],
            'uptime'     => $monitor->uptimeAll(),
            'incidents'  => $incidents,
            'sslCheck'   => $sslCheck ? [
                'issuer'            => $sslCheck->issuer,
                'valid_from'        => $sslCheck->valid_from?->toDateString(),
                'valid_to'          => $sslCheck->valid_to?->toDateString(),
                'days_until_expiry' => $sslCheck->days_until_expiry,
                'is_valid'          => $sslCheck->is_valid,
                'error'             => $sslCheck->error,
                'alert_level'       => $sslCheck->alertLevel(),
                'checked_at'        => $sslCheck->checked_at->diffForHumans(),
            ] : null,
        ]);
    }

    public function edit(Request $request, Monitor $monitor): Response
    {
        Gate::authorize('update', $monitor);

        $user = $request->user();
        $plan = $user->planConfig();
        $maxSsl = $plan['max_ssl_monitors'] ?? null;
        $maxKeyword = $plan['max_keyword_monitors'] ?? null;

        $sslCheckAvailable = $monitor->ssl_check_enabled
            || $maxSsl === null
            || $user->monitors()->where('ssl_check_enabled', true)->where('id', '!=', $monitor->id)->count() < $maxSsl;
        $keywordCheckAvailable = filled($monitor->keyword_check)
            || $maxKeyword === null
            || $user->monitors()->whereNotNull('keyword_check')->where('id', '!=', $monitor->id)->count() < $maxKeyword;

        return Inertia::render('Monitors/Edit', [
            'monitor'            => [
                'id'                     => $monitor->id,
                'name'                   => $monitor->name,
                'url'                    => $monitor->url,
                'method'                 => $monitor->method,
                'check_type'             => $monitor->check_type,
                'port'                   => $monitor->port,
                'interval_minutes'       => $monitor->interval_minutes,
                'current_status'         => $monitor->current_status,
                'is_paused'              => $monitor->is_paused,
                'confirmation_threshold'     => $monitor->confirmation_threshold,
                'response_time_threshold_ms' => $monitor->response_time_threshold_ms,
                'keyword_check'              => $monitor->keyword_check,
                'keyword_check_type'         => $monitor->keyword_check_type,
                'ssl_check_enabled'          => $monitor->ssl_check_enabled,
                'ssl_expiry_alert_days'      => $monitor->ssl_expiry_alert_days,
            ],
            'availableIntervals'        => $plan['intervals'],
            'maxThreshold'              => (int) $plan['max_confirmation_threshold'],
            'responseTimeAlertsEnabled' => (bool) $plan['response_time_alerts'],
            'sslCheckAvailable'         => $sslCheckAvailable,
            'keywordCheckAvailable'     => $keywordCheckAvailable,
            'allowedCheckTypes'         => $plan['allowed_check_types'] ?? ['http', 'ping', 'tcp'],
        ]);
    }

    public function update(UpdateMonitorRequest $request, Monitor $monitor): RedirectResponse
    {
        $monitor->update($this->normalizeCheckPayload($request->validated()));

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
