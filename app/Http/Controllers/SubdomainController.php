<?php

namespace App\Http\Controllers;

use App\Jobs\InstallWordPressJob;
use App\Models\Domain;
use App\Models\Subdomain;
use App\Services\SubdomainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SubdomainController extends Controller
{
    public function __construct(
        protected SubdomainService $subdomainService
    ) {}

    /**
     * Store a new subdomain
     */
    public function store(Request $request, Domain $domain)
    {
        $validated = $request->validate([
            'subdomain_name' => 'required|string|max:63|regex:/^[a-z0-9-]+$/',
            'php_version' => 'nullable|string|in:7.4,8.0,8.1,8.2,8.3',
            'document_root' => 'nullable|string',
            'install_wordpress' => 'nullable|boolean',
        ]);

        try {
            // Check if subdomain already exists
            $exists = Subdomain::where('parent_domain_id', $domain->id)
                ->where('subdomain_name', $validated['subdomain_name'])
                ->exists();

            if ($exists) {
                return back()->with('error', "Subdomain '{$validated['subdomain_name']}' already exists for this domain.");
            }

            $subdomain = $this->subdomainService->createSubdomain($domain, $validated);

            // Install WordPress if requested
            if (!empty($validated['install_wordpress'])) {
                Log::info('WordPress installation requested', [
                    'subdomain_id' => $subdomain->id,
                ]);

                // Dispatch WordPress installation job
                InstallWordPressJob::dispatch($subdomain);

                return back()->with([
                    'success' => "Subdomain '{$subdomain->subdomain_name}' created. WordPress is being installed in the background. Check back in a few moments.",
                    'subdomain_id' => $subdomain->id,
                ]);
            }

            Log::info('Subdomain created successfully', [
                'domain_id' => $domain->id,
                'subdomain_id' => $subdomain->id,
                'subdomain_name' => $subdomain->subdomain_name,
            ]);

            return back()->with('success', "Subdomain '{$subdomain->subdomain_name}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create subdomain', [
                'domain_id' => $domain->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to create subdomain: ' . $e->getMessage());
        }
    }

    /**
     * Update a subdomain
     */
    public function update(Request $request, Domain $domain, Subdomain $subdomain)
    {
        $validated = $request->validate([
            'php_version' => 'nullable|string|in:7.4,8.0,8.1,8.2,8.3',
            'document_root' => 'nullable|string',
            'ssl_enabled' => 'nullable|boolean',
        ]);

        try {
            $subdomain = $this->subdomainService->updateSubdomain($subdomain, $validated);

            Log::info('Subdomain updated successfully', [
                'subdomain_id' => $subdomain->id,
                'changes' => $validated,
            ]);

            return back()->with('success', "Subdomain '{$subdomain->subdomain_name}' updated successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to update subdomain', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to update subdomain: ' . $e->getMessage());
        }
    }

    /**
     * Delete a subdomain
     */
    public function destroy(Domain $domain, Subdomain $subdomain)
    {
        // Prevent deletion of default subdomains
        if (in_array($subdomain->subdomain_name, ['www', '@'])) {
            return back()->with('error', "Cannot delete default subdomain '{$subdomain->subdomain_name}'.");
        }

        try {
            $subdomainName = $subdomain->subdomain_name;
            $this->subdomainService->deleteSubdomain($subdomain);

            Log::info('Subdomain deleted successfully', [
                'subdomain_id' => $subdomain->id,
                'subdomain_name' => $subdomainName,
            ]);

            return back()->with('success', "Subdomain '{$subdomainName}' deleted successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to delete subdomain', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to delete subdomain: ' . $e->getMessage());
        }
    }

    /**
     * Get WordPress credentials for a subdomain
     */
    public function wordpressCredentials(Domain $domain, Subdomain $subdomain)
    {
        $cacheKey = 'wordpress_credentials_' . $subdomain->id;
        $credentials = Cache::get($cacheKey);

        if (!$credentials) {
            return response()->json([
                'success' => false,
                'message' => 'WordPress credentials not found or expired.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'credentials' => $credentials,
        ]);
    }

    /**
     * Enable SSL for subdomain
     */
    public function enableSsl(Domain $domain, Subdomain $subdomain)
    {
        try {
            if ($subdomain->ssl_enabled) {
                return back()->with('info', 'SSL is already enabled for this subdomain.');
            }

            // Dispatch SSL certificate issuance job
            \App\Jobs\IssueSslCertificateJob::dispatch($subdomain);

            Log::info('SSL certificate issuance started for subdomain', [
                'subdomain_id' => $subdomain->id,
                'full_domain' => $subdomain->full_domain,
            ]);

            return back()->with('success', "SSL certificate issuance started for '{$subdomain->full_domain}'. This may take a few minutes.");
        } catch (\Exception $e) {
            Log::error('Failed to enable SSL for subdomain', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to enable SSL: ' . $e->getMessage());
        }
    }

    /**
     * Install WordPress on existing subdomain
     */
    public function installWordPress(Domain $domain, Subdomain $subdomain)
    {
        try {
            if ($subdomain->wordpress_installed) {
                return back()->with('info', 'WordPress is already installed on this subdomain.');
            }

            // Dispatch WordPress installation job
            InstallWordPressJob::dispatch($subdomain);

            Log::info('WordPress installation started for subdomain', [
                'subdomain_id' => $subdomain->id,
                'full_domain' => $subdomain->full_domain,
            ]);

            return back()->with([
                'success' => "WordPress installation started for '{$subdomain->full_domain}'. Check back in a few moments.",
                'subdomain_id' => $subdomain->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to install WordPress on subdomain', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to install WordPress: ' . $e->getMessage());
        }
    }
}
