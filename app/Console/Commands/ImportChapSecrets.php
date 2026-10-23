<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Mikrotik;
use App\Models\VpnUser;
use App\Services\ChapSecretsGeneratorService;
use App\Services\IpAllocatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports accounts from an existing /etc/ppp/chap-secrets into the panel.
 *
 * The panel treats the database as the single source of truth and rewrites
 * chap-secrets wholesale, so accounts created outside the panel (typically the
 * first user provisioned by hwdsl2/setup-ipsec-vpn) would be dropped on the
 * next deploy. Run this once after installing the panel to adopt them.
 */
class ImportChapSecrets extends Command
{
    protected $signature = 'vpnpanel:import-chap-secrets
                            {--file= : path to a chap-secrets file (default: the configured one)}
                            {--dry-run : show what would be imported without writing}';

    protected $description = 'Import existing chap-secrets accounts into the panel database';

    public function handle(IpAllocatorService $ipAllocator, ChapSecretsGeneratorService $generator): int
    {
        $path = (string) ($this->option('file') ?? config('vpnpanel.paths.chap_secrets'));
        $contents = @file_get_contents($path);

        if ($contents === false) {
            $this->error("Cannot read chap-secrets file: {$path}");

            return self::FAILURE;
        }

        $entries = $this->parse($contents);

        if ($entries === []) {
            $this->warn("No usable entries found in {$path}.");

            return self::SUCCESS;
        }

        foreach ($generator->validate($contents) as $problem) {
            $this->warn('source file: ' . $problem);
        }

        // Decide once per entry so the preview table and the import loop share
        // the same verdicts and can never disagree.
        $decisions = array_map(
            fn (array $entry): array => [
                'entry' => $entry,
                'action' => $this->decide($entry, $ipAllocator),
            ],
            $entries,
        );

        $this->table(
            ['username', 'address', 'action'],
            array_map(
                static fn (array $d): array => [$d['entry']['username'], $d['entry']['ip'], $d['action']['label']],
                $decisions,
            ),
        );

        $importable = array_values(array_filter(
            $decisions,
            static fn (array $d): bool => $d['action']['import'],
        ));

        if ($this->option('dry-run')) {
            $this->info(sprintf('Dry run: %d account(s) would be imported.', count($importable)));

            return self::SUCCESS;
        }

        if ($importable === []) {
            $this->info('Nothing to import.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($importable): void {
            foreach ($importable as $decision) {
                $entry = $decision['entry'];

                VpnUser::create([
                    'username' => $entry['username'],
                    'password' => $entry['password'],
                    'assigned_ip' => $entry['assignedIp'],
                    'notes' => 'Imported from chap-secrets on ' . now()->toDateString(),
                    'is_enabled' => true,
                ]);
            }
        });

        $this->info(sprintf('Imported %d account(s) into the panel.', count($importable)));
        $this->line('Run "php artisan vpnpanel:deploy-configs" to rewrite chap-secrets from the database.');

        return self::SUCCESS;
    }

    /**
     * Parse chap-secrets into normalised entries, dropping unusable lines.
     *
     * @return list<array{username: string, password: string, ip: string, assignedIp: string|null}>
     */
    private function parse(string $contents): array
    {
        $entries = [];

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! preg_match('/^"([^"]+)"\s+(\S+)\s+"([^"]*)"\s+(\S+)$/', $line, $matches)) {
                continue;
            }

            [, $username, , $password, $ip] = $matches;

            if (! preg_match('/^[A-Za-z0-9_.\-]+$/', $username)) {
                continue;
            }

            $isStatic = $ip !== '*'
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

            $entries[] = [
                'username' => $username,
                'password' => $password,
                'ip' => $ip,
                'assignedIp' => $isStatic ? $ip : null,
            ];
        }

        return $entries;
    }

    /**
     * Decide whether an entry should be imported, and why.
     *
     * @param  array{username: string, password: string, ip: string, assignedIp: string|null}  $entry
     * @return array{import: bool, label: string}
     */
    private function decide(array $entry, IpAllocatorService $ipAllocator): array
    {
        $username = $entry['username'];

        if (Mikrotik::withTrashed()->where('vpn_username', $username)->exists()) {
            return ['import' => false, 'label' => 'skip (owned by MikroTik)'];
        }

        if (VpnUser::withTrashed()->where('username', $username)->exists()) {
            return ['import' => false, 'label' => 'skip (already in panel)'];
        }

        // A static address somebody else already holds must not be duplicated;
        // the unique index would abort the whole transaction.
        if ($entry['assignedIp'] !== null && $ipAllocator->isIpInUse($entry['assignedIp'])) {
            return ['import' => false, 'label' => 'skip (IP already allocated)'];
        }

        return [
            'import' => true,
            'label' => $entry['assignedIp'] !== null ? 'import (static IP)' : 'import (dynamic IP)',
        ];
    }
}
