<?php

declare(strict_types=1);

namespace App\DTO;

use Carbon\Carbon;

class Ikev2UserData
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly ?string $assignedIp = null,
        public readonly ?string $notes = null,
        public readonly ?Carbon $expiredAt = null,
        public readonly bool $isEnabled = true,
    ) {
    }
}
