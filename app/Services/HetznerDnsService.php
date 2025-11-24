<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DnsRecord;
use App\Models\HetznerApiLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HetznerDnsService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('HETZNER_DNS_API_BASE', 'https://dns.hetzner.com/api'), '/');
        $this->token = env('HETZNER_DNS_API_TOKEN', '');
    }

    protected function client()
    {
        return Http::withHeaders([
            'Auth-API-Token' => $this->token,
            'Accept' => 'application/json',
        ]);
    }

    protected function log(?Domain $domain, ?int $subdomainId, string $method, string $endpoint, array $payload, $response): void
    {
        HetznerApiLog::create([
            'domain_id' => $domain?->id,
            'subdomain_id' => $subdomainId,
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'response_code' => $response?->status(),
            'response_body' => $response?->body(),
            'success' => $response?->successful() ?? false,
            'message' => $response?->json('error') ?? null,
        ]);
    }

    public function listZones(): array
    {
        $endpoint = '/v1/zones';
        $response = $this->client()->get($this->baseUrl . $endpoint);
        $this->log(null, null, 'GET', $endpoint, [], $response);
        return $response->json('zones') ?? [];
    }

    public function findZoneIdForDomain(string $domainName): ?string
    {
        $zones = $this->listZones();
        foreach ($zones as $zone) {
            if (($zone['name'] ?? null) === $domainName) {
                return $zone['id'] ?? null;
            }
        }
        return null;
    }

    public function createRecord(Domain $domain, array $data): ?DnsRecord
    {
        $endpoint = '/v1/records';
        $payload = [
            'value' => $data['value'],
            'type' => $data['type'],
            'name' => $data['name'],
            'zone_id' => $domain->hetzner_zone_id,
            'ttl' => $data['ttl'] ?? 3600,
        ];
        $response = $this->client()->post($this->baseUrl . $endpoint, $payload);
        $this->log($domain, null, 'POST', $endpoint, $payload, $response);
        if ($response->successful()) {
            $recordData = $response->json('record');
            return DnsRecord::create([
                'domain_id' => $domain->id,
                'hetzner_record_id' => $recordData['id'] ?? null,
                'type' => $recordData['type'] ?? $data['type'],
                'name' => $recordData['name'] ?? $data['name'],
                'value' => $recordData['value'] ?? $data['value'],
                'ttl' => $recordData['ttl'] ?? $data['ttl'] ?? 3600,
                'status' => 'synced',
            ]);
        }
        return null;
    }

    public function deleteRecord(DnsRecord $record): bool
    {
        if (!$record->hetzner_record_id) {
            return false;
        }
        $endpoint = '/v1/records/' . $record->hetzner_record_id;
        $response = $this->client()->delete($this->baseUrl . $endpoint);
        $this->log($record->domain, $record->subdomain_id, 'DELETE', $endpoint, [], $response);
        if ($response->successful()) {
            $record->delete();
            return true;
        }
        return false;
    }

    public function ensureARecord(Domain $domain, string $ipv4Address): ?DnsRecord
    {
        $existing = $domain->dnsRecords()->where('type', 'A')->where('name', $domain->name)->first();
        if ($existing) {
            if ($existing->value === $ipv4Address) {
                return $existing; // already correct
            }
            $this->deleteRecord($existing);
        }
        return $this->createRecord($domain, [
            'type' => 'A',
            'name' => $domain->name,
            'value' => $ipv4Address,
            'ttl' => 3600,
        ]);
    }

    public function createVerificationTxtRecord(Domain $domain): ?DnsRecord
    {
        $token = $domain->verification_token ?: Str::random(24);
        if (!$domain->verification_token) {
            $domain->verification_token = $token;
            $domain->save();
        }
        return $this->createRecord($domain, [
            'type' => 'TXT',
            'name' => '_npanel.' . $domain->name,
            'value' => $token,
            'ttl' => 300,
        ]);
    }
}
