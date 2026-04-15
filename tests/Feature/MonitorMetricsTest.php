<?php

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;

test('returns aggregated response times for a monitor', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 100,
        'is_successful'    => true,
        'checked_at'       => now()->subMinutes(10),
    ]);

    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 200,
        'is_successful'    => true,
        'checked_at'       => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('monitors.metrics', $monitor));

    $response->assertOk()
        ->assertJsonStructure(['data' => [['timestamp', 'avg_response_time_ms', 'check_count']]]);

    $total = collect($response->json('data'))->sum('check_count');
    expect($total)->toBe(2);
});

test('aggregates into 15-minute buckets', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $baseTime = now()->startOfHour();

    // Two results in the same 15-min bucket (minute 0-14)
    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 100,
        'is_successful'    => true,
        'checked_at'       => $baseTime->copy()->addMinutes(2),
    ]);

    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 200,
        'is_successful'    => true,
        'checked_at'       => $baseTime->copy()->addMinutes(10),
    ]);

    // One result in a different bucket (minute 15-29)
    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 300,
        'is_successful'    => true,
        'checked_at'       => $baseTime->copy()->addMinutes(20),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('monitors.metrics', $monitor));

    $data = $response->json('data');

    expect($data)->toHaveCount(2);
    // First bucket: avg of 100 and 200
    expect($data[0]['avg_response_time_ms'])->toBe(150);
    expect($data[0]['check_count'])->toBe(2);
    // Second bucket: just 300
    expect($data[1]['avg_response_time_ms'])->toBe(300);
    expect($data[1]['check_count'])->toBe(1);
});

test('filters by 24h range by default', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    // Within 24h
    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 100,
        'is_successful'    => true,
        'checked_at'       => now()->subHours(12),
    ]);

    // Outside 24h
    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 999,
        'is_successful'    => true,
        'checked_at'       => now()->subHours(25),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('monitors.metrics', $monitor));

    $total = collect($response->json('data'))->sum('check_count');
    expect($total)->toBe(1);
});

test('supports 7d range', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 100,
        'is_successful'    => true,
        'checked_at'       => now()->subDays(3),
    ]);

    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 200,
        'is_successful'    => true,
        'checked_at'       => now()->subDays(8),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('monitors.metrics', [$monitor, 'range' => '7d']));

    $total = collect($response->json('data'))->sum('check_count');
    expect($total)->toBe(1);
});

test('supports 30d range', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    CheckResult::create([
        'monitor_id'       => $monitor->id,
        'status_code'      => 200,
        'response_time_ms' => 100,
        'is_successful'    => true,
        'checked_at'       => now()->subDays(20),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('monitors.metrics', [$monitor, 'range' => '30d']));

    $total = collect($response->json('data'))->sum('check_count');
    expect($total)->toBe(1);
});

test('rejects invalid range', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson(route('monitors.metrics', [$monitor, 'range' => '1y']))
        ->assertUnprocessable();
});

test('non-owner cannot access metrics', function () {
    $owner   = User::factory()->create();
    $other   = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)
        ->getJson(route('monitors.metrics', $monitor))
        ->assertForbidden();
});

test('guest cannot access metrics', function () {
    $monitor = Monitor::factory()->create(['user_id' => User::factory()]);

    $this->getJson(route('monitors.metrics', $monitor))
        ->assertUnauthorized();
});

test('returns empty data for monitor with no check results', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->getJson(route('monitors.metrics', $monitor));

    $response->assertOk()
        ->assertJson(['data' => []]);
});
