<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Models\Domain;
use App\Services\DatabaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class DatabaseController extends Controller
{
    public function __construct(
        protected DatabaseService $databaseService
    ) {}

    /**
     * Display databases for a domain
     */
    public function index(Domain $domain)
    {
        // Load databases with size updates
        $databases = $this->databaseService->getDomainDatabases($domain);
        
        // Update sizes in background (this could be optimized with a job)
        foreach ($databases as $database) {
            $this->databaseService->updateDatabaseSize($database);
        }

        return inertia('Databases/Index', [
            'domain' => $domain,
            'databases' => $databases->fresh(), // Reload with updated sizes
        ]);
    }

    /**
     * Store a newly created database
     */
    public function store(Request $request, Domain $domain): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'display_name' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        if ($validator->fails()) {
            return Redirect::back()->withErrors($validator)->withInput();
        }

        try {
            $database = $this->databaseService->createDatabase($domain, $validator->validated());

            return Redirect::route('databases.index', $domain)->with('success', 'Database created successfully.');
        } catch (\Exception $e) {
            \Log::error('Database creation failed', [
                'domain' => $domain->domain_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Redirect::back()->with('error', 'Failed to create database: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display database credentials
     */
    public function show(Domain $domain, Database $database)
    {
        // Ensure database belongs to domain
        if ($database->domain_id !== $domain->id) {
            abort(404);
        }

        // Update size
        $this->databaseService->updateDatabaseSize($database);

        return response()->json([
            'database' => $database->fresh(),
        ]);
    }

    /**
     * Remove the specified database
     */
    public function destroy(Domain $domain, Database $database): RedirectResponse
    {
        // Ensure database belongs to domain
        if ($database->domain_id !== $domain->id) {
            abort(404);
        }

        try {
            $this->databaseService->deleteDatabase($database);

            return Redirect::route('databases.index', $domain)->with('success', 'Database deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Database deletion failed', [
                'database' => $database->database_name,
                'error' => $e->getMessage()
            ]);
            return Redirect::back()->with('error', 'Failed to delete database: ' . $e->getMessage());
        }
    }

    /**
     * Suspend database
     */
    public function suspend(Domain $domain, Database $database): RedirectResponse
    {
        if ($database->domain_id !== $domain->id) {
            abort(404);
        }

        try {
            $this->databaseService->suspendDatabase($database);

            return Redirect::route('databases.index', $domain)->with('success', 'Database suspended successfully.');
        } catch (\Exception $e) {
            \Log::error('Database suspension failed', [
                'database' => $database->database_name,
                'error' => $e->getMessage()
            ]);
            return Redirect::back()->with('error', 'Failed to suspend database: ' . $e->getMessage());
        }
    }

    /**
     * Resume suspended database
     */
    public function resume(Domain $domain, Database $database): RedirectResponse
    {
        if ($database->domain_id !== $domain->id) {
            abort(404);
        }

        try {
            $this->databaseService->resumeDatabase($database);

            return Redirect::route('databases.index', $domain)->with('success', 'Database resumed successfully.');
        } catch (\Exception $e) {
            \Log::error('Database resume failed', [
                'database' => $database->database_name,
                'error' => $e->getMessage()
            ]);
            return Redirect::back()->with('error', 'Failed to resume database: ' . $e->getMessage());
        }
    }
}

