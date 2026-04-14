<?php

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function freeUser(): User
{
    return User::factory()->create(['plan' => 'free']);
}

function proUser(): User
{
    return User::factory()->create(['plan' => 'pro']);
}

// ─── Dashboard (index) ────────────────────────────────────────────────────────

test('authenticated user can view the dashboard', function () {
    $user = freeUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('guest is redirected from dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('user can view the create monitor form', function () {
    $this->actingAs(freeUser())
        ->get(route('monitors.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Monitors/Create'));
});

test('user can create a monitor with valid data', function () {
    $user = freeUser();

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'name'             => 'My Service',
            'url'              => 'https://example.com',
            'method'           => 'GET',
            'interval_minutes' => 5,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('message');

    expect($user->monitors)->toHaveCount(1)
        ->and($user->monitors->first()->current_status)->toBe('unknown');
});

test('store fails with invalid url', function () {
    $this->actingAs(freeUser())
        ->post(route('monitors.store'), [
            'url'              => 'not-a-url',
            'method'           => 'GET',
            'interval_minutes' => 5,
        ])
        ->assertSessionHasErrors('url');
});

test('store fails with invalid method', function () {
    $this->actingAs(freeUser())
        ->post(route('monitors.store'), [
            'url'              => 'https://example.com',
            'method'           => 'POST',
            'interval_minutes' => 5,
        ])
        ->assertSessionHasErrors('method');
});

test('store fails with interval not available on plan', function () {
    // free plan only allows interval 5
    $this->actingAs(freeUser())
        ->post(route('monitors.store'), [
            'url'              => 'https://example.com',
            'method'           => 'GET',
            'interval_minutes' => 1,
        ])
        ->assertSessionHasErrors('interval_minutes');
});

// ─── Plan limit ───────────────────────────────────────────────────────────────

test('free user cannot create a 4th monitor (plan limit)', function () {
    $user = freeUser();
    Monitor::factory()->count(3)->forUser($user)->withInterval(5)->create();

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'url'              => 'https://example.com',
            'method'           => 'GET',
            'interval_minutes' => 5,
        ])
        ->assertForbidden();
});

test('pro user can create up to 10 monitors', function () {
    $user = proUser();
    Monitor::factory()->count(9)->forUser($user)->withInterval(5)->create();

    $this->actingAs($user)
        ->post(route('monitors.store'), [
            'url'              => 'https://example.com',
            'method'           => 'GET',
            'interval_minutes' => 5,
        ])
        ->assertRedirect(route('dashboard'));

    expect($user->monitors()->count())->toBe(10);
});

// ─── Edit ─────────────────────────────────────────────────────────────────────

test('owner can view the edit form pre-populated', function () {
    $user  = freeUser();
    $monitor = Monitor::factory()->forUser($user)->withInterval(5)->create(['name' => 'Svc']);

    $this->actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Monitors/Edit')
            ->has('monitor')
            ->where('monitor.id', $monitor->id)
        );
});

test('non-owner cannot view edit form', function () {
    $monitor = Monitor::factory()->withInterval(5)->create();

    $this->actingAs(freeUser())
        ->get(route('monitors.edit', $monitor))
        ->assertForbidden();
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('owner can update a monitor', function () {
    $user    = freeUser();
    $monitor = Monitor::factory()->forUser($user)->withInterval(5)->create();

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), [
            'name'             => 'Updated Name',
            'url'              => 'https://updated.com',
            'method'           => 'HEAD',
            'interval_minutes' => 5,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('message');

    $monitor->refresh();
    expect($monitor->name)->toBe('Updated Name')
        ->and($monitor->url)->toBe('https://updated.com')
        ->and($monitor->method)->toBe('HEAD');
});

test('update fails with invalid data', function () {
    $user    = freeUser();
    $monitor = Monitor::factory()->forUser($user)->withInterval(5)->create();

    $this->actingAs($user)
        ->put(route('monitors.update', $monitor), [
            'url'              => 'bad-url',
            'method'           => 'GET',
            'interval_minutes' => 5,
        ])
        ->assertSessionHasErrors('url');
});

test('non-owner cannot update monitor', function () {
    $monitor = Monitor::factory()->withInterval(5)->create();

    $this->actingAs(freeUser())
        ->put(route('monitors.update', $monitor), [
            'url'              => 'https://example.com',
            'method'           => 'GET',
            'interval_minutes' => 5,
        ])
        ->assertForbidden();
});

// ─── Delete ───────────────────────────────────────────────────────────────────

test('owner can delete a monitor', function () {
    $user    = freeUser();
    $monitor = Monitor::factory()->forUser($user)->withInterval(5)->create();

    $this->actingAs($user)
        ->delete(route('monitors.destroy', $monitor))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('message');

    expect(Monitor::find($monitor->id))->toBeNull();
});

test('deleting a monitor also deletes its check results (cascade)', function () {
    $user    = freeUser();
    $monitor = Monitor::factory()->forUser($user)->withInterval(5)->create();

    CheckResult::create([
        'monitor_id'     => $monitor->id,
        'status_code'    => 200,
        'response_time_ms' => 120,
        'is_successful'  => true,
        'checked_at'     => now(),
    ]);

    expect(CheckResult::where('monitor_id', $monitor->id)->count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('monitors.destroy', $monitor));

    expect(CheckResult::where('monitor_id', $monitor->id)->count())->toBe(0);
});

test('non-owner cannot delete monitor', function () {
    $monitor = Monitor::factory()->withInterval(5)->create();

    $this->actingAs(freeUser())
        ->delete(route('monitors.destroy', $monitor))
        ->assertForbidden();

    expect(Monitor::find($monitor->id))->not->toBeNull();
});
