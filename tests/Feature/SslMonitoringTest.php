<?php

use App\Jobs\PerformSslCheck;
use App\Models\Monitor;
use App\Models\SslCheck;
use App\Models\User;
use App\Notifications\SslExpiringNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

// ─── Test subclass ────────────────────────────────────────────────────────────

class FakeSslCheck extends PerformSslCheck
{
    public function __construct(Monitor $monitor, private readonly array $fakeData)
    {
        parent::__construct($monitor);
    }

    protected function fetchCertificate(): array
    {
        return $this->fakeData;
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function sslMonitor(int $alertDays = 14): Monitor
{
    return Monitor::factory()->create([
        'user_id'               => User::factory(),
        'url'                   => 'https://example.com',
        'ssl_check_enabled'     => true,
        'ssl_expiry_alert_days' => $alertDays,
        'is_paused'             => false,
    ]);
}

function makeSslJob(Monitor $monitor, array $certData): FakeSslCheck
{
    return new FakeSslCheck($monitor, $certData);
}

function validCert(int $daysLeft = 60): array
{
    return [
        'issuer'            => "Let's Encrypt",
        'valid_from'        => now()->subDays(30),
        'valid_to'          => now()->addDays($daysLeft),
        'days_until_expiry' => $daysLeft,
        'is_valid'          => true,
        'error'             => null,
    ];
}

function expiredCert(): array
{
    return [
        'issuer'            => "Let's Encrypt",
        'valid_from'        => now()->subDays(400),
        'valid_to'          => now()->subDays(5),
        'days_until_expiry' => -5,
        'is_valid'          => false,
        'error'             => null,
    ];
}

function failedCert(string $error = 'Connection failed'): array
{
    return [
        'issuer'            => null,
        'valid_from'        => null,
        'valid_to'          => null,
        'days_until_expiry' => null,
        'is_valid'          => false,
        'error'             => $error,
    ];
}

// ─── SslCheck::alertLevel() ───────────────────────────────────────────────────

test('alert level is ok for a cert expiring in 60 days', function () {
    $check = new SslCheck(['days_until_expiry' => 60, 'is_valid' => true]);
    expect($check->alertLevel())->toBe('ok');
});

test('alert level is warning for a cert expiring in 14 days', function () {
    $check = new SslCheck(['days_until_expiry' => 14, 'is_valid' => true]);
    expect($check->alertLevel())->toBe('warning');
});

test('alert level is warning for a cert expiring between 8 and 14 days', function () {
    $check = new SslCheck(['days_until_expiry' => 8, 'is_valid' => true]);
    expect($check->alertLevel())->toBe('warning');
});

test('alert level is critical for a cert expiring in 7 days', function () {
    $check = new SslCheck(['days_until_expiry' => 7, 'is_valid' => true]);
    expect($check->alertLevel())->toBe('critical');
});

test('alert level is critical for a cert expiring in 1 day', function () {
    $check = new SslCheck(['days_until_expiry' => 1, 'is_valid' => true]);
    expect($check->alertLevel())->toBe('critical');
});

test('alert level is expired when is_valid is false', function () {
    $check = new SslCheck(['days_until_expiry' => -5, 'is_valid' => false]);
    expect($check->alertLevel())->toBe('expired');
});

test('alert level is expired when connection failed', function () {
    $check = new SslCheck(['days_until_expiry' => null, 'is_valid' => false]);
    expect($check->alertLevel())->toBe('expired');
});

// ─── PerformSslCheck: saves ssl_check record ──────────────────────────────────

test('saves ssl check record with valid certificate data', function () {
    Notification::fake();

    $monitor = sslMonitor();
    $job     = makeSslJob($monitor, validCert(60));
    $job->handle();

    $check = SslCheck::where('monitor_id', $monitor->id)->sole();
    expect($check->issuer)->toBe("Let's Encrypt")
        ->and($check->days_until_expiry)->toBe(60)
        ->and($check->is_valid)->toBeTrue()
        ->and($check->error)->toBeNull()
        ->and($check->checked_at)->not->toBeNull();
});

test('saves ssl check record for expired certificate', function () {
    Notification::fake();

    $monitor = sslMonitor();
    $job     = makeSslJob($monitor, expiredCert());
    $job->handle();

    $check = SslCheck::where('monitor_id', $monitor->id)->sole();
    expect($check->is_valid)->toBeFalse()
        ->and($check->days_until_expiry)->toBe(-5);
});

test('saves ssl check record for connection failure', function () {
    Notification::fake();

    $monitor = sslMonitor();
    $job     = makeSslJob($monitor, failedCert('SSL handshake error'));
    $job->handle();

    $check = SslCheck::where('monitor_id', $monitor->id)->sole();
    expect($check->is_valid)->toBeFalse()
        ->and($check->error)->toBe('SSL handshake error')
        ->and($check->issuer)->toBeNull();
});

// ─── PerformSslCheck: notification logic ─────────────────────────────────────

test('no notification when certificate is healthy', function () {
    Notification::fake();

    $monitor = sslMonitor(14);
    $job     = makeSslJob($monitor, validCert(60));
    $job->handle();

    Notification::assertNothingSent();
});

test('sends notification when cert expires within alert threshold (warning)', function () {
    Notification::fake();

    $monitor = sslMonitor(14);
    $job     = makeSslJob($monitor, validCert(10)); // 10 days < 14 day threshold
    $job->handle();

    Notification::assertSentTo($monitor->user, SslExpiringNotification::class);
});

test('sends notification when cert expires within critical threshold', function () {
    Notification::fake();

    $monitor = sslMonitor(14);
    $job     = makeSslJob($monitor, validCert(5));
    $job->handle();

    Notification::assertSentTo($monitor->user, SslExpiringNotification::class);
});

test('sends notification when certificate is expired', function () {
    Notification::fake();

    $monitor = sslMonitor(14);
    $job     = makeSslJob($monitor, expiredCert());
    $job->handle();

    Notification::assertSentTo($monitor->user, SslExpiringNotification::class);
});

test('sends notification when certificate connection fails', function () {
    Notification::fake();

    $monitor = sslMonitor(14);
    $job     = makeSslJob($monitor, failedCert());
    $job->handle();

    Notification::assertSentTo($monitor->user, SslExpiringNotification::class);
});

test('no notification when cert expires after custom alert threshold', function () {
    Notification::fake();

    $monitor = sslMonitor(7); // only alert within 7 days
    $job     = makeSslJob($monitor, validCert(10)); // 10 days > 7 day threshold, but <= 14
    $job->handle();

    Notification::assertNothingSent();
});

// ─── SslExpiringNotification: mail content ────────────────────────────────────

test('expired notification mail contains EXPIRED in subject', function () {
    $monitor = Monitor::factory()->create(['name' => 'My Site', 'url' => 'https://example.com']);
    $check   = SslCheck::factory()->expired()->for($monitor)->create(['checked_at' => now()]);

    $mail = (new SslExpiringNotification($monitor, $check))->toMail($monitor->user);

    expect($mail->subject)->toContain('EXPIRED');
});

test('warning notification mail contains days until expiry in subject', function () {
    $monitor = Monitor::factory()->create(['name' => 'My Site', 'url' => 'https://example.com']);
    $check   = SslCheck::factory()->expiring(10)->for($monitor)->create(['checked_at' => now()]);

    $mail = (new SslExpiringNotification($monitor, $check))->toMail($monitor->user);

    expect($mail->subject)->toContain('10d');
});

test('critical notification mail contains monitor url in body', function () {
    $monitor = Monitor::factory()->create(['name' => 'My Site', 'url' => 'https://example.com']);
    $check   = SslCheck::factory()->expiring(5)->for($monitor)->create(['checked_at' => now()]);

    $mail = (new SslExpiringNotification($monitor, $check))->toMail($monitor->user);
    $body = collect($mail->introLines)->implode(' ');

    expect($body)->toContain('https://example.com');
});

test('notification is queued on notifications queue', function () {
    $monitor = Monitor::factory()->create();
    $check   = SslCheck::factory()->for($monitor)->create(['checked_at' => now()]);

    $notification = new SslExpiringNotification($monitor, $check);

    expect($notification->viaQueues())->toBe(['mail' => 'notifications']);
});

test('notification via mail channel', function () {
    $monitor      = Monitor::factory()->create();
    $check        = SslCheck::factory()->for($monitor)->create(['checked_at' => now()]);
    $notification = new SslExpiringNotification($monitor, $check);

    expect($notification->via($monitor->user))->toBe(['mail']);
});

// ─── DispatchSslChecks command ────────────────────────────────────────────────

test('dispatch ssl checks command dispatches jobs for enabled monitors', function () {
    Queue::fake();

    $user = User::factory()->create();

    Monitor::factory()->for($user)->create(['ssl_check_enabled' => true, 'is_paused' => false]);
    Monitor::factory()->for($user)->create(['ssl_check_enabled' => true, 'is_paused' => false]);
    Monitor::factory()->for($user)->create(['ssl_check_enabled' => false, 'is_paused' => false]);
    Monitor::factory()->for($user)->create(['ssl_check_enabled' => true, 'is_paused' => true]);

    $this->artisan('monitors:dispatch-ssl-checks')
        ->assertExitCode(0);

    Queue::assertPushed(PerformSslCheck::class, 2);
});

test('dispatch ssl checks command outputs dispatched count', function () {
    Queue::fake();

    $user = User::factory()->create();
    Monitor::factory()->for($user)->create(['ssl_check_enabled' => true, 'is_paused' => false]);

    $this->artisan('monitors:dispatch-ssl-checks')
        ->expectsOutput('Dispatched 1 SSL check job(s).')
        ->assertExitCode(0);
});
