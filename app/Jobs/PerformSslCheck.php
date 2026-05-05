<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Models\SslCheck;
use App\Notifications\SslExpiringNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PerformSslCheck implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(public readonly Monitor $monitor)
    {
        $this->onQueue('checks');
    }

    public function uniqueId(): string
    {
        return 'ssl-' . $this->monitor->id;
    }

    public function handle(): void
    {
        $result = $this->fetchCertificate();

        $sslCheck = $this->monitor->sslChecks()->create([
            'issuer'            => $result['issuer'],
            'valid_from'        => $result['valid_from'],
            'valid_to'          => $result['valid_to'],
            'days_until_expiry' => $result['days_until_expiry'],
            'is_valid'          => $result['is_valid'],
            'error'             => $result['error'],
            'checked_at'        => now(),
        ]);

        $this->notifyIfNeeded($sslCheck);
    }

    protected function fetchCertificate(): array
    {
        $host    = parse_url($this->monitor->url, PHP_URL_HOST);
        $port    = parse_url($this->monitor->url, PHP_URL_PORT) ?? 443;
        $timeout = 10;

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => true,
                'verify_peer_name'  => true,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($client === false) {
            return [
                'issuer'            => null,
                'valid_from'        => null,
                'valid_to'          => null,
                'days_until_expiry' => null,
                'is_valid'          => false,
                'error'             => $errstr ?: "Connection failed (errno {$errno})",
            ];
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($cert === null) {
            return [
                'issuer'            => null,
                'valid_from'        => null,
                'valid_to'          => null,
                'days_until_expiry' => null,
                'is_valid'          => false,
                'error'             => 'No certificate captured',
            ];
        }

        $info    = openssl_x509_parse($cert);
        $validTo = Carbon::createFromTimestamp($info['validTo_time_t']);
        $validFrom = Carbon::createFromTimestamp($info['validFrom_time_t']);
        $daysUntilExpiry = (int) now()->diffInDays($validTo, false);
        $issuer  = $this->extractIssuer($info['issuer'] ?? []);

        return [
            'issuer'            => $issuer,
            'valid_from'        => $validFrom,
            'valid_to'          => $validTo,
            'days_until_expiry' => $daysUntilExpiry,
            'is_valid'          => $daysUntilExpiry > 0,
            'error'             => null,
        ];
    }

    protected function extractIssuer(array $issuer): ?string
    {
        return $issuer['O'] ?? $issuer['CN'] ?? null;
    }

    private function notifyIfNeeded(SslCheck $sslCheck): void
    {
        $alertLevel = $sslCheck->alertLevel();

        if ($alertLevel === 'ok') {
            return;
        }

        $threshold = $this->monitor->ssl_expiry_alert_days;

        // Only notify when days_until_expiry is at or crosses the configured threshold,
        // or when the cert is expired/invalid.
        if (
            $alertLevel !== 'expired'
            && $sslCheck->days_until_expiry !== null
            && $sslCheck->days_until_expiry > $threshold
        ) {
            return;
        }

        $this->monitor->user->notify(new SslExpiringNotification($this->monitor, $sslCheck));
    }
}
