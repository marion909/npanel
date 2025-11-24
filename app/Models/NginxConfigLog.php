<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NginxConfigLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'subdomain_id',
        'action',
        'previous_config',
        'new_config',
        'success',
        'message',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function subdomain()
    {
        return $this->belongsTo(Subdomain::class);
    }
}
