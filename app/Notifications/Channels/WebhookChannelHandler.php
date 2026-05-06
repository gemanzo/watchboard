<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;

class WebhookChannelHandler implements ChannelHandler
{
    public function send(NotificationChannel $channel, string $event, array $context): void
    {
        $payload = $this->buildPayload($event, $context);
        $this->post($channel, $payload);
    }

    public function sendTest(NotificationChannel $channel): void
    {
        $payload = $this->buildPayload('monitor.test', [
            'monitor'      => (object) ['id' => null, 'name' => 'Test Monitor', 'url' => 'https://example.com'],
            'check_result' => null,
        ]);

        $this->post($channel, $payload);
    }

    // -------------------------------------------------------------------------

    private function buildPayload(string $event, array $context): array
    {
        $monitor     = $context['monitor'] ?? null;
        $checkResult = $context['check_result'] ?? null;

        $payload = [
            'event'       => $event,
            'notified_at' => now()->toIso8601String(),
        ];

        if ($monitor) {
            $payload['monitor'] = [
                'id'   => $monitor->id ?? null,
                'name' => $monitor->name ?? null,
                'url'  => $monitor->url ?? null,
            ];
        }

        if ($checkResult) {
            $payload['check'] = [
                'status_code'      => $checkResult->status_code,
                'response_time_ms' => $checkResult->response_time_ms,
                'is_successful'    => $checkResult->is_successful,
                'checked_at'       => $checkResult->checked_at->toIso8601String(),
            ];
        }

        if (isset($context['downtime_seconds'])) {
            $payload['downtime_seconds'] = $context['downtime_seconds'];
        }

        if (isset($context['threshold_ms'])) {
            $payload['threshold_ms'] = $context['threshold_ms'];
        }

        return $payload;
    }

    private function post(NotificationChannel $channel, array $payload): void
    {
        $config  = $channel->config;
        $url     = $config['url'];
        $secret  = $config['secret'] ?? null;
        $timeout = (int) ($config['timeout_seconds'] ?? 10);

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $request = Http::timeout($timeout)
            ->withBody($body, 'application/json');

        if (filled($secret)) {
            $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
            $request   = $request->withHeader('X-WatchBoard-Signature', $signature);
        }

        $response = $request->post($url);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Webhook returned HTTP {$response->status()} from {$url}"
            );
        }
    }
}
