<?php

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// ─── Daily uptime model method ──────────────────────────────────────────────

test('dailyUptime returns 90 entries keyed by date', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $result = $monitor->dailyUptime(90);

    expect($result)->toHaveCount(90);
    expect(array_key_first($result))->toBe(now()->subDays(89)->format('Y-m-d'));
    expect(array_key_last($result))->toBe(now()->format('Y-m-d'));
});

test('dailyUptime returns null for days with no checks', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $result = $monitor->dailyUptime(90);

    // No checks created, all days should be null
    foreach ($result as $uptime) {
        expect($uptime)->toBeNull();
    }
});

test('dailyUptime calculates percentage for a day with checks', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    // Today: 3 successful, 1 failed = 75%
    $today = now()->format('Y-m-d');
    for ($i = 0; $i < 3; $i++) {
        CheckResult::factory()->create([
            'monitor_id'    => $monitor->id,
            'is_successful' => true,
            'checked_at'    => now()->startOfDay()->addHours($i),
        ]);
    }
    CheckResult::factory()->create([
        'monitor_id'    => $monitor->id,
        'is_successful' => false,
        'checked_at'    => now()->startOfDay()->addHours(4),
    ]);

    $result = $monitor->dailyUptime(90);

    expect($result[$today])->toBe(75.0);
});

test('dailyUptime returns 100 for a fully successful day', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    CheckResult::factory()->count(5)->create([
        'monitor_id'    => $monitor->id,
        'is_successful' => true,
        'checked_at'    => now(),
    ]);

    $result = $monitor->dailyUptime(90);
    $today = now()->format('Y-m-d');

    expect($result[$today])->toBe(100.0);
});

test('dailyUptime is cached for 5 minutes', function () {
    $user    = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    Cache::shouldReceive('remember')
        ->once()
        ->withArgs(fn ($key, $ttl) => $key === "monitor:{$monitor->id}:daily_uptime:90" && $ttl === 300)
        ->andReturn([]);

    $monitor->dailyUptime(90);
});

// ─── Public page response ────────────────────────────────────────────────────

test('public page includes daily_uptime for each monitor', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $mon  = Monitor::factory()->create([
        'user_id' => $user->id, 'current_status' => 'up', 'is_paused' => false,
    ]);
    $sp->monitors()->attach($mon->id, ['sort_order' => 0]);

    $response = $this->get(route('status-pages.public', $sp->slug));

    $response->assertOk();
    $monitors = $response->original->getData()['page']['props']['monitors'];
    expect($monitors[0])->toHaveKey('daily_uptime');
    expect($monitors[0]['daily_uptime'])->toHaveCount(90);
});

test('public page daily_uptime reflects actual check data', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $mon  = Monitor::factory()->create([
        'user_id' => $user->id, 'current_status' => 'up', 'is_paused' => false,
    ]);
    $sp->monitors()->attach($mon->id, ['sort_order' => 0]);

    // Add checks for today
    CheckResult::factory()->count(4)->create([
        'monitor_id' => $mon->id, 'is_successful' => true, 'checked_at' => now(),
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $mon->id, 'is_successful' => false, 'checked_at' => now(),
    ]);

    $response = $this->get(route('status-pages.public', $sp->slug));

    $monitors = $response->original->getData()['page']['props']['monitors'];
    $today    = now()->format('Y-m-d');
    expect($monitors[0]['daily_uptime'][$today])->toBe(80.0);
});

test('overall status shows operational when all monitors are up', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    $mon1 = Monitor::factory()->create(['user_id' => $user->id, 'current_status' => 'up', 'is_paused' => false]);
    $mon2 = Monitor::factory()->create(['user_id' => $user->id, 'current_status' => 'up', 'is_paused' => false]);
    $sp->monitors()->attach([$mon1->id => ['sort_order' => 0], $mon2->id => ['sort_order' => 1]]);

    $response = $this->get(route('status-pages.public', $sp->slug));

    $monitors = $response->original->getData()['page']['props']['monitors'];
    expect(collect($monitors)->every(fn ($m) => $m['current_status'] === 'up'))->toBeTrue();
});

test('overall status reflects down monitors', function () {
    $user = User::factory()->create();
    $sp   = StatusPage::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    $up   = Monitor::factory()->create(['user_id' => $user->id, 'current_status' => 'up', 'is_paused' => false]);
    $down = Monitor::factory()->create(['user_id' => $user->id, 'current_status' => 'down', 'is_paused' => false]);
    $sp->monitors()->attach([$up->id => ['sort_order' => 0], $down->id => ['sort_order' => 1]]);

    $response = $this->get(route('status-pages.public', $sp->slug));

    $monitors = $response->original->getData()['page']['props']['monitors'];
    $statuses = collect($monitors)->pluck('current_status')->toArray();
    expect($statuses)->toContain('down');
    expect($statuses)->toContain('up');
});

test('inactive status page returns 404', function () {
    $sp = StatusPage::factory()->create(['is_active' => false, 'slug' => 'hidden-page']);

    $this->get('/status/hidden-page')->assertNotFound();
});

test('non-existent slug returns 404', function () {
    $this->get('/status/no-such-page')->assertNotFound();
});
