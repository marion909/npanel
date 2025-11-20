<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ActivateDomainJob;
use App\Jobs\IssueSslCertificateJob;
use App\Models\Domain;
use App\Services\DomainService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DomainController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DomainService $domainService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $domains = Auth::user()->domains()
            ->with(['subdomains', 'sslCertificate', 'phpFpmPool'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $domains,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain_name' => ['required', 'string', 'regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', 'unique:domains,domain_name'],
            'document_root' => ['nullable', 'string', 'max:512'],
            'php_version' => ['nullable', 'string', 'in:7.4,8.0,8.1,8.2,8.3'],
            'ssl_enabled' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
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

            return response()->json([
                'message' => 'Domain created successfully. Activation in progress.',
                'data' => $domain->load(['subdomains', 'phpFpmPool']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create domain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        return response()->json([
            'data' => $domain->load(['subdomains', 'sslCertificate', 'phpFpmPool', 'nginxConfig']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validator = Validator::make($request->all(), [
            'document_root' => ['sometimes', 'string', 'max:512'],
            'php_version' => ['sometimes', 'string', 'in:7.4,8.0,8.1,8.2,8.3'],
            'status' => ['sometimes', 'string', 'in:active,suspended,deleted'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updatedDomain = $this->domainService->updateDomain($domain, $validator->validated());

            // Reload services
            ActivateDomainJob::dispatch($updatedDomain);

            return response()->json([
                'message' => 'Domain updated successfully',
                'data' => $updatedDomain->load(['subdomains', 'phpFpmPool']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update domain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Domain $domain): JsonResponse
    {
        $this->authorize('delete', $domain);

        try {
            $this->domainService->deleteDomain($domain);

            return response()->json([
                'message' => 'Domain deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete domain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suspend domain
     */
    public function suspend(Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        try {
            $this->domainService->suspendDomain($domain);

            return response()->json([
                'message' => 'Domain suspended successfully',
                'data' => $domain->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to suspend domain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resume suspended domain
     */
    public function resume(Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        try {
            $this->domainService->resumeDomain($domain);

            return response()->json([
                'message' => 'Domain resumed successfully',
                'data' => $domain->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to resume domain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Issue SSL certificate
     */
    public function issueSSL(Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        try {
            IssueSslCertificateJob::dispatch($domain);

            return response()->json([
                'message' => 'SSL certificate issuance queued',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to queue SSL issuance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
