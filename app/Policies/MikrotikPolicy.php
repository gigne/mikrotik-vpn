<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mikrotik;
use App\Models\User;

class MikrotikPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Mikrotik $mikrotik): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Mikrotik $mikrotik): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Mikrotik $mikrotik): bool
    {
        return $user->isAdmin();
    }

    public function provision(User $user): bool
    {
        return $user->isAdmin();
    }

    public function backup(User $user, Mikrotik $mikrotik): bool
    {
        return $user->isAdmin();
    }
}
