<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhpFpmPool extends Model
{
    protected $fillable = [
        'domain_id',
        'pool_name',
        'php_version',
        'socket_path',
        'pm_mode',
        'pm_max_children',
        'pm_start_servers',
        'pm_min_spare_servers',
        'pm_max_spare_servers',
        'memory_limit',
        'max_execution_time',
    ];

    protected $casts = [
        'pm_max_children' => 'integer',
        'pm_start_servers' => 'integer',
        'pm_min_spare_servers' => 'integer',
        'pm_max_spare_servers' => 'integer',
        'max_execution_time' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function getConfigPathAttribute(): string
    {
        return str_replace(
            '{version}',
            $this->php_version,
            config('npanel.php_fpm_pool_dir')
        ) . '/' . $this->pool_name . '.conf';
    }
}
