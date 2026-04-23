<?php

use App\Models\User;

// ─── Index ────────────────────────────────────────────────────────────────────

test('guest is redirected from the api-tokens page', function () {
    $this->get(route('api-tokens.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view the api-tokens page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ApiTokens/Index'));
});

test('api-tokens page lists only the current user tokens', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $user->createToken('My token');
    $other->createToken('Other token');

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ApiTokens/Index')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'My token'),
        );
});

// ─── Store ────────────────────────────────────────────────────────────────────

test('authenticated user can create a token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => 'CI token'])
        ->assertRedirect(route('api-tokens.index'));

    expect($user->tokens()->where('name', 'CI token')->exists())->toBeTrue();
});

test('new token is flashed to the session once', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => 'Deploy key'])
        ->assertSessionHas('new_token')
        ->assertSessionHas('message');
});

test('token name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('token name must not exceed 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => str_repeat('a', 256)])
        ->assertSessionHasErrors('name');
});

test('guest cannot create a token', function () {
    $this->post(route('api-tokens.store'), ['name' => 'token'])
        ->assertRedirect(route('login'));
});

// ─── Destroy ──────────────────────────────────────────────────────────────────

test('authenticated user can revoke their own token', function () {
    $user  = User::factory()->create();
    $token = $user->createToken('To revoke');

    // The id on the PersonalAccessToken model
    $tokenId = $user->tokens()->first()->id;

    $this->actingAs($user)
        ->delete(route('api-tokens.destroy', $tokenId))
        ->assertRedirect(route('api-tokens.index'))
        ->assertSessionHas('message');

    expect($user->tokens()->count())->toBe(0);
});

test('user cannot revoke a token belonging to another user', function () {
    $owner = User::factory()->create();
    $owner->createToken('Owners token');
    $otherTokenId = $owner->tokens()->first()->id;

    $attacker = User::factory()->create();

    $this->actingAs($attacker)
        ->delete(route('api-tokens.destroy', $otherTokenId))
        ->assertNotFound();

    expect($owner->tokens()->count())->toBe(1);
});

test('revoking a non-existent token returns 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('api-tokens.destroy', 99999))
        ->assertNotFound();
});

test('guest cannot revoke a token', function () {
    $user    = User::factory()->create();
    $user->createToken('Some token');
    $tokenId = $user->tokens()->first()->id;

    $this->delete(route('api-tokens.destroy', $tokenId))
        ->assertRedirect(route('login'));
});
