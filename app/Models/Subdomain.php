<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subdomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'name',
        'full_name',
        'php_version',
        'document_root',
        'nginx_enabled',
    ];

    protected $casts = [
        'nginx_enabled' => 'boolean',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function dnsRecords()
    {
        return $this->hasMany(DnsRecord::class);
    }

    public function nginxConfigLogs()
    {
        return $this->hasMany(NginxConfigLog::class);
    }

    public function hetznerApiLogs()
    {
        return $this->hasMany(HetznerApiLog::class);
    }

    public function effectivePhpVersion(): string
    {
        return $this->php_version ?: $this->domain->php_version;
    }

    public function effectiveDocumentRoot(): string
    {
        return $this->document_root ?: $this->domain->document_root;
    }
}
