<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DnsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'subdomain_id',
        'hetzner_record_id',
        'type',
        'name',
        'value',
        'ttl',
        'status',
    ];

    protected $casts = [
        'ttl' => 'integer',
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
