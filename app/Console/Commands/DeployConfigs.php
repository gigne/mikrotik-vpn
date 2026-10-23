<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChapSecretsDeployService;
use App\Services\NginxDeployService;
use Illuminate\Console\Command;

/**
 * Regenerates the panel-managed files from the database.
 *
 * The database is the single source of truth for /etc/ppp/chap-secrets and the
 * nginx port-forwarding configs. Run this after seeding, after a restore, or
 * any time the files drift from the database.
 *
 * NOTE: chap-secrets is fully rewritten, so accounts created outside the panel
 * (e.g. the initial user from hwdsl2/setup-ipsec-vpn) are dropped. Re-add them
 * as VPN users in the panel if they are still needed.
 */
class DeployConfigs extends Command
{
    protected $signature = 'vpnpanel:deploy-configs
                            {--chap-secrets : only regenerate /etc/ppp/chap-secrets}
                            {--nginx : only regenerate the nginx port-forwarding configs}
                            {--dry-run : print what would be written without touching disk or services}';

    protected $description = 'Regenerate chap-secrets and nginx configs from the database';

    public function handle(
        ChapSecretsDeployService $chapSecrets,
        NginxDeployService $nginx,
        \App\Services\ChapSecretsGeneratorService $chapGenerator,
        \App\Services\NginxConfigGeneratorService $nginxGenerator,
    ): int {
        $onlyChap = (bool) $this->option('chap-secrets');
        $onlyNginx = (bool) $this->option('nginx');
        $both = ! $onlyChap && ! $onlyNginx;

        if ($this->option('dry-run')) {
            if ($onlyChap || $both) {
                $this->line('--- would write: ' . config('vpnpanel.paths.chap_secrets') . ' ---');
                $this->line($chapGenerator->generate());
            }
            if ($onlyNginx || $both) {
                $this->line('--- would write: ' . config('vpnpanel.paths.nginx_stream_conf') . ' ---');
                $this->line($nginxGenerator->generateStreamConfig());
                $this->line('--- would write: ' . config('vpnpanel.paths.nginx_conf') . ' ---');
                $this->line($nginxGenerator->generateHttpConfig());
            }

            $this->info('Dry run: nothing written, no services restarted.');

            return self::SUCCESS;
        }

        $failures = 0;

        if ($onlyChap || $both) {
            try {
                $result = $chapSecrets->deploy();
                $this->info('chap-secrets deployed -> ' . $result['path']);
            } catch (\Throwable $e) {
                $failures++;
                $this->error('chap-secrets deploy failed: ' . $e->getMessage());
            }
        }

        if ($onlyNginx || $both) {
            try {
                $result = $nginx->deploy();
                $this->info('nginx stream -> ' . $result['stream_path']);
                $this->info('nginx http   -> ' . $result['http_path']);
            } catch (\Throwable $e) {
                $failures++;
                $this->error('nginx deploy failed: ' . $e->getMessage());
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
