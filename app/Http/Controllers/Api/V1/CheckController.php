<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckResultResource;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CheckController extends Controller
{
    public function index(Request $request, Monitor $monitor): AnonymousResourceCollection
    {
        Gate::authorize('view', $monitor);

        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to'   => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $checks = $monitor->checkResults()
            ->when(isset($validated['from']), fn ($q) => $q->where('checked_at', '>=', $validated['from']))
            ->when(isset($validated['to']),   fn ($q) => $q->where('checked_at', '<=', \Carbon\Carbon::parse($validated['to'])->endOfDay()))
            ->orderByDesc('checked_at')
            ->paginate(50);

        return CheckResultResource::collection($checks);
    }
}
