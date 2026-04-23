<?php

namespace App\Events;

use App\Models\CheckResult;
use App\Models\Monitor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CheckCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly Monitor     $monitor,
        public readonly CheckResult $checkResult,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('user.' . $this->monitor->user_id);
    }

    public function broadcastAs(): string
    {
        return 'CheckCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'monitor' => [
                'id'                    => $this->monitor->id,
                'current_status'        => $this->monitor->current_status,
                'is_paused'             => $this->monitor->is_paused,
                'last_status_code'      => $this->checkResult->status_code,
                'last_response_time_ms' => $this->checkResult->response_time_ms,
                'last_checked_at_human' => $this->checkResult->checked_at->diffForHumans(),
                'uptime_24h'            => $this->monitor->uptimePercentage('24h'),
            ],
        ];
    }
}
