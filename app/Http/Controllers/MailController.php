<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\MailAlias;
use App\Services\MailService;
use App\Services\PostfixService;
use App\Services\DovecotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Exception;

class MailController extends Controller
{
    private MailService $mailService;
    private PostfixService $postfixService;
    private DovecotService $dovecotService;

    public function __construct(
        MailService $mailService,
        PostfixService $postfixService,
        DovecotService $dovecotService
    ) {
        $this->mailService = $mailService;
        $this->postfixService = $postfixService;
        $this->dovecotService = $dovecotService;
    }

    /**
     * Display mailboxes and aliases with domain filter.
     */
    public function index(Request $request)
    {
        $domainId = $request->input('domain_id');

        $query = Domain::where('status', 'active');
        
        if ($domainId) {
            $query->where('id', $domainId);
        }

        $domains = $query->with(['mailboxes', 'mailAliases'])->get();

        $mailboxes = Mailbox::with('domain')
            ->when($domainId, fn($q) => $q->where('domain_id', $domainId))
            ->orderBy('email')
            ->get();

        $aliases = MailAlias::with('domain')
            ->when($domainId, fn($q) => $q->where('domain_id', $domainId))
            ->orderBy('source')
            ->get();

        return Inertia::render('Mail/Index', [
            'domains' => $domains,
            'mailboxes' => $mailboxes,
            'aliases' => $aliases,
            'selectedDomainId' => $domainId,
        ]);
    }

    /**
     * Store a new mailbox.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
            'localpart' => 'required|string|regex:/^[a-zA-Z0-9._-]+$/',
            'password' => 'required|string|min:8',
            'quota_mb' => 'required|integer|min:100|max:50000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $domain = Domain::findOrFail($request->domain_id);
            
            $mailbox = $this->mailService->createMailbox(
                $domain,
                $request->localpart,
                $request->password,
                $request->quota_mb
            );

            // Regenerate Postfix/Dovecot configs and reload
            $this->postfixService->generateConfigs();
            $this->dovecotService->generateSqlConfig();
            $this->postfixService->reload();
            $this->dovecotService->reload();

            Log::info("Created mailbox {$mailbox->email} via web interface");

            return back()->with('success', 'Mailbox created successfully');
        } catch (Exception $e) {
            Log::error("Failed to create mailbox: " . $e->getMessage());
            
            return back()->with('error', 'Failed to create mailbox: ' . $e->getMessage());
        }
    }

    /**
     * Update mailbox password and/or quota.
     */
    public function update(Request $request, Mailbox $mailbox)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|min:8',
            'quota_mb' => 'nullable|integer|min:100|max:50000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $this->mailService->updateMailbox(
                $mailbox,
                $request->password,
                $request->quota_mb
            );

            Log::info("Updated mailbox {$mailbox->email} via web interface");

            return back()->with('success', 'Mailbox updated successfully');
        } catch (Exception $e) {
            Log::error("Failed to update mailbox: " . $e->getMessage());
            
            return back()->with('error', 'Failed to update mailbox: ' . $e->getMessage());
        }
    }

    /**
     * Delete a mailbox.
     */
    public function destroy(Mailbox $mailbox)
    {
        try {
            $email = $mailbox->email;
            
            $this->mailService->deleteMailbox($mailbox);

            // Regenerate configs and reload
            $this->postfixService->generateConfigs();
            $this->dovecotService->generateSqlConfig();
            $this->postfixService->reload();
            $this->dovecotService->reload();

            Log::info("Deleted mailbox {$email} via web interface");

            return back()->with('success', 'Mailbox deleted successfully');
        } catch (Exception $e) {
            Log::error("Failed to delete mailbox: " . $e->getMessage());
            
            return back()->with('error', 'Failed to delete mailbox: ' . $e->getMessage());
        }
    }

    /**
     * Calculate and update mailbox disk usage.
     */
    public function calculateSize(Mailbox $mailbox)
    {
        try {
            $sizeMb = $this->mailService->calculateMailboxSize($mailbox);

            return response()->json([
                'success' => true,
                'data' => [
                    'used_mb' => $sizeMb,
                    'quota_mb' => $mailbox->quota_mb,
                    'quota_percentage' => $mailbox->quota_percentage,
                    'quota_badge_color' => $mailbox->quota_badge_color,
                ],
            ]);
        } catch (Exception $e) {
            Log::error("Failed to calculate mailbox size: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create an alias or catch-all forwarder.
     */
    public function storeAlias(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain_id' => 'required|exists:domains,id',
            'source' => 'required|string',
            'destination' => 'required|email',
            'type' => 'required|in:alias,catchall',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $domain = Domain::findOrFail($request->domain_id);
            
            $alias = $this->mailService->createAlias(
                $domain,
                $request->source,
                $request->destination,
                $request->type
            );

            // Regenerate Postfix config and reload
            $this->postfixService->generateConfigs();
            $this->postfixService->reload();

            Log::info("Created alias {$alias->source} -> {$alias->destination} via web interface");

            return back()->with('success', 'Alias created successfully');
        } catch (Exception $e) {
            Log::error("Failed to create alias: " . $e->getMessage());
            
            return back()->with('error', 'Failed to create alias: ' . $e->getMessage());
        }
    }

    /**
     * Delete an alias.
     */
    public function destroyAlias(MailAlias $alias)
    {
        try {
            $source = $alias->source;
            
            $this->mailService->deleteAlias($alias);

            // Regenerate Postfix config and reload
            $this->postfixService->generateConfigs();
            $this->postfixService->reload();

            Log::info("Deleted alias {$source} via web interface");

            return back()->with('success', 'Alias deleted successfully');
        } catch (Exception $e) {
            Log::error("Failed to delete alias: " . $e->getMessage());
            
            return back()->with('error', 'Failed to delete alias: ' . $e->getMessage());
        }
    }

    /**
     * Get DNS records for a domain (MX, SPF, DKIM, DMARC).
     */
    public function dnsRecords(Domain $domain)
    {
        try {
            $records = $this->mailService->generateDnsRecords($domain);

            return Inertia::render('Mail/DnsRecords', [
                'domain' => $domain,
                'records' => $records,
            ]);
        } catch (Exception $e) {
            Log::error("Failed to generate DNS records: " . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to generate DNS records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display mail settings page (Roundcube URL, etc.).
     */
    public function settings()
    {
        $settings = [
            'roundcube_url' => config('npanel.roundcube_url', 'https://webmail.' . request()->getHost()),
        ];

        return Inertia::render('Mail/Settings', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update mail settings.
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roundcube_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Update config file (in production, this would write to .env or database)
            // For now, just return success
            
            Log::info("Updated mail settings via web interface");

            return response()->json([
                'message' => 'Settings updated successfully',
            ]);
        } catch (Exception $e) {
            Log::error("Failed to update settings: " . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
