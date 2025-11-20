<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NginxConfig extends Model
{
    protected $fillable = [
        'domain_id',
        'config_content',
        'config_path',
        'is_active',
        'last_tested_at',
        'last_reloaded_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
        'last_reloaded_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function wasTestedRecently(int $minutes = 5): bool
    {
        return $this->last_tested_at && $this->last_tested_at->diffInMinutes(now()) <= $minutes;
    }
}
