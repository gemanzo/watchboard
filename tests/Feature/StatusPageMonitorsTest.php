<?php

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;

// ─── Configure page ──────────────────────────────────────────────────────────

test('owner can view the configure page', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('status-pages.configure', $sp))
        ->assertOk();
});

test('non-owner cannot view configure page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sp    = StatusPage::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('status-pages.configure', $sp))
        ->assertForbidden();
});

test('configure page lists user monitors', function () {
    $user    = User::factory()->create();
    $sp      = StatusPage::factory()->create(['user_id' => $user->id]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id, 'name' => 'My API']);

    $response = $this->actingAs($user)
        ->get(route('status-pages.configure', $sp));

    $response->assertOk();
    $userMonitors = $response->original->getData()['page']['props']['userMonitors'];
    expect($userMonitors)->toHaveCount(1);
    expect($userMonitors[0]['name'])->toBe('My API');
});

test('configure page shows already attached monitors', function () {
    $user    = User::factory()->create();
    $sp      = StatusPage::factory()->create(['user_id' => $user->id]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $sp->monitors()->attach($monitor->id, ['display_name' => 'Custom Name', 'sort_order' => 0]);

    $response = $this->actingAs($user)
        ->get(route('status-pages.configure', $sp));

    $attached = $response->original->getData()['page']['props']['attached'];
    expect($attached)->toHaveCount(1);
    expect($attached[0]['display_name'])->toBe('Custom Name');
});

// ─── Update monitors (sync pivot) ───────────────────────────────────────────

test('owner can attach monitors to status page', function () {
    $user    = User::factory()->create();
    $sp      = StatusPage::factory()->create(['user_id' => $user->id]);
    $mon1    = Monitor::factory()->create(['user_id' => $user->id]);
    $mon2    = Monitor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('status-pages.update-monitors', $sp), [
            'monitors' => [
                ['monitor_id' => $mon1->id, 'display_name' => 'API', 'sort_order' => 0],
                ['monitor_id' => $mon2->id, 'display_name' => null,  'sort_order' => 1],
            ],
        ])
        ->assertRedirect(route('status-pages.configure', $sp));

    expect($sp->monitors()->count())->toBe(2);

    $this->assertDatabaseHas('monitor_status_page', [
        'monitor_id'     => $mon1->id,
        'status_page_id' => $sp->id,
        'display_name'   => 'API',
        'sort_order'     => 0,
    ]);
});

test('sync removes monitors not in the request', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => false]);
    $mon1 = Monitor::factory()->create(['user_id' => $user->id]);
    $mon2 = Monitor::factory()->create(['user_id' => $user->id]);

    $sp->monitors()->attach([
        $mon1->id => ['sort_order' => 0],
        $mon2->id => ['sort_order' => 1],
    ]);

    // Keep only mon2
    $this->actingAs($user)
        ->put(route('status-pages.update-monitors', $sp), [
            'monitors' => [
                ['monitor_id' => $mon2->id, 'display_name' => null, 'sort_order' => 0],
            ],
        ])
        ->assertRedirect();

    expect($sp->monitors()->count())->toBe(1);
    $this->assertDatabaseMissing('monitor_status_page', [
        'monitor_id'     => $mon1->id,
        'status_page_id' => $sp->id,
    ]);
});

test('non-owner cannot update monitors', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sp    = StatusPage::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->put(route('status-pages.update-monitors', $sp), ['monitors' => []])
        ->assertForbidden();
});

// ─── Ownership validation ────────────────────────────────────────────────────

test('cannot attach monitors owned by another user', function () {
    $user      = User::factory()->create();
    $otherUser = User::factory()->create();
    $sp        = StatusPage::factory()->create(['user_id' => $user->id]);
    $otherMon  = Monitor::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->put(route('status-pages.update-monitors', $sp), [
            'monitors' => [
                ['monitor_id' => $otherMon->id, 'display_name' => null, 'sort_order' => 0],
            ],
        ])
        ->assertForbidden();
});

// ─── Activation guard ────────────────────────────────────────────────────────

test('cannot remove all monitors from an active status page', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $mon  = Monitor::factory()->create(['user_id' => $user->id]);
    $sp->monitors()->attach($mon->id, ['sort_order' => 0]);

    $this->actingAs($user)
        ->put(route('status-pages.update-monitors', $sp), [
            'monitors' => [],
        ])
        ->assertSessionHasErrors('monitors');

    // Monitor should still be attached
    expect($sp->monitors()->count())->toBe(1);
});

test('can remove all monitors from an inactive status page', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => false]);
    $mon  = Monitor::factory()->create(['user_id' => $user->id]);
    $sp->monitors()->attach($mon->id, ['sort_order' => 0]);

    $this->actingAs($user)
        ->put(route('status-pages.update-monitors', $sp), [
            'monitors' => [],
        ])
        ->assertRedirect();

    expect($sp->monitors()->count())->toBe(0);
});

test('cannot activate a status page with no monitors', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => false]);

    $this->actingAs($user)
        ->patch(route('status-pages.toggle', $sp))
        ->assertRedirect();

    expect($sp->fresh()->is_active)->toBeFalse();
});

test('can activate a status page that has monitors', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => false]);
    $mon  = Monitor::factory()->create(['user_id' => $user->id]);
    $sp->monitors()->attach($mon->id, ['sort_order' => 0]);

    $this->actingAs($user)
        ->patch(route('status-pages.toggle', $sp))
        ->assertRedirect();

    expect($sp->fresh()->is_active)->toBeTrue();
});

// ─── Display name on public page ─────────────────────────────────────────────

test('public page shows pivot display_name when set', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create([
        'user_id'   => $user->id,
        'is_active' => true,
    ]);
    $mon = Monitor::factory()->create([
        'user_id'        => $user->id,
        'name'           => 'Original Name',
        'current_status' => 'up',
        'is_paused'      => false,
    ]);
    $sp->monitors()->attach($mon->id, ['display_name' => 'Custom Public Name', 'sort_order' => 0]);

    $response = $this->get(route('status-pages.public', $sp->slug));

    $response->assertOk();
    $monitors = $response->original->getData()['page']['props']['monitors'];
    expect($monitors[0]['name'])->toBe('Custom Public Name');
});

test('public page falls back to monitor name when display_name is null', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create([
        'user_id'   => $user->id,
        'is_active' => true,
    ]);
    $mon = Monitor::factory()->create([
        'user_id'        => $user->id,
        'name'           => 'My API',
        'current_status' => 'up',
        'is_paused'      => false,
    ]);
    $sp->monitors()->attach($mon->id, ['display_name' => null, 'sort_order' => 0]);

    $response = $this->get(route('status-pages.public', $sp->slug));

    $monitors = $response->original->getData()['page']['props']['monitors'];
    expect($monitors[0]['name'])->toBe('My API');
});

// ─── Sort order ──────────────────────────────────────────────────────────────

test('public page respects sort_order from pivot', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create([
        'user_id'   => $user->id,
        'is_active' => true,
    ]);
    $monA = Monitor::factory()->create([
        'user_id' => $user->id, 'name' => 'Alpha', 'current_status' => 'up', 'is_paused' => false,
    ]);
    $monB = Monitor::factory()->create([
        'user_id' => $user->id, 'name' => 'Beta', 'current_status' => 'up', 'is_paused' => false,
    ]);

    // Beta first (sort_order 0), Alpha second (sort_order 1)
    $sp->monitors()->attach([
        $monB->id => ['sort_order' => 0],
        $monA->id => ['sort_order' => 1],
    ]);

    $response = $this->get(route('status-pages.public', $sp->slug));

    $monitors = $response->original->getData()['page']['props']['monitors'];
    expect($monitors[0]['name'])->toBe('Beta');
    expect($monitors[1]['name'])->toBe('Alpha');
});

test('paused monitors are excluded from public page', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create([
        'user_id'   => $user->id,
        'is_active' => true,
    ]);
    $active = Monitor::factory()->create([
        'user_id' => $user->id, 'name' => 'Active', 'current_status' => 'up', 'is_paused' => false,
    ]);
    $paused = Monitor::factory()->create([
        'user_id' => $user->id, 'name' => 'Paused', 'current_status' => 'up', 'is_paused' => true,
    ]);

    $sp->monitors()->attach([
        $active->id => ['sort_order' => 0],
        $paused->id => ['sort_order' => 1],
    ]);

    $response = $this->get(route('status-pages.public', $sp->slug));

    $monitors = $response->original->getData()['page']['props']['monitors'];
    expect($monitors)->toHaveCount(1);
    expect($monitors[0]['name'])->toBe('Active');
});
