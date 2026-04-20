<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('ApiTokens/Index', [
            'tokens' => $request->user()->tokens()
                ->latest()
                ->get()
                ->map(fn ($token) => [
                    'id'           => $token->id,
                    'name'         => $token->name,
                    'last_used_at' => $token->last_used_at?->diffForHumans(),
                    'created_at'   => $token->created_at->toDateString(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()->createToken($validated['name']);

        return redirect()->route('api-tokens.index')
            ->with('new_token', $token->plainTextToken)
            ->with('message', 'Token creato con successo.');
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->findOrFail($tokenId)->delete();

        return redirect()->route('api-tokens.index')
            ->with('message', 'Token revocato.');
    }
}
