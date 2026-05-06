<?php

namespace App\Notifications\Channels;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;

class SlackChannelHandler implements ChannelHandler
{
    public function send(NotificationChannel $channel, string $event, array $context): void
    {
        $monitor     = $context['monitor'] ?? null;
        $checkResult = $context['check_result'] ?? null;

        [$emoji, $color, $title] = $this->eventMeta($event, $monitor);

        $fields = [];

        if ($monitor) {
            $fields[] = ['title' => 'URL', 'value' => $monitor->url ?? '–', 'short' => true];
        }

        if ($checkResult) {
            $statusCode = $checkResult->status_code !== null
                ? (string) $checkResult->status_code
                : 'Connection failed';

            $fields[] = ['title' => 'Status', 'value' => $statusCode, 'short' => true];
            $fields[] = [
                'title' => 'Detected at',
                'value' => $checkResult->checked_at->toDateTimeString() . ' UTC',
                'short' => false,
            ];
        }

        if (isset($context['downtime_seconds'])) {
            $fields[] = [
                'title' => 'Downtime',
                'value' => gmdate('H:i:s', $context['downtime_seconds']),
                'short' => true,
            ];
        }

        if (isset($context['threshold_ms'])) {
            $fields[] = ['title' => 'Threshold', 'value' => $context['threshold_ms'] . ' ms', 'short' => true];
        }

        $payload = [
            'text'        => "{$emoji} {$title}",
            'attachments' => [
                [
                    'color'  => $color,
                    'fields' => $fields,
                ],
            ],
        ];

        $url      = $channel->config['webhook_url'];
        $timeout  = (int) ($channel->config['timeout_seconds'] ?? 10);
        $response = Http::timeout($timeout)->post($url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Slack webhook returned HTTP {$response->status()}"
            );
        }
    }

    public function sendTest(NotificationChannel $channel): void
    {
        $payload = [
            'text' => ':white_check_mark: WatchBoard test notification — channel is working correctly.',
        ];

        $url      = $channel->config['webhook_url'];
        $timeout  = (int) ($channel->config['timeout_seconds'] ?? 10);
        $response = Http::timeout($timeout)->post($url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Slack webhook returned HTTP {$response->status()}"
            );
        }
    }

    // -------------------------------------------------------------------------

    private function eventMeta(string $event, ?object $monitor): array
    {
        $name = $monitor->name ?? 'Monitor';

        return match ($event) {
            'monitor.down'          => [':red_circle:',  '#e53e3e', "*{$name}* is DOWN"],
            'monitor.recovered'     => [':large_green_circle:', '#38a169', "*{$name}* has RECOVERED"],
            'monitor.slow_response' => [':yellow_circle:', '#d69e2e', "*{$name}* is responding slowly"],
            default                 => [':bell:', '#718096', "WatchBoard alert for *{$name}*"],
        };
    }
}
