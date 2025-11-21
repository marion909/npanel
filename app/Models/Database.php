<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Database extends Model
{
    protected $fillable = [
        'domain_id',
        'database_name',
        'display_name',
        'mysql_user',
        'mysql_password_encrypted',
        'status',
        'size_mb',
    ];

    protected $casts = [
        'size_mb' => 'integer',
    ];

    protected $hidden = [
        'mysql_password_encrypted',
    ];

    protected $appends = [
        'mysql_password',
    ];

    /**
     * Get the domain that owns this database
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get decrypted MySQL password
     */
    public function getMysqlPasswordAttribute(): string
    {
        return Crypt::decryptString($this->mysql_password_encrypted);
    }

    /**
     * Set encrypted MySQL password
     */
    public function setMysqlPasswordAttribute(string $value): void
    {
        $this->attributes['mysql_password_encrypted'] = Crypt::encryptString($value);
    }

    /**
     * Generate MySQL database name from domain
     * Format: {sanitized_domain}_{display_name}
     */
    public static function generateDatabaseName(Domain $domain, string $displayName): string
    {
        $sanitizedDomain = str_replace(['.', '-'], '_', $domain->domain_name);
        $sanitizedName = str_replace(['.', '-', ' '], '_', strtolower($displayName));
        return substr($sanitizedDomain . '_' . $sanitizedName, 0, 64); // MySQL max db name length
    }

    /**
     * Generate MySQL username
     * Format: db_{sanitized_domain}_{display_name}
     */
    public static function generateUsername(Domain $domain, string $displayName): string
    {
        $sanitizedDomain = str_replace(['.', '-'], '_', $domain->domain_name);
        $sanitizedName = str_replace(['.', '-', ' '], '_', strtolower($displayName));
        return substr('db_' . $sanitizedDomain . '_' . $sanitizedName, 0, 32); // MySQL max username length
    }

    /**
     * Generate random secure password
     */
    public static function generatePassword(int $length = 16): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        $maxIndex = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $maxIndex)];
        }
        
        return $password;
    }
}

