<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Domain extends Model
{
    protected $fillable = [
        'user_id',
        'domain_name',
        'document_root',
        'nginx_config_path',
        'php_version',
        'php_fpm_pool',
        'ssl_enabled',
        'ssl_cert_path',
        'ssl_key_path',
        'ssl_expiry_date',
        'mail_hostname',
        'status',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'ssl_expiry_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subdomains(): HasMany
    {
        return $this->hasMany(Subdomain::class, 'parent_domain_id');
    }

    public function sslCertificate(): HasOne
    {
        return $this->hasOne(SslCertificate::class);
    }

    public function phpFpmPool(): HasOne
    {
        return $this->hasOne(PhpFpmPool::class);
    }

    public function nginxConfig(): HasOne
    {
        return $this->hasOne(NginxConfig::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    public function mailAliases(): HasMany
    {
        return $this->hasMany(MailAlias::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSslExpiringSoon(int $days = 30): bool
    {
        if (!$this->ssl_enabled || !$this->ssl_expiry_date) {
            return false;
        }

        return $this->ssl_expiry_date->diffInDays(now()) <= $days;
    }

    /**
     * Get the mail hostname for this domain.
     * Returns the configured value or defaults to mail.{domain}.
     */
    public function getMailHostnameAttribute(): string
    {
        return $this->attributes['mail_hostname'] ?? "mail.{$this->domain_name}";
    }
}
