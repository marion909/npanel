<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\DnsRecord;
use App\Models\Subdomain;
use App\Services\HetznerDnsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DnsRecordController extends Controller
{
    public function index(Domain $domain)
    {
        $this->authorizeDomain($domain);
        $records = $domain->dnsRecords()->latest()->paginate(100);
        return response()->json($records);
    }

    public function store(Request $request, Domain $domain, HetznerDnsService $dnsService)
    {
        $this->authorizeDomain($domain);
        $data = $request->validate([
            'type' => 'required|string|in:A,AAAA,CNAME,TXT,MX,NS,SRV',
            'name' => 'required|string',
            'value' => 'required|string',
            'ttl' => 'nullable|integer|min:60',
            'subdomain_id' => 'nullable|integer',
        ]);
        $record = $dnsService->createRecord($domain, $data);
        return response()->json($record, 201);
    }

    public function destroy(Domain $domain, DnsRecord $dnsRecord, HetznerDnsService $dnsService)
    {
        $this->authorizeDomain($domain);
        abort_unless($dnsRecord->domain_id === $domain->id, 404);
        $ok = $dnsService->deleteRecord($dnsRecord);
        return response()->json(['deleted' => $ok]);
    }

    protected function authorizeDomain(Domain $domain): void
    {
        abort_unless($domain->user_id === Auth::id(), 403);
    }
}
