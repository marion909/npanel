<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SslCertificate extends Model
{
    protected $fillable = [
        'domain_id',
        'certificate_path',
        'private_key_path',
        'chain_path',
        'provider',
        'issue_date',
        'expiry_date',
        'auto_renew',
        'last_renewal_attempt',
    ];

    protected $casts = [
        'issue_date' => 'datetime',
        'expiry_date' => 'datetime',
        'last_renewal_attempt' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->diffInDays(now()) <= $days;
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date ? $this->expiry_date->diffInDays(now()) : null;
    }
}
