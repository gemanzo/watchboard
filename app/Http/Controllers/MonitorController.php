<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonitorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'monitors' => $request->user()->monitors()->latest()->get(),
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
}
