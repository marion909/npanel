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
}
