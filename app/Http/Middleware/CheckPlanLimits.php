<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimits
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $configKey = match ($resource) {
            'monitors' => 'max_monitors',
            'status-pages' => 'max_status_pages',
            default => null,
        };

        if ($configKey === null) {
            abort(500, "Unknown plan limited resource [{$resource}].");
        }

        $maxAllowed = $user->planConfig()[$configKey] ?? null;

        // Null means unlimited for the current plan.
        if ($maxAllowed === null) {
            return $next($request);
        }

        $relationship = match ($resource) {
            'monitors' => 'monitors',
            'status-pages' => 'statusPages',
        };

        if (! method_exists($user, $relationship)) {
            return $next($request);
        }

        if ($user->{$relationship}()->count() >= $maxAllowed) {
            abort(403, 'Plan limit reached for this feature.');
        }

        return $next($request);
    }
}
