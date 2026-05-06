<?php

use App\Models\Monitor;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function freeUserTcp(): User
{
    return User::factory()->create(['plan' => 'free']);
}

function proUserTcp(): User
{
    return User::factory()->create(['plan' => 'pro']);
}

function tcpPayload(array $overrides = []): array
{
    return array_merge([
        'url'              => 'db.internal.local',
        'check_type'       => 'tcp',
        'port'             => 5432,
        'interval_minutes' => 5,
    ], $overrides);
}

function pingPayload(array $overrides = []): array
{
    return array_merge([
        'url'              => '8.8.8.8',
        'check_type'       => 'ping',
        'interval_minutes' => 5,
    ], $overrides);
}

// ─── Create form ──────────────────────────────────────────────────────────────

test('create form passes allowedCheckTypes without tcp for free users', function () {
    $this->actingAs(freeUserTcp())
        ->get(route('monitors.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Create')
            ->where('allowedCheckTypes', ['http', 'ping'])
        );
});

test('create form passes allowedCheckTypes with tcp for pro users', function () {
    $this->actingAs(proUserTcp())
        ->get(route('monitors.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Create')
            ->where('allowedCheckTypes', ['http', 'ping', 'tcp'])
        );
});

// ─── Edit form ────────────────────────────────────────────────────────────────

test('edit form passes allowedCheckTypes without tcp for free users', function () {
    $user    = freeUserTcp();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'check_type' => 'http']);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Edit')
            ->where('allowedCheckTypes', ['http', 'ping'])
        );
});

test('edit form passes allowedCheckTypes with tcp for pro users', function () {
    $user    = proUserTcp();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'check_type' => 'http']);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Edit')
            ->where('allowedCheckTypes', ['http', 'ping', 'tcp'])
        );
});

// ─── Web store ────────────────────────────────────────────────────────────────

test('free user cannot create a tcp monitor', function () {
    $this->actingAs(freeUserTcp())
        ->post(route('monitors.store'), tcpPayload())
        ->assertSessionHasErrors('check_type');
});

test('free user can create a ping monitor', function () {
    $user = freeUserTcp();

    $this->actingAs($user)
        ->post(route('monitors.store'), pingPayload())
        ->assertRedirect(route('dashboard'));

    expect($user->monitors()->where('check_type', 'ping')->count())->toBe(1);
});

test('pro user can create a tcp monitor', function () {
    $user = proUserTcp();

    $this->actingAs($user)
        ->post(route('monitors.store'), tcpPayload())
        ->assertRedirect(route('dashboard'));

    expect($user->monitors()->where('check_type', 'tcp')->count())->toBe(1);
});

// ─── Web update ───────────────────────────────────────────────────────────────

function webUpdatePayload(Monitor $monitor, string $checkType, ?int $port = null): array
{
    return [
        'name'              => $monitor->name ?? 'Test',
        'url'               => $monitor->url,
        'method'            => 'GET',
        'check_type'        => $checkType,
        'port'              => $port,
        'interval_minutes'  => 5,
        'ssl_expiry_alert_days' => 14,
    ];
}

test('free user cannot switch an existing http monitor to tcp', function () {
    $user    = freeUserTcp();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'check_type' => 'http']);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), webUpdatePayload($monitor, 'tcp', 3306))
        ->assertSessionHasErrors('check_type');

    expect($monitor->fresh()->check_type)->toBe('http');
});

test('free user can keep an http monitor as http', function () {
    $user    = freeUserTcp();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'check_type' => 'http',
        'url' => 'https://example.com', 'method' => 'GET']);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), webUpdatePayload($monitor, 'http'))
        ->assertRedirect(route('dashboard'));
});

test('free user can keep a ping monitor as ping', function () {
    $user    = freeUserTcp();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'check_type' => 'ping',
        'url' => '8.8.8.8']);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), webUpdatePayload($monitor, 'ping'))
        ->assertRedirect(route('dashboard'));
});

test('pro user can switch an http monitor to tcp', function () {
    $user    = proUserTcp();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'check_type' => 'http']);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), webUpdatePayload($monitor, 'tcp', 3306))
        ->assertRedirect(route('dashboard'));

    expect($monitor->fresh()->check_type)->toBe('tcp');
});

// ─── API store ────────────────────────────────────────────────────────────────

test('free user cannot create a tcp monitor via api', function () {
    Sanctum::actingAs(freeUserTcp());

    $this->postJson('/api/v1/monitors', [
        'url'              => 'db.internal.local',
        'check_type'       => 'tcp',
        'port'             => 5432,
        'interval_minutes' => 5,
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['check_type']);
});

test('free user can create a ping monitor via api', function () {
    $user = freeUserTcp();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/monitors', [
        'url'              => '8.8.8.8',
        'check_type'       => 'ping',
        'interval_minutes' => 5,
    ])->assertStatus(201)
      ->assertJsonPath('data.attributes.check_type', 'ping');
});

test('pro user can create a tcp monitor via api', function () {
    Sanctum::actingAs(proUserTcp());

    $this->postJson('/api/v1/monitors', [
        'url'              => 'db.internal.local',
        'check_type'       => 'tcp',
        'port'             => 5432,
        'interval_minutes' => 5,
    ])->assertStatus(201)
      ->assertJsonPath('data.attributes.check_type', 'tcp');
});

// ─── API update ───────────────────────────────────────────────────────────────

test('free user cannot switch monitor to tcp via api', function () {
    $user    = freeUserTcp();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'check_type' => 'http',
        'url' => 'https://example.com', 'method' => 'GET']);
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/monitors/{$monitor->id}", [
        'url'              => 'db.internal.local',
        'check_type'       => 'tcp',
        'port'             => 3306,
        'interval_minutes' => 5,
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['check_type']);
});

test('pro user can switch monitor to tcp via api', function () {
    $user    = proUserTcp();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'check_type' => 'http',
        'url' => 'https://example.com', 'method' => 'GET']);
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/monitors/{$monitor->id}", [
        'url'              => 'db.internal.local',
        'check_type'       => 'tcp',
        'port'             => 3306,
        'interval_minutes' => 5,
    ])->assertStatus(200)
      ->assertJsonPath('data.attributes.check_type', 'tcp');
});
