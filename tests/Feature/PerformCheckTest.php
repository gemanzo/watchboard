<?php

use App\Jobs\PerformCheck;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// ─── Helper ───────────────────────────────────────────────────────────────────

function monitorFor(string $method = 'GET', string $url = 'https://example.com'): Monitor
{
    return Monitor::factory()->create([
        'user_id'          => User::factory(),
        'url'              => $url,
        'method'           => $method,
        'interval_minutes' => 5,
        'is_paused'        => false,
        'current_status'   => 'unknown',
        'last_checked_at'  => null,
    ]);
}

// ─── Successful check ─────────────────────────────────────────────────────────

test('saves check result and updates monitor on 200 response', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $monitor = monitorFor();
    (new PerformCheck($monitor))->handle();

    $result = CheckResult::where('monitor_id', $monitor->id)->sole();
    expect($result->status_code)->toBe(200)
        ->and($result->is_successful)->toBeTrue()
        ->and($result->response_time_ms)->toBeGreaterThanOrEqual(0)
        ->and($result->checked_at)->not->toBeNull();

    $monitor->refresh();
    expect($monitor->current_status)->toBe('up')
        ->and($monitor->last_checked_at)->not->toBeNull();
});

test('marks monitor as up for any 2xx status code', function () {
    Http::fake(['*' => Http::response('Created', 201)]);

    $monitor = monitorFor();
    (new PerformCheck($monitor))->handle();

    expect($monitor->refresh()->current_status)->toBe('up');
    expect(CheckResult::where('monitor_id', $monitor->id)->sole()->is_successful)->toBeTrue();
});

test('uses the configured http method', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $monitor = monitorFor('HEAD');
    (new PerformCheck($monitor))->handle();

    Http::assertSent(fn($request) => $request->method() === 'HEAD');
});

// ─── 4xx / 5xx responses ──────────────────────────────────────────────────────

test('saves check result and marks monitor down on 500 response', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $monitor = monitorFor();
    (new PerformCheck($monitor))->handle();

    $result = CheckResult::where('monitor_id', $monitor->id)->sole();
    expect($result->status_code)->toBe(500)
        ->and($result->is_successful)->toBeFalse();

    expect($monitor->refresh()->current_status)->toBe('down');
});

test('saves status code on 404 response', function () {
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $monitor = monitorFor();
    (new PerformCheck($monitor))->handle();

    expect(CheckResult::where('monitor_id', $monitor->id)->sole()->status_code)->toBe(404);
    expect($monitor->refresh()->current_status)->toBe('down');
});

// ─── Connection failure ───────────────────────────────────────────────────────

test('saves null status_code and marks monitor down on connection error', function () {
    Http::fake(['*' => fn() => throw new ConnectionException('Connection refused')]);

    $monitor = monitorFor();
    (new PerformCheck($monitor))->handle();

    $result = CheckResult::where('monitor_id', $monitor->id)->sole();
    expect($result->status_code)->toBeNull()
        ->and($result->is_successful)->toBeFalse()
        ->and($result->response_time_ms)->toBeGreaterThanOrEqual(0);

    expect($monitor->refresh()->current_status)->toBe('down');
});

// ─── Timeout ─────────────────────────────────────────────────────────────────

test('saves null status_code and marks monitor down on timeout', function () {
    // ConnectionException is thrown for timeouts by Laravel's Http client
    Http::fake(['*' => fn() => throw new ConnectionException('cURL error 28: Operation timed out')]);

    $monitor = monitorFor();
    (new PerformCheck($monitor))->handle();

    $result = CheckResult::where('monitor_id', $monitor->id)->sole();
    expect($result->status_code)->toBeNull()
        ->and($result->is_successful)->toBeFalse();

    expect($monitor->refresh()->current_status)->toBe('down');
});

// ─── Job configuration ────────────────────────────────────────────────────────

test('job has 3 tries', function () {
    $monitor = monitorFor();
    $job     = new PerformCheck($monitor);

    expect($job->tries)->toBe(3);
});

test('job has exponential backoff', function () {
    $monitor = monitorFor();
    $job     = new PerformCheck($monitor);

    expect($job->backoff)->toBe([10, 100, 1000]);
});

test('job targets the checks queue', function () {
    $monitor = monitorFor();
    $job     = new PerformCheck($monitor);

    expect($job->queue)->toBe('checks');
});

// ─── last_checked_at set correctly ───────────────────────────────────────────

test('always updates last_checked_at even on failure', function () {
    Http::fake(['*' => fn() => throw new ConnectionException('refused')]);

    $monitor = monitorFor();
    (new PerformCheck($monitor))->handle();

    expect($monitor->refresh()->last_checked_at)->not->toBeNull();
});
