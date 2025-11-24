<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Subdomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubdomainController extends Controller
{
    public function index(Domain $domain)
    {
        $this->authorizeDomain($domain);
        $subs = $domain->subdomains()->paginate(50);
        return response()->json($subs);
    }

    public function store(Request $request, Domain $domain)
    {
        $this->authorizeDomain($domain);
        $data = $request->validate([
            'name' => 'required|string|lowercase|regex:/^[a-z0-9-]+$/',
            'php_version' => 'nullable|string',
            'document_root' => 'nullable|string',
            'nginx_enabled' => 'boolean',
        ]);
        $data['full_name'] = $data['name'] . '.' . $domain->name;
        abort_if(Subdomain::where('full_name', $data['full_name'])->exists(), 422, 'Subdomain exists');
        $sub = $domain->subdomains()->create($data);
        return response()->json($sub, 201);
    }

    public function show(Domain $domain, Subdomain $subdomain)
    {
        $this->authorizeDomain($domain);
        abort_unless($subdomain->domain_id === $domain->id, 404);
        $subdomain->load('dnsRecords');
        return response()->json($subdomain);
    }

    public function update(Request $request, Domain $domain, Subdomain $subdomain)
    {
        $this->authorizeDomain($domain);
        abort_unless($subdomain->domain_id === $domain->id, 404);
        $data = $request->validate([
            'php_version' => 'sometimes|string',
            'document_root' => 'sometimes|string',
            'nginx_enabled' => 'sometimes|boolean',
        ]);
        $subdomain->update($data);
        return response()->json($subdomain);
    }

    public function destroy(Domain $domain, Subdomain $subdomain)
    {
        $this->authorizeDomain($domain);
        abort_unless($subdomain->domain_id === $domain->id, 404);
        $subdomain->delete();
        return response()->json(['deleted' => true]);
    }

    protected function authorizeDomain(Domain $domain): void
    {
        abort_unless($domain->user_id === Auth::id(), 403);
    }
}
