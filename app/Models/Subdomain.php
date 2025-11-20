<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subdomain extends Model
{
    protected $fillable = [
        'parent_domain_id',
        'subdomain_name',
        'document_root',
        'nginx_config_path',
        'php_version',
        'php_fpm_pool',
        'ssl_enabled',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
    ];

    public function parentDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'parent_domain_id');
    }

    public function getFullDomainAttribute(): string
    {
        if ($this->subdomain_name === '@') {
            return $this->parentDomain->domain_name;
        }

        return $this->subdomain_name . '.' . $this->parentDomain->domain_name;
    }
}
