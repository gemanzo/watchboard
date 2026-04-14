<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonitorRequest;
use App\Models\Monitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
                'id'                   => $monitor->id,
                'name'                 => $monitor->name,
                'url'                  => $monitor->url,
                'method'               => $monitor->method,
                'interval_minutes'     => $monitor->interval_minutes,
                'current_status'       => $monitor->current_status,
                'is_paused'            => $monitor->is_paused,
                'last_status_code'     => $monitor->latestCheckResult?->status_code,
                'last_response_time_ms' => $monitor->latestCheckResult?->response_time_ms,
                'last_checked_at_human' => $monitor->latestCheckResult?->checked_at?->diffForHumans(),
            ]);

        return Inertia::render('Dashboard', [
            'monitors' => $monitors,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Monitors/Create', [
            'availableIntervals' => $request->user()->planConfig()['intervals'],
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
        ]);
    }
}
