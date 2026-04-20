<?php

use App\Models\Monitor;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ─── Auth ──────────────────────────────────────────────────────────────────────

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/v1/monitors')->assertStatus(401);
});

// ─── Index ─────────────────────────────────────────────────────────────────────

test('returns paginated list of own monitors', function () {
    $user = User::factory()->create();
    Monitor::factory()->for($user)->count(3)->create();
    Monitor::factory()->for(User::factory()->create())->count(2)->create(); // altri utenti

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/monitors')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'type', 'attributes']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'links',
        ]);
});

test('index response has correct attributes shape', function () {
    $user = User::factory()->create();
    Monitor::factory()->for($user)->create(['name' => 'My API', 'url' => 'https://example.com', 'method' => 'GET']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/monitors')->assertOk();

    $attrs = $response->json('data.0.attributes');
    expect($attrs)->toHaveKeys(['name', 'url', 'method', 'interval_minutes', 'current_status', 'is_paused', 'last_checked_at', 'created_at', 'updated_at']);
    expect($response->json('data.0.type'))->toBe('monitor');
});

test('index paginates correctly', function () {
    $user = User::factory()->create();
    Monitor::factory()->for($user)->count(20)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/monitors')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.last_page', 2);
});

// ─── Store ─────────────────────────────────────────────────────────────────────

test('can create a monitor (201)', function () {
    $user = User::factory()->create(['plan' => 'pro']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/monitors', [
        'url'              => 'https://example.com',
        'method'           => 'GET',
        'interval_minutes' => 5,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.type', 'monitor')
        ->assertJsonPath('data.attributes.url', 'https://example.com');

    expect(Monitor::where('url', 'https://example.com')->exists())->toBeTrue();
});

test('store returns 422 on invalid payload', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/monitors', [])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

test('store returns 422 when url is not a valid url', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/monitors', ['url' => 'not-a-url', 'method' => 'GET', 'interval_minutes' => 5])
        ->assertStatus(422)
        ->assertJsonPath('errors.url.0', fn ($v) => str_contains($v, 'url'));
});

test('store returns 403 when plan limit is reached', function () {
    $user = User::factory()->create(['plan' => 'free']);
    $maxMonitors = $user->planConfig()['max_monitors'];
    Monitor::factory()->for($user)->count($maxMonitors)->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/monitors', ['url' => 'https://extra.com', 'method' => 'GET', 'interval_minutes' => 5])
        ->assertStatus(403);
});

// ─── Show ──────────────────────────────────────────────────────────────────────

test('can view own monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/monitors/{$monitor->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $monitor->id)
        ->assertJsonPath('data.type', 'monitor');
});

test('show returns 403 for another users monitor', function () {
    $monitor = Monitor::factory()->for(User::factory()->create())->create();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/monitors/{$monitor->id}")->assertStatus(403);
});

test('show returns 404 for non-existent monitor', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/monitors/99999')->assertStatus(404);
});

// ─── Update ────────────────────────────────────────────────────────────────────

test('can update own monitor', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    $monitor = Monitor::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/monitors/{$monitor->id}", [
        'url'              => 'https://updated.example.com',
        'method'           => 'GET',
        'interval_minutes' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.attributes.url', 'https://updated.example.com');

    expect($monitor->fresh()->url)->toBe('https://updated.example.com');
});

test('update returns 403 for another users monitor', function () {
    $monitor = Monitor::factory()->for(User::factory()->create(['plan' => 'pro']))->create();

    Sanctum::actingAs(User::factory()->create(['plan' => 'pro']));

    $this->putJson("/api/v1/monitors/{$monitor->id}", [
        'url' => 'https://hacked.com', 'method' => 'GET', 'interval_minutes' => 5,
    ])->assertStatus(403);
});

test('update returns 422 on invalid payload', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/monitors/{$monitor->id}", ['method' => 'INVALID'])
        ->assertStatus(422);
});

// ─── Destroy ───────────────────────────────────────────────────────────────────

test('can delete own monitor (204)', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/monitors/{$monitor->id}")->assertStatus(204);

    expect(Monitor::find($monitor->id))->toBeNull();
});

test('delete returns 403 for another users monitor', function () {
    $monitor = Monitor::factory()->for(User::factory()->create())->create();

    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson("/api/v1/monitors/{$monitor->id}")->assertStatus(403);
});
