<?php

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeCheck(Monitor $monitor, string $checkedAt, bool $successful = true): CheckResult
{
    return CheckResult::factory()->for($monitor)->create([
        'checked_at'    => $checkedAt,
        'is_successful' => $successful,
    ]);
}

// ─── Index ─────────────────────────────────────────────────────────────────────

test('returns paginated check results for own monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    CheckResult::factory()->for($monitor)->count(3)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/monitors/{$monitor->id}/checks")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'type', 'attributes']],
            'meta',
            'links',
        ]);
});

test('check result has correct attributes shape', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    CheckResult::factory()->for($monitor)->create();

    Sanctum::actingAs($user);

    $attrs = $this->getJson("/api/v1/monitors/{$monitor->id}/checks")
        ->assertOk()
        ->json('data.0.attributes');

    expect($attrs)->toHaveKeys(['status_code', 'response_time_ms', 'is_successful', 'checked_at']);
    expect($this->getJson("/api/v1/monitors/{$monitor->id}/checks")->json('data.0.type'))->toBe('check_result');
});

test('results are filtered by from date', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    makeCheck($monitor, '2024-01-05 12:00:00');
    makeCheck($monitor, '2024-01-10 12:00:00');
    makeCheck($monitor, '2024-01-15 12:00:00');

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/monitors/{$monitor->id}/checks?from=2024-01-10")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('results are filtered by to date', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    makeCheck($monitor, '2024-01-05 12:00:00');
    makeCheck($monitor, '2024-01-10 12:00:00');
    makeCheck($monitor, '2024-01-15 12:00:00');

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/monitors/{$monitor->id}/checks?to=2024-01-10")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('results are filtered by from and to range', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    makeCheck($monitor, '2024-01-05 12:00:00');
    makeCheck($monitor, '2024-01-10 12:00:00');
    makeCheck($monitor, '2024-01-15 12:00:00');

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/monitors/{$monitor->id}/checks?from=2024-01-08&to=2024-01-12")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('returns 422 when to is before from', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/monitors/{$monitor->id}/checks?from=2024-01-15&to=2024-01-01")
        ->assertStatus(422);
});

test('returns 403 for another users monitor', function () {
    $monitor = Monitor::factory()->for(User::factory()->create())->create();

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/monitors/{$monitor->id}/checks")->assertStatus(403);
});

test('returns 404 for non-existent monitor', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/monitors/99999/checks')->assertStatus(404);
});

test('paginates at 50 per page', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->for($user)->create();
    CheckResult::factory()->for($monitor)->count(60)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/monitors/{$monitor->id}/checks")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 60);
});

test('unauthenticated request returns 401', function () {
    $monitor = Monitor::factory()->for(User::factory()->create())->create();

    $this->getJson("/api/v1/monitors/{$monitor->id}/checks")->assertStatus(401);
});
