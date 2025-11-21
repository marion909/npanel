<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAlias extends Model
{
    protected $fillable = [
        'domain_id',
        'source',
        'destination',
        'type',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    /**
     * Get the domain that owns this alias
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Check if this is a catch-all alias
     */
    public function isCatchAll(): bool
    {
        return $this->type === 'catchall';
    }
}
