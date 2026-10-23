<?php

namespace App\Providers;

use App\Contracts\FileStore;
use App\Contracts\ProcessRunner;
use App\Support\LocalProcessRunner;
use App\Support\SandboxFileStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FileStore::class, function ($app) {
            $driver = config('vpnpanel.driver', 'sandbox');
            if ($driver === 'local') {
                // The installer makes /etc/ppp, /etc/nginx/conf.d, and
                // /etc/nginx group-writable by vpnpanel, so the app writes
                // managed files directly — no sudo helper needed.
                return new \App\Support\LocalFileStore(basePath: base_path());
            }
            return new SandboxFileStore(basePath: storage_path('app'));
        });

        $this->app->singleton(ProcessRunner::class, function ($app) {
            $runner = config('vpnpanel.process_runner', 'local');
            if ($runner === 'fake') {
                return new \App\Support\FakeProcessRunner();
            }
            if ($runner === 'ssh') {
                return new \App\Support\SshProcessRunner(
                    target: config('vpnpanel.ssh.target'),
                    keyPath: config('vpnpanel.ssh.key'),
                    timeout: config('vpnpanel.ssh.timeout'),
                );
            }
            return new LocalProcessRunner(timeout: 60);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
