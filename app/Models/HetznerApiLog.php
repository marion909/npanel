<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HetznerApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'subdomain_id',
        'method',
        'endpoint',
        'request_payload',
        'response_code',
        'response_body',
        'success',
        'message',
    ];

    protected $casts = [
        'success' => 'boolean',
        'response_code' => 'integer',
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
