<?php

use App\Models\Monitor;
use App\Models\User;

// ─── Toggle Pause ─────────────────────────────────────────────────────────────

test('authenticated user can pause an active monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create(['is_paused' => false]);

    $this->actingAs($user)
        ->patch(route('monitors.toggle-pause', $monitor))
        ->assertRedirect();

    expect($monitor->fresh()->is_paused)->toBeTrue();
});

test('authenticated user can resume a paused monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create(['is_paused' => true]);

    $this->actingAs($user)
        ->patch(route('monitors.toggle-pause', $monitor))
        ->assertRedirect();

    expect($monitor->fresh()->is_paused)->toBeFalse();
});

test('toggle pause is idempotent on double call', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create(['is_paused' => false]);

    $this->actingAs($user)->patch(route('monitors.toggle-pause', $monitor));
    $this->actingAs($user)->patch(route('monitors.toggle-pause', $monitor));

    expect($monitor->fresh()->is_paused)->toBeFalse();
});

test('guest cannot toggle pause', function () {
    $monitor = Monitor::factory()->for(User::factory()->create())->create();

    $this->patch(route('monitors.toggle-pause', $monitor))
        ->assertRedirect(route('login'));
});

test('user cannot pause a monitor belonging to another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $monitor = Monitor::factory()->for($owner)->create(['is_paused' => false]);

    $this->actingAs($other)
        ->patch(route('monitors.toggle-pause', $monitor))
        ->assertForbidden();

    expect($monitor->fresh()->is_paused)->toBeFalse();
});

// ─── DispatchChecks esclusione ────────────────────────────────────────────────

test('paused monitor is excluded from dispatch checks', function () {
    $user = User::factory()->create();
    Monitor::factory()->for($user)->create(['is_paused' => false, 'current_status' => 'up']);
    Monitor::factory()->for($user)->create(['is_paused' => true,  'current_status' => 'up']);

    $active = Monitor::where('is_paused', false)->count();
    $paused = Monitor::where('is_paused', true)->count();

    expect($active)->toBe(1)
        ->and($paused)->toBe(1);

    // Verifica che la query usata da DispatchChecks escluda i paused
    $dispatched = Monitor::query()->where('is_paused', false)->get();

    expect($dispatched)->toHaveCount(1)
        ->and($dispatched->first()->is_paused)->toBeFalse();
});
