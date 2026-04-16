<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStatusPageRequest;
use App\Http\Requests\UpdateStatusPageRequest;
use App\Models\StatusPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StatusPageController extends Controller
{
    public function index(Request $request): Response
    {
        $statusPages = $request->user()
            ->statusPages()
            ->orderBy('title')
            ->get()
            ->map(fn (StatusPage $sp) => [
                'id'          => $sp->id,
                'title'       => $sp->title,
                'slug'        => $sp->slug,
                'description' => $sp->description,
                'is_active'   => $sp->is_active,
                'public_url'  => route('status-pages.public', $sp->slug),
            ]);

        return Inertia::render('StatusPages/Index', [
            'statusPages' => $statusPages,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('StatusPages/Create');
    }

    public function store(StoreStatusPageRequest $request): RedirectResponse
    {
        $request->user()->statusPages()->create($request->validated());

        return redirect()->route('status-pages.index')
            ->with('message', 'Status page creata con successo.');
    }

    public function edit(Request $request, StatusPage $statusPage): Response
    {
        Gate::authorize('update', $statusPage);

        return Inertia::render('StatusPages/Edit', [
            'statusPage' => [
                'id'          => $statusPage->id,
                'title'       => $statusPage->title,
                'slug'        => $statusPage->slug,
                'description' => $statusPage->description,
                'is_active'   => $statusPage->is_active,
            ],
        ]);
    }

    public function update(UpdateStatusPageRequest $request, StatusPage $statusPage): RedirectResponse
    {
        $statusPage->update($request->validated());

        return redirect()->route('status-pages.index')
            ->with('message', 'Status page aggiornata con successo.');
    }

    public function destroy(Request $request, StatusPage $statusPage): RedirectResponse
    {
        Gate::authorize('delete', $statusPage);

        $statusPage->delete();

        return redirect()->route('status-pages.index')
            ->with('message', 'Status page eliminata con successo.');
    }

    public function toggle(Request $request, StatusPage $statusPage): RedirectResponse
    {
        Gate::authorize('update', $statusPage);

        $statusPage->update(['is_active' => ! $statusPage->is_active]);

        return back()->with('message', $statusPage->is_active ? 'Status page attivata.' : 'Status page disattivata.');
    }

    public function publicShow(StatusPage $statusPage): Response
    {
        if (! $statusPage->is_active) {
            abort(404);
        }

        $monitors = $statusPage->user->monitors()
            ->where('is_paused', false)
            ->with('latestCheckResult')
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => [
                'name'           => $m->name ?? $m->url,
                'current_status' => $m->current_status,
                'response_time'  => $m->latestCheckResult?->response_time_ms,
            ]);

        return Inertia::render('StatusPages/PublicShow', [
            'statusPage' => [
                'title'       => $statusPage->title,
                'description' => $statusPage->description,
            ],
            'monitors' => $monitors,
        ]);
    }
}
