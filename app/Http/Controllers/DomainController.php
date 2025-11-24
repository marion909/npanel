<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\DnsValidationService;
use App\Services\WildcardSslService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $domains = Domain::where('user_id', Auth::id())->with('subdomains')->paginate(20);
        return response()->json($domains);
    }

    public function show(Domain $domain)
    {
        $this->authorizeDomain($domain);
        $domain->load(['subdomains','dnsRecords','nginxConfigLogs' => fn($q) => $q->latest()->limit(20)]);
        return response()->json($domain);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|lowercase|regex:/^[a-z0-9.-]+$/|unique:domains,name',
            'php_version' => 'nullable|string',
            'wildcard_ssl_enabled' => 'boolean',
            'document_root' => 'nullable|string',
        ]);
        $data['user_id'] = Auth::id();
        $domain = Domain::create($data);
        return response()->json($domain, 201);
    }

    public function update(Request $request, Domain $domain)
    {
        $this->authorizeDomain($domain);
        $data = $request->validate([
            'php_version' => 'sometimes|string',
            'wildcard_ssl_enabled' => 'sometimes|boolean',
            'document_root' => 'sometimes|string',
        ]);
        $domain->update($data);
        return response()->json($domain);
    }

    public function destroy(Domain $domain)
    {
        $this->authorizeDomain($domain);
        $domain->delete();
        return response()->json(['deleted' => true]);
    }

    public function verify(Domain $domain, DnsValidationService $validationService)
    {
        $this->authorizeDomain($domain);
        $ok = $validationService->verify($domain);
        return response()->json(['verified' => $ok, 'status' => $domain->status]);
    }

    public function requestWildcard(Domain $domain, WildcardSslService $sslService)
    {
        $this->authorizeDomain($domain);
        $ok = $sslService->requestWildcardCertificate($domain);
        return response()->json(['requested' => $ok, 'ssl_status' => $domain->wildcard_ssl_status]);
    }

    protected function authorizeDomain(Domain $domain): void
    {
        abort_unless($domain->user_id === Auth::id(), 403);
    }
}
