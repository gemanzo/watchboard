<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'started_at',
        'resolved_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function isOngoing(): bool
    {
        return $this->resolved_at === null;
    }
}
