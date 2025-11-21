<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mailbox extends Model
{
    protected $fillable = [
        'domain_id',
        'email',
        'password_encrypted',
        'quota_mb',
        'used_mb',
        'status',
    ];

    protected $casts = [
        'quota_mb' => 'integer',
        'used_mb' => 'integer',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    protected $appends = [
        'localpart',
        'quota_percentage',
        'quota_badge_color',
    ];

    /**
     * Get the domain that owns this mailbox
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get localpart (part before @)
     */
    public function getLocalpartAttribute(): string
    {
        return explode('@', $this->email)[0];
    }

    /**
     * Get quota usage percentage
     */
    public function getQuotaPercentageAttribute(): float
    {
        if ($this->quota_mb === 0) {
            return 0;
        }
        return round(($this->used_mb / $this->quota_mb) * 100, 1);
    }

    /**
     * Get quota badge color based on usage
     */
    public function getQuotaBadgeColorAttribute(): string
    {
        $percentage = $this->quota_percentage;
        
        if ($percentage >= 95) {
            return 'red';
        }
        
        if ($percentage >= 80) {
            return 'yellow';
        }
        
        return 'green';
    }
}
