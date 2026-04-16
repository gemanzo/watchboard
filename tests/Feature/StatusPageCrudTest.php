<?php

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;

// ─── Index ────────────────────────────────────────────────────────────────────

test('authenticated user can view status pages index', function () {
    $user = User::factory()->create();
    StatusPage::factory()->create(['user_id' => $user->id, 'title' => 'My Page']);

    $this->actingAs($user)
        ->get(route('status-pages.index'))
        ->assertOk()
        ->assertSee('My Page');
});

test('guest is redirected from status pages index', function () {
    $this->get(route('status-pages.index'))
        ->assertRedirect(route('login'));
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('user can view the create status page form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('status-pages.create'))
        ->assertOk();
});

test('user can create a status page with valid data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('status-pages.store'), [
            'title'       => 'Acme Status',
            'slug'        => 'acme-status',
            'description' => 'Stato dei servizi Acme',
        ])
        ->assertRedirect(route('status-pages.index'));

    $this->assertDatabaseHas('status_pages', [
        'user_id'     => $user->id,
        'title'       => 'Acme Status',
        'slug'        => 'acme-status',
        'description' => 'Stato dei servizi Acme',
        'is_active'   => true,
    ]);
});

test('description is optional', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('status-pages.store'), [
            'title' => 'Acme',
            'slug'  => 'acme',
        ])
        ->assertRedirect(route('status-pages.index'));

    $this->assertDatabaseHas('status_pages', ['slug' => 'acme', 'description' => null]);
});

// ─── Slug validation ──────────────────────────────────────────────────────────

test('slug must be unique', function () {
    $user = User::factory()->create();
    StatusPage::factory()->create(['slug' => 'taken']);

    $this->actingAs($user)
        ->post(route('status-pages.store'), [
            'title' => 'Test',
            'slug'  => 'taken',
        ])
        ->assertSessionHasErrors('slug');
});

test('slug must match lowercase alphanumeric pattern', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('status-pages.store'), ['title' => 'Test', 'slug' => 'Invalid Slug!'])
        ->assertSessionHasErrors('slug');
});

test('slug must be at least 3 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('status-pages.store'), ['title' => 'Test', 'slug' => 'ab'])
        ->assertSessionHasErrors('slug');
});

// ─── Plan limits ──────────────────────────────────────────────────────────────

test('free user cannot create a 2nd status page', function () {
    $user = User::factory()->create(['plan' => 'free']);
    StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('status-pages.store'), ['title' => 'Second', 'slug' => 'second'])
        ->assertForbidden();
});

test('pro user can create multiple status pages', function () {
    $user = User::factory()->create(['plan' => 'pro']);
    StatusPage::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('status-pages.store'), ['title' => 'Fourth', 'slug' => 'fourth'])
        ->assertRedirect(route('status-pages.index'));
});

// ─── Edit ─────────────────────────────────────────────────────────────────────

test('owner can view the edit form', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('status-pages.edit', $sp))
        ->assertOk();
});

test('non-owner cannot view edit form', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sp    = StatusPage::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('status-pages.edit', $sp))
        ->assertForbidden();
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('owner can update a status page', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->put(route('status-pages.update', $sp), [
            'title'       => 'Updated Title',
            'slug'        => 'updated-slug',
            'description' => 'Updated desc',
            'is_active'   => false,
        ])
        ->assertRedirect(route('status-pages.index'));

    $sp->refresh();
    expect($sp->title)->toBe('Updated Title');
    expect($sp->slug)->toBe('updated-slug');
    expect($sp->is_active)->toBeFalse();
});

test('non-owner cannot update status page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sp    = StatusPage::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->put(route('status-pages.update', $sp), ['title' => 'Hacked', 'slug' => 'hacked'])
        ->assertForbidden();
});

test('slug uniqueness allows keeping the same slug on update', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'slug' => 'my-page']);

    $this->actingAs($user)
        ->put(route('status-pages.update', $sp), [
            'title' => 'Changed Title',
            'slug'  => 'my-page',
        ])
        ->assertRedirect(route('status-pages.index'));
});

// ─── Toggle ───────────────────────────────────────────────────────────────────

test('owner can toggle active state', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    $this->actingAs($user)
        ->patch(route('status-pages.toggle', $sp))
        ->assertRedirect();

    expect($sp->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('status-pages.toggle', $sp))
        ->assertRedirect();

    expect($sp->fresh()->is_active)->toBeTrue();
});

// ─── Delete ───────────────────────────────────────────────────────────────────

test('owner can delete a status page', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('status-pages.destroy', $sp))
        ->assertRedirect(route('status-pages.index'));

    $this->assertDatabaseMissing('status_pages', ['id' => $sp->id]);
});

test('non-owner cannot delete status page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $sp    = StatusPage::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->delete(route('status-pages.destroy', $sp))
        ->assertForbidden();
});

// ─── Public page ──────────────────────────────────────────────────────────────

test('active status page is publicly accessible by slug', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create([
        'user_id'   => $user->id,
        'slug'      => 'acme-status',
        'is_active' => true,
    ]);
    Monitor::factory()->create([
        'user_id'        => $user->id,
        'name'           => 'API',
        'current_status' => 'up',
        'is_paused'      => false,
    ]);

    $this->get('/status/acme-status')
        ->assertOk()
        ->assertSee($sp->title)
        ->assertSee('API');
});

test('inactive status page returns 404', function () {
    $sp = StatusPage::factory()->create(['is_active' => false, 'slug' => 'hidden']);

    $this->get('/status/hidden')
        ->assertNotFound();
});

test('non-existent slug returns 404', function () {
    $this->get('/status/does-not-exist')
        ->assertNotFound();
});
