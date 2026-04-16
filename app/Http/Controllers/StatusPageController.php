<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStatusPageRequest;
use App\Http\Requests\UpdateStatusPageRequest;
use App\Models\StatusPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        $wantsActive = ! $statusPage->is_active;

        if ($wantsActive && $statusPage->monitors()->count() === 0) {
            return back()->withErrors(['monitors' => 'Aggiungi almeno un monitor prima di attivare la status page.']);
        }

        $statusPage->update(['is_active' => $wantsActive]);

        return back()->with('message', $statusPage->is_active ? 'Status page attivata.' : 'Status page disattivata.');
    }

    public function configure(Request $request, StatusPage $statusPage): Response
    {
        Gate::authorize('update', $statusPage);

        $userMonitors = $request->user()
            ->monitors()
            ->orderBy('name')
            ->get(['id', 'name', 'url']);

        $attached = $statusPage->monitors()
            ->get()
            ->map(fn ($m) => [
                'monitor_id'   => $m->id,
                'display_name' => $m->pivot->display_name,
                'sort_order'   => $m->pivot->sort_order,
            ]);

        return Inertia::render('StatusPages/Configure', [
            'statusPage'   => [
                'id'    => $statusPage->id,
                'title' => $statusPage->title,
            ],
            'userMonitors' => $userMonitors,
            'attached'     => $attached,
        ]);
    }

    public function updateMonitors(Request $request, StatusPage $statusPage): RedirectResponse
    {
        Gate::authorize('update', $statusPage);

        $validated = $request->validate([
            'monitors'                => ['present', 'array'],
            'monitors.*.monitor_id'   => ['required', 'integer', 'exists:monitors,id'],
            'monitors.*.display_name' => ['nullable', 'string', 'max:255'],
            'monitors.*.sort_order'   => ['required', 'integer', 'min:0'],
        ]);

        // Verify all monitors belong to the user
        $userMonitorIds = $request->user()->monitors()->pluck('id')->toArray();
        $requestedIds   = collect($validated['monitors'])->pluck('monitor_id')->toArray();

        if (array_diff($requestedIds, $userMonitorIds)) {
            abort(403, 'Cannot attach monitors you do not own.');
        }

        // If page is active, require at least 1 monitor
        if ($statusPage->is_active && empty($validated['monitors'])) {
            throw ValidationException::withMessages([
                'monitors' => 'Una status page attiva deve avere almeno un monitor.',
            ]);
        }

        // Sync pivot
        $syncData = [];
        foreach ($validated['monitors'] as $entry) {
            $syncData[$entry['monitor_id']] = [
                'display_name' => $entry['display_name'],
                'sort_order'   => $entry['sort_order'],
            ];
        }

        $statusPage->monitors()->sync($syncData);

        return redirect()->route('status-pages.configure', $statusPage)
            ->with('message', 'Monitor aggiornati con successo.');
    }

    public function publicShow(StatusPage $statusPage): Response
    {
        if (! $statusPage->is_active) {
            abort(404);
        }

        $monitors = $statusPage->monitors()
            ->where('is_paused', false)
            ->with('latestCheckResult')
            ->get()
            ->map(fn ($m) => [
                'name'           => $m->pivot->display_name ?? $m->name ?? $m->url,
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
