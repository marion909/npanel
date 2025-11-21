<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\MailAlias;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Exception;

class MailService
{
    /**
     * Create a new mailbox with encrypted password and Maildir structure.
     *
     * @param Domain $domain
     * @param string $localpart
     * @param string $password
     * @param int $quotaMb
     * @return Mailbox
     * @throws Exception
     */
    public function createMailbox(Domain $domain, string $localpart, string $password, int $quotaMb = 1000): Mailbox
    {
        $email = $localpart . '@' . $domain->domain_name;

        // Check if mailbox already exists
        if (Mailbox::where('email', $email)->exists()) {
            throw new Exception("Mailbox {$email} already exists.");
        }

        // Generate SHA512-CRYPT password hash using doveadm
        $passwordHash = $this->generatePasswordHash($password);

        // Create mailbox record
        $mailbox = Mailbox::create([
            'domain_id' => $domain->id,
            'email' => $email,
            'password_encrypted' => $passwordHash,
            'quota_mb' => $quotaMb,
            'used_mb' => 0,
            'status' => 'active',
        ]);

        // Create Maildir structure
        $this->createMaildir($domain->domain_name, $localpart);

        Log::info("Created mailbox {$email} with {$quotaMb}MB quota");

        return $mailbox;
    }

    /**
     * Update mailbox password and/or quota.
     *
     * @param Mailbox $mailbox
     * @param string|null $password
     * @param int|null $quotaMb
     * @return Mailbox
     * @throws Exception
     */
    public function updateMailbox(Mailbox $mailbox, ?string $password = null, ?int $quotaMb = null): Mailbox
    {
        if ($password !== null) {
            $mailbox->password_encrypted = $this->generatePasswordHash($password);
        }

        if ($quotaMb !== null) {
            $mailbox->quota_mb = $quotaMb;
        }

        $mailbox->save();

        Log::info("Updated mailbox {$mailbox->email}");

        return $mailbox;
    }

    /**
     * Delete a mailbox after checking for alias dependencies.
     *
     * @param Mailbox $mailbox
     * @return void
     * @throws Exception
     */
    public function deleteMailbox(Mailbox $mailbox): void
    {
        // Check if any aliases point to this mailbox
        $aliasCount = MailAlias::where('destination', $mailbox->email)->count();
        
        if ($aliasCount > 0) {
            throw new Exception("Cannot delete mailbox {$mailbox->email}: {$aliasCount} alias(es) point to this mailbox. Delete them first.");
        }

        $domain = $mailbox->domain;
        $localpart = $mailbox->localpart;
        $email = $mailbox->email;

        // Delete Maildir
        $maildirPath = "/var/vmail/{$domain->domain_name}/{$localpart}";
        if (File::exists($maildirPath)) {
            File::deleteDirectory($maildirPath);
            Log::info("Deleted Maildir: {$maildirPath}");
        }

        // Delete database record
        $mailbox->delete();

        Log::info("Deleted mailbox {$email}");
    }

    /**
     * Calculate actual disk usage for a mailbox.
     *
     * @param Mailbox $mailbox
     * @return int Size in MB
     */
    public function calculateMailboxSize(Mailbox $mailbox): int
    {
        $domain = $mailbox->domain;
        $localpart = $mailbox->localpart;
        $maildirPath = "/var/vmail/{$domain->domain_name}/{$localpart}";

        if (!File::exists($maildirPath)) {
            return 0;
        }

        try {
            // Get size in bytes using du -sb
            $result = Process::run("du -sb {$maildirPath}");
            
            if ($result->successful()) {
                // Parse output: "1234567    /var/vmail/..."
                $output = trim($result->output());
                $sizeBytes = (int) explode("\t", $output)[0];
                $sizeMb = (int) ceil($sizeBytes / 1024 / 1024);

                // Update cached value
                $mailbox->update(['used_mb' => $sizeMb]);

                Log::info("Calculated mailbox size for {$mailbox->email}: {$sizeMb}MB");

                return $sizeMb;
            }
        } catch (Exception $e) {
            Log::error("Failed to calculate mailbox size for {$mailbox->email}: " . $e->getMessage());
        }

        return $mailbox->used_mb; // Return cached value on error
    }

    /**
     * Generate DNS records for a domain (MX, SPF, DKIM, DMARC).
     *
     * @param Domain $domain
     * @return array
     */
    public function generateDnsRecords(Domain $domain): array
    {
        $mailHostname = $domain->mail_hostname;
        $domainName = $domain->domain_name;

        $records = [
            'MX' => [
                'type' => 'MX',
                'name' => '@',
                'value' => "10 {$mailHostname}",
                'ttl' => 3600,
            ],
            'SPF' => [
                'type' => 'TXT',
                'name' => '@',
                'value' => "v=spf1 mx ~all",
                'ttl' => 3600,
            ],
            'DMARC' => [
                'type' => 'TXT',
                'name' => '_dmarc',
                'value' => "v=DMARC1; p=quarantine; rua=mailto:postmaster@{$domainName}",
                'ttl' => 3600,
            ],
        ];

        // Read DKIM public key if available
        $dkimKeyPath = "/etc/opendkim/keys/{$domainName}/default.txt";
        if (File::exists($dkimKeyPath)) {
            $dkimContent = File::get($dkimKeyPath);
            
            // Parse DKIM record (format: default._domainkey.example.com. IN TXT "v=DKIM1; ..." "..." )
            // Extract the actual TXT value between quotes
            preg_match_all('/"([^"]+)"/', $dkimContent, $matches);
            if (!empty($matches[1])) {
                $dkimValue = implode('', $matches[1]);
                
                $records['DKIM'] = [
                    'type' => 'TXT',
                    'name' => 'default._domainkey',
                    'value' => $dkimValue,
                    'ttl' => 3600,
                ];
            }
        } else {
            Log::warning("DKIM key not found for {$domainName} at {$dkimKeyPath}");
        }

        return $records;
    }

    /**
     * Create alias or catch-all forwarder.
     *
     * @param Domain $domain
     * @param string $source Email address or @domain for catch-all
     * @param string $destination Target email address
     * @param string $type 'alias' or 'catchall'
     * @return MailAlias
     * @throws Exception
     */
    public function createAlias(Domain $domain, string $source, string $destination, string $type = 'alias'): MailAlias
    {
        // Validate catch-all format
        if ($type === 'catchall' && !str_starts_with($source, '@')) {
            throw new Exception("Catch-all source must start with @ (e.g., @{$domain->domain_name})");
        }

        // Check for duplicate
        if (MailAlias::where('source', $source)->where('domain_id', $domain->id)->exists()) {
            throw new Exception("Alias/catch-all for {$source} already exists.");
        }

        $alias = MailAlias::create([
            'domain_id' => $domain->id,
            'source' => $source,
            'destination' => $destination,
            'type' => $type,
        ]);

        Log::info("Created {$type} from {$source} to {$destination}");

        return $alias;
    }

    /**
     * Delete an alias.
     *
     * @param MailAlias $alias
     * @return void
     */
    public function deleteAlias(MailAlias $alias): void
    {
        $source = $alias->source;
        $alias->delete();
        
        Log::info("Deleted alias {$source}");
    }

    /**
     * Generate SHA512-CRYPT password hash using doveadm or fallback to PHP crypt().
     *
     * @param string $password
     * @return string
     * @throws Exception
     */
    private function generatePasswordHash(string $password): string
    {
        // Check if doveadm is available
        $checkResult = Process::run('which doveadm');
        
        if ($checkResult->successful()) {
            // Use doveadm if available
            $escapedPassword = escapeshellarg($password);
            $result = Process::run("doveadm pw -s SHA512-CRYPT -p {$escapedPassword}");

            if ($result->successful()) {
                return trim($result->output());
            }
            
            Log::warning("doveadm failed, falling back to PHP crypt(): " . $result->errorOutput());
        }
        
        // Fallback to PHP's crypt() with SHA512
        // Generate random salt (16 characters for SHA512)
        $salt = substr(str_replace('+', '.', base64_encode(random_bytes(16))), 0, 16);
        $hash = crypt($password, '$6$' . $salt);
        
        if ($hash === false || strlen($hash) < 13) {
            throw new Exception("Failed to generate password hash using PHP crypt().");
        }
        
        Log::info("Generated password hash using PHP crypt() fallback");
        return '{SHA512-CRYPT}' . $hash;
    }

    /**
     * Create Maildir directory structure for a mailbox.
     *
     * @param string $domain
     * @param string $localpart
     * @return void
     * @throws Exception
     */
    private function createMaildir(string $domain, string $localpart): void
    {
        $maildirPath = "/var/vmail/{$domain}/{$localpart}";

        try {
            // Create main directory
            File::makeDirectory($maildirPath, 0750, true, true);

            // Create Maildir structure
            File::makeDirectory("{$maildirPath}/cur", 0750, true, true);
            File::makeDirectory("{$maildirPath}/new", 0750, true, true);
            File::makeDirectory("{$maildirPath}/tmp", 0750, true, true);

            // Set ownership to vmail:vmail (UID 5000)
            Process::run("chown -R vmail:vmail {$maildirPath}");

            Log::info("Created Maildir structure: {$maildirPath}");
        } catch (Exception $e) {
            Log::error("Failed to create Maildir {$maildirPath}: " . $e->getMessage());
            throw new Exception("Failed to create mailbox directory structure.");
        }
    }
}
