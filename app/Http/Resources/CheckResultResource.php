<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'type' => 'check_result',
            'attributes' => [
                'status_code'      => $this->status_code,
                'response_time_ms' => $this->response_time_ms,
                'is_successful'    => $this->is_successful,
                'checked_at'       => $this->checked_at->toIso8601String(),
            ],
        ];
    }
}
