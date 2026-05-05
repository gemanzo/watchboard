<?php

use App\Models\Monitor;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function freeUserSsl(): User
{
    return User::factory()->create(['plan' => 'free']);
}

function proUserSsl(): User
{
    return User::factory()->create(['plan' => 'pro']);
}

function monitorPayload(bool $sslEnabled = true): array
{
    return [
        'name'              => 'Test',
        'url'               => 'https://example.com',
        'method'            => 'GET',
        'interval_minutes'  => 5,
        'ssl_check_enabled' => $sslEnabled,
        'ssl_expiry_alert_days' => 14,
    ];
}

// ─── Create form ──────────────────────────────────────────────────────────────

test('create form passes sslCheckAvailable=true when free user has no ssl monitors', function () {
    $user = freeUserSsl();

    $this->actingAs($user)
        ->get(route('monitors.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Create')
            ->where('sslCheckAvailable', true)
        );
});

test('create form passes sslCheckAvailable=false when free user has used ssl slot', function () {
    $user = freeUserSsl();
    Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);

    $this->actingAs($user)
        ->get(route('monitors.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Create')
            ->where('sslCheckAvailable', false)
        );
});

test('create form passes sslCheckAvailable=true for pro user regardless of ssl monitors count', function () {
    $user = proUserSsl();
    Monitor::factory()->count(5)->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);

    $this->actingAs($user)
        ->get(route('monitors.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Create')
            ->where('sslCheckAvailable', true)
        );
});

// ─── Store (create) ───────────────────────────────────────────────────────────

test('free user can create a monitor with ssl enabled when slot is available', function () {
    $user = freeUserSsl();

    $this->actingAs($user)
        ->post(route('monitors.store'), monitorPayload(sslEnabled: true))
        ->assertRedirect(route('dashboard'));

    expect($user->monitors()->where('ssl_check_enabled', true)->count())->toBe(1);
});

test('free user cannot create a second monitor with ssl enabled', function () {
    $user = freeUserSsl();
    Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);

    $this->actingAs($user)
        ->post(route('monitors.store'), monitorPayload(sslEnabled: true))
        ->assertSessionHasErrors('ssl_check_enabled');

    expect($user->monitors()->where('ssl_check_enabled', true)->count())->toBe(1);
});

test('free user can create a monitor with ssl disabled even when slot is used', function () {
    $user = freeUserSsl();
    Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);

    $this->actingAs($user)
        ->post(route('monitors.store'), monitorPayload(sslEnabled: false))
        ->assertRedirect(route('dashboard'));
});

test('pro user can create multiple monitors with ssl enabled', function () {
    $user = proUserSsl();

    foreach (range(1, 3) as $_) {
        $this->actingAs($user)
            ->post(route('monitors.store'), monitorPayload(sslEnabled: true))
            ->assertRedirect(route('dashboard'));
    }

    expect($user->monitors()->where('ssl_check_enabled', true)->count())->toBe(3);
});

// ─── Edit form ────────────────────────────────────────────────────────────────

test('edit form passes sslCheckAvailable=true when free user has ssl slot', function () {
    $user = freeUserSsl();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => false]);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Edit')
            ->where('sslCheckAvailable', true)
        );
});

test('edit form passes sslCheckAvailable=false when slot is taken by another monitor', function () {
    $user = freeUserSsl();
    Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => false]);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Edit')
            ->where('sslCheckAvailable', false)
        );
});

test('edit form passes sslCheckAvailable=true for monitor that already has ssl enabled', function () {
    $user = freeUserSsl();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Edit')
            ->where('sslCheckAvailable', true)
        );
});

// ─── Update ───────────────────────────────────────────────────────────────────

function updatePayload(Monitor $monitor, bool $sslEnabled): array
{
    return [
        'name'                 => $monitor->name ?? 'Test',
        'url'                  => $monitor->url,
        'method'               => $monitor->method,
        'interval_minutes'     => 5,
        'ssl_check_enabled'    => $sslEnabled,
        'ssl_expiry_alert_days' => $monitor->ssl_expiry_alert_days,
    ];
}

test('free user can enable ssl on a monitor when slot is available', function () {
    $user = freeUserSsl();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => false]);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), updatePayload($monitor, sslEnabled: true))
        ->assertRedirect(route('dashboard'));

    expect($monitor->fresh()->ssl_check_enabled)->toBeTrue();
});

test('free user cannot enable ssl on a second monitor when slot is taken', function () {
    $user = freeUserSsl();
    Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => false]);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), updatePayload($monitor, sslEnabled: true))
        ->assertSessionHasErrors('ssl_check_enabled');

    expect($monitor->fresh()->ssl_check_enabled)->toBeFalse();
});

test('free user can keep ssl enabled on a monitor that already has it', function () {
    $user = freeUserSsl();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), updatePayload($monitor, sslEnabled: true))
        ->assertRedirect(route('dashboard'));

    expect($monitor->fresh()->ssl_check_enabled)->toBeTrue();
});

test('free user can disable ssl on a monitor', function () {
    $user = freeUserSsl();
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'ssl_check_enabled' => true]);

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), updatePayload($monitor, sslEnabled: false))
        ->assertRedirect(route('dashboard'));

    expect($monitor->fresh()->ssl_check_enabled)->toBeFalse();
});

test('pro user can enable ssl on multiple monitors', function () {
    $user = proUserSsl();
    $monitors = Monitor::factory()->count(3)->create(['user_id' => $user->id, 'ssl_check_enabled' => false]);

    foreach ($monitors as $monitor) {
        $this->actingAs($user)
            ->put(route('monitors.update', $monitor), updatePayload($monitor, sslEnabled: true))
            ->assertRedirect(route('dashboard'));
    }

    expect($user->monitors()->where('ssl_check_enabled', true)->count())->toBe(3);
});
