<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Str;

class DnsValidationService
{
    public function generateToken(Domain $domain): string
    {
        if (!$domain->verification_token) {
            $domain->verification_token = Str::random(32);
            $domain->save();
        }
        return $domain->verification_token;
    }

    public function requiredRecordName(Domain $domain): string
    {
        return '_npanel.' . $domain->name;
    }

    public function verify(Domain $domain): bool
    {
        $expectedName = $this->requiredRecordName($domain);
        $token = $domain->verification_token;
        if (!$token) {
            return false;
        }
        $record = $domain->dnsRecords()
            ->where('type', 'TXT')
            ->where('name', $expectedName)
            ->where('value', $token)
            ->first();
        if ($record) {
            $domain->status = 'active';
            $domain->verified_at = now();
            $domain->save();
            return true;
        }
        return false;
    }
}
