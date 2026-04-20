<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckResultResource;
use App\Models\Monitor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @tags Checks
 */
class CheckController extends Controller
{
    /**
     * List check results
     *
     * Returns a paginated list of check results for the given monitor,
     * ordered from most recent to oldest (50 per page).
     *
     * Use the `from` and `to` query parameters to filter by date range.
     * Both accept any date format parseable by PHP (e.g. `2026-01-15`, `2026-01-15T08:00:00`).
     * The `to` date is **inclusive** (results up to end of that day are included).
     *
     * Returns `403` if the monitor belongs to a different user.
     *
     * @queryParam from string Filter results from this date (inclusive). Example: 2026-01-01
     * @queryParam to string Filter results up to this date (inclusive, end of day). Example: 2026-01-31
     */
    public function index(Request $request, Monitor $monitor): AnonymousResourceCollection
    {
        Gate::authorize('view', $monitor);

        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to'   => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $checks = $monitor->checkResults()
            ->when(isset($validated['from']), fn ($q) => $q->where('checked_at', '>=', $validated['from']))
            ->when(isset($validated['to']),   fn ($q) => $q->where('checked_at', '<=', Carbon::parse($validated['to'])->endOfDay()))
            ->orderByDesc('checked_at')
            ->paginate(50);

        return CheckResultResource::collection($checks);
    }
}
