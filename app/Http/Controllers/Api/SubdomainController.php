<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Subdomain;
use App\Services\SubdomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubdomainController extends Controller
{
    public function __construct(
        protected SubdomainService $subdomainService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        $subdomains = $domain->subdomains()->latest()->get();

        return response()->json([
            'data' => $subdomains,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validator = Validator::make($request->all(), [
            'subdomain_name' => ['required', 'string', 'regex:/^[a-z0-9]+([\-]{1}[a-z0-9]+)*$/i'],
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

        // Check for duplicate subdomain
        $exists = $domain->subdomains()
            ->where('subdomain_name', $request->subdomain_name)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Subdomain already exists',
                'errors' => ['subdomain_name' => ['This subdomain already exists for this domain']],
            ], 422);
        }

        try {
            $subdomain = $this->subdomainService->createSubdomain($domain, $validator->validated());

            // Activate subdomain
            $this->subdomainService->activateSubdomain($subdomain);

            return response()->json([
                'message' => 'Subdomain created successfully',
                'data' => $subdomain,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create subdomain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Domain $domain, Subdomain $subdomain): JsonResponse
    {
        $this->authorize('view', $domain);

        if ($subdomain->parent_domain_id !== $domain->id) {
            return response()->json([
                'message' => 'Subdomain does not belong to this domain',
            ], 404);
        }

        return response()->json([
            'data' => $subdomain,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Domain $domain, Subdomain $subdomain): JsonResponse
    {
        $this->authorize('update', $domain);

        if ($subdomain->parent_domain_id !== $domain->id) {
            return response()->json([
                'message' => 'Subdomain does not belong to this domain',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'document_root' => ['sometimes', 'string', 'max:512'],
            'php_version' => ['sometimes', 'string', 'in:7.4,8.0,8.1,8.2,8.3'],
            'ssl_enabled' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updatedSubdomain = $this->subdomainService->updateSubdomain($subdomain, $validator->validated());

            // Reload services
            $this->subdomainService->activateSubdomain($updatedSubdomain);

            return response()->json([
                'message' => 'Subdomain updated successfully',
                'data' => $updatedSubdomain,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update subdomain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Domain $domain, Subdomain $subdomain): JsonResponse
    {
        $this->authorize('delete', $domain);

        if ($subdomain->parent_domain_id !== $domain->id) {
            return response()->json([
                'message' => 'Subdomain does not belong to this domain',
            ], 404);
        }

        try {
            $this->subdomainService->deleteSubdomain($subdomain);

            return response()->json([
                'message' => 'Subdomain deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete subdomain',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
