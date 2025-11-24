<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'status',
        'verification_token',
        'verified_at',
        'hetzner_zone_id',
        'wildcard_ssl_enabled',
        'wildcard_ssl_status',
        'wildcard_ssl_last_issued_at',
        'php_version',
        'document_root',
    ];

    protected $casts = [
        'wildcard_ssl_enabled' => 'boolean',
        'verified_at' => 'datetime',
        'wildcard_ssl_last_issued_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subdomains()
    {
        return $this->hasMany(Subdomain::class);
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
}
