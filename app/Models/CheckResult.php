<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'status_code',
        'response_time_ms',
        'is_successful',
        'keyword_matched',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_successful' => 'boolean',
            'keyword_matched' => 'boolean',
            'checked_at'    => 'datetime',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
