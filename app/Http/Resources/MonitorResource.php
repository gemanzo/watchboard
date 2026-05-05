<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'type' => 'monitor',
            'attributes' => [
                'name'                   => $this->name,
                'url'                    => $this->url,
                'method'                 => $this->method,
                'interval_minutes'       => $this->interval_minutes,
                'current_status'         => $this->current_status,
                'is_paused'              => $this->is_paused,
                'confirmation_threshold'     => $this->confirmation_threshold,
                'consecutive_failures'       => $this->consecutive_failures,
                'response_time_threshold_ms' => $this->response_time_threshold_ms,
                'keyword_check'              => $this->keyword_check,
                'keyword_check_type'         => $this->keyword_check_type,
                'last_checked_at'        => $this->last_checked_at?->toIso8601String(),
                'created_at'             => $this->created_at->toIso8601String(),
                'updated_at'             => $this->updated_at->toIso8601String(),
            ],
        ];
    }
}
