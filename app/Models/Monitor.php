<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Monitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'url',
        'method',
        'interval_minutes',
        'current_status',
        'is_paused',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_paused'       => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(CheckResult::class);
    }

    public function latestCheckResult(): HasOne
    {
        return $this->hasOne(CheckResult::class)->latestOfMany('checked_at');
    }

    public function uptimePercentage(string $range): ?float
    {
        $cacheKey = "monitor:{$this->id}:uptime:{$range}";

        return Cache::remember($cacheKey, 300, function () use ($range) {
            $since = match ($range) {
                '24h' => now()->subHours(24),
                '7d'  => now()->subDays(7),
                '30d' => now()->subDays(30),
            };

            $query = $this->checkResults()->where('checked_at', '>=', $since);

            $total = $query->count();

            if ($total === 0) {
                return null;
            }

            $successful = (clone $query)->where('is_successful', true)->count();

            return round(($successful / $total) * 100, 2);
        });
    }

    public function uptimeAll(): array
    {
        return [
            '24h' => $this->uptimePercentage('24h'),
            '7d'  => $this->uptimePercentage('7d'),
            '30d' => $this->uptimePercentage('30d'),
        ];
    }
}
