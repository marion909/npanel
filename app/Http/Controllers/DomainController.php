<?php

namespace App\Http\Controllers;

use App\Jobs\ActivateDomainJob;
use App\Jobs\IssueSslCertificateJob;
use App\Models\Domain;
use App\Services\DomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class DomainController extends Controller
{
    public function __construct(
        protected DomainService $domainService
    ) {}

    /**
     * Store a newly created domain
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'domain_name' => ['required', 'string', 'regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', 'unique:domains,domain_name'],
            'document_root' => ['nullable', 'string', 'max:512'],
            'php_version' => ['nullable', 'string', 'in:7.4,8.0,8.1,8.2,8.3'],
            'ssl_enabled' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Redirect::back()->withErrors($validator)->withInput();
        }

        try {
            // Create domain
            $domain = $this->domainService->createDomain(Auth::user(), $validator->validated());

            // Dispatch activation job
            ActivateDomainJob::dispatch($domain);

            // Dispatch SSL issuance if enabled
            if ($request->boolean('ssl_enabled')) {
                IssueSslCertificateJob::dispatch($domain)->delay(now()->addMinutes(1));
            }

            return Redirect::route('dashboard')->with('success', 'Domain created successfully. Activation in progress.');
        } catch (\Exception $e) {
            \Log::error('Domain creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return Redirect::back()->with('error', 'Failed to create domain: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified domain
     */
    public function show(Domain $domain)
    {
        // Load relationships
        $domain->load(['subdomains', 'sslCertificate']);

        return inertia('Domains/Show', [
            'domain' => $domain,
        ]);
    }

    /**
     * Remove the specified domain
     */
    public function destroy(Domain $domain): RedirectResponse
    {
        try {
            // Delete the domain (this will also trigger cleanup in the service)
            $this->domainService->deleteDomain($domain);

            return Redirect::route('dashboard')->with('success', 'Domain deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Domain deletion failed', ['domain' => $domain->domain_name, 'error' => $e->getMessage()]);
            return Redirect::back()->with('error', 'Failed to delete domain: ' . $e->getMessage());
        }
    }

    /**
     * Issue SSL certificate for domain
     */
    public function issueSSL(Domain $domain): RedirectResponse
    {
        try {
            // Check if domain is active
            if ($domain->status !== 'active') {
                return Redirect::back()->with('error', 'Domain must be active before issuing SSL certificate.');
            }

            // Check if SSL already enabled
            if ($domain->ssl_enabled) {
                return Redirect::back()->with('info', 'SSL is already enabled for this domain.');
            }

            // Dispatch SSL issuance job
            IssueSslCertificateJob::dispatch($domain);

            return Redirect::back()->with('success', 'SSL certificate issuance started. This may take a few minutes.');
        } catch (\Exception $e) {
            \Log::error('SSL issuance failed', ['domain' => $domain->domain_name, 'error' => $e->getMessage()]);
            return Redirect::back()->with('error', 'Failed to issue SSL: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified domain
     */
    public function edit(Domain $domain)
    {
        return inertia('Domains/Edit', [
            'domain' => $domain,
            'phpVersions' => ['7.4', '8.0', '8.1', '8.2', '8.3'],
        ]);
    }

    /**
     * Update the specified domain
     */
    public function update(Request $request, Domain $domain): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'document_root' => ['nullable', 'string', 'max:512'],
            'php_version' => ['required', 'string', 'in:7.4,8.0,8.1,8.2,8.3'],
        ]);

        if ($validator->fails()) {
            return Redirect::back()->withErrors($validator)->withInput();
        }

        try {
            $this->domainService->updateDomain($domain, $validator->validated());

            return Redirect::route('domains.show', $domain)->with('success', 'Domain updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Domain update failed', ['domain' => $domain->domain_name, 'error' => $e->getMessage()]);
            return Redirect::back()->with('error', 'Failed to update domain: ' . $e->getMessage())->withInput();
        }
    }
}
