<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    //
    protected $table = 'otp';
    protected $fillable = [
        'email',
        'service',
        'password',
        'is_verified',
        'expires_at',
        'owner',
        'mail_type',
        'screen_locks',
    ];

    protected $casts = [
        'screen_locks' => 'array',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'screen_locks',
    ];

    protected $appends = [
        'screen_lock_screens',
        'screen_lock_count',
    ];

    public function getScreenLockScreensAttribute(): array
    {
        $locks = $this->screen_locks ?? [];
        if (!is_array($locks)) {
            return [];
        }

        return collect($locks)
            ->pluck('screen_name')
            ->filter(fn($name) => is_string($name) && trim($name) !== '')
            ->values()
            ->all();
    }

    public function getScreenLockCountAttribute(): int
    {
        return count($this->screen_lock_screens);
    }
}
