<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'issuer',
        'valid_from',
        'valid_to',
        'days_until_expiry',
        'is_valid',
        'error',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_from'        => 'datetime',
            'valid_to'          => 'datetime',
            'days_until_expiry' => 'integer',
            'is_valid'          => 'boolean',
            'checked_at'        => 'datetime',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function alertLevel(): string
    {
        if (! $this->is_valid) {
            return 'expired';
        }

        if ($this->days_until_expiry !== null && $this->days_until_expiry <= 7) {
            return 'critical';
        }

        if ($this->days_until_expiry !== null && $this->days_until_expiry <= 14) {
            return 'warning';
        }

        return 'ok';
    }
}
