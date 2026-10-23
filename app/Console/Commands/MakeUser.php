<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Creates (or updates) a panel user and optionally prints a Sanctum API token.
 *
 * Useful for first-time login on a fresh install and for scripting access:
 *   php artisan vpnpanel:make-user admin@example.com --name "VPN Admin" --admin --token
 */
class MakeUser extends Command
{
    protected $signature = 'vpnpanel:make-user
                            {email : Login email address}
                            {--name= : Display name (defaults to the email local-part)}
                            {--password= : Plaintext password (prompted when omitted)}
                            {--admin : Grant administrator role}
                            {--operator : Explicitly grant the operator (non-admin) role}
                            {--token : Also issue and print a Sanctum API token}
                            {--token-name=panel-cli : Name for the issued API token}';

    protected $description = 'Create or update a panel user (admin/operator) and optionally issue an API token';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $isAdmin = (bool) $this->option('admin');

        if ($this->option('operator') && $isAdmin) {
            $this->error('Choose either --admin or --operator, not both.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?? '');
        if ($password === '') {
            $password = (string) $this->secret('Password (input hidden)');
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'min:8']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? explode('@', $email)[0]);

        $user = User::firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        $user->name = $name;
        $user->password = Hash::make($password);
        $user->email_verified_at = $user->email_verified_at ?? now();

        // Only change the role when explicitly requested, so re-running with
        // --password alone cannot silently demote an existing admin.
        if ($isAdmin) {
            $user->is_admin = true;
        } elseif ($this->option('operator')) {
            $user->is_admin = false;
        } elseif ($isNew) {
            $user->is_admin = false;
        }

        $user->save();

        $this->info(sprintf(
            '%s %s (role: %s)',
            $isNew ? 'Created' : 'Updated',
            $user->email,
            $user->is_admin ? 'admin' : 'operator',
        ));

        if ($this->option('token')) {
            $tokenName = (string) $this->option('token-name');
            $user->tokens()->where('name', $tokenName)->delete();

            $this->line('');
            $this->line('API token (' . $tokenName . '):');
            $this->info($user->createToken($tokenName)->plainTextToken);
            $this->warn('Store it now — it cannot be retrieved again.');
        }

        return self::SUCCESS;
    }
}
