<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMailDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:sync-database {--force : Force sync even if mail DB is not configured}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync domain, mailbox, and alias data from main database to mail MySQL database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting mail database synchronization...');

        // Check if mail database is configured
        if (!env('MAIL_DB_DATABASE') && !$this->option('force')) {
            $this->error('Mail database not configured. Please set MAIL_DB_* variables in .env');
            $this->info('Run setup-mail-database.sh first to create the mail database.');
            return 1;
        }

        try {
            // Test mail database connection
            DB::connection('mail')->getPdo();
            $this->info('✓ Mail database connection successful');
        } catch (\Exception $e) {
            $this->error('Failed to connect to mail database: ' . $e->getMessage());
            return 1;
        }

        // Sync domains
        $this->info('Syncing domains...');
        $domainCount = $this->syncDomains();
        $this->info("✓ Synced {$domainCount} domains");

        // Sync mailboxes
        $this->info('Syncing mailboxes...');
        $mailboxCount = $this->syncMailboxes();
        $this->info("✓ Synced {$mailboxCount} mailboxes");

        // Sync aliases
        $this->info('Syncing mail aliases...');
        $aliasCount = $this->syncAliases();
        $this->info("✓ Synced {$aliasCount} aliases");

        $this->newLine();
        $this->info('Mail database synchronization completed successfully!');
        $this->table(
            ['Type', 'Count'],
            [
                ['Domains', $domainCount],
                ['Mailboxes', $mailboxCount],
                ['Aliases', $aliasCount],
            ]
        );

        Log::info('Mail database synced', [
            'domains' => $domainCount,
            'mailboxes' => $mailboxCount,
            'aliases' => $aliasCount,
        ]);

        return 0;
    }

    /**
     * Sync domains from main database to mail database.
     *
     * @return int
     */
    private function syncDomains(): int
    {
        $domains = DB::table('domains')->get();
        $mailDb = DB::connection('mail');

        $count = 0;
        foreach ($domains as $domain) {
            $mailDb->table('domains')->updateOrInsert(
                ['id' => $domain->id],
                [
                    'id' => $domain->id,
                    'user_id' => $domain->user_id,
                    'domain_name' => $domain->domain_name,
                    'status' => $domain->status,
                    'created_at' => $domain->created_at,
                    'updated_at' => $domain->updated_at,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Sync mailboxes from main database to mail database.
     *
     * @return int
     */
    private function syncMailboxes(): int
    {
        $mailboxes = DB::table('mailboxes')->get();
        $mailDb = DB::connection('mail');

        $count = 0;
        foreach ($mailboxes as $mailbox) {
            $mailDb->table('mailboxes')->updateOrInsert(
                ['id' => $mailbox->id],
                [
                    'id' => $mailbox->id,
                    'domain_id' => $mailbox->domain_id,
                    'email' => $mailbox->email,
                    'password_encrypted' => $mailbox->password_encrypted,
                    'quota_mb' => $mailbox->quota_mb,
                    'used_mb' => $mailbox->used_mb ?? 0,
                    'status' => $mailbox->status,
                    'created_at' => $mailbox->created_at,
                    'updated_at' => $mailbox->updated_at,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Sync mail aliases from main database to mail database.
     *
     * @return int
     */
    private function syncAliases(): int
    {
        $aliases = DB::table('mail_aliases')->get();
        $mailDb = DB::connection('mail');

        $count = 0;
        foreach ($aliases as $alias) {
            $mailDb->table('mail_aliases')->updateOrInsert(
                ['id' => $alias->id],
                [
                    'id' => $alias->id,
                    'domain_id' => $alias->domain_id,
                    'source' => $alias->source,
                    'destination' => $alias->destination,
                    'type' => $alias->type ?? 'alias',
                    'created_at' => $alias->created_at,
                    'updated_at' => $alias->updated_at,
                ]
            );
            $count++;
        }

        return $count;
    }
}
