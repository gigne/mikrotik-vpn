<?php

declare(strict_types=1);

namespace App\Http\Requests\VpnUser;

use Illuminate\Foundation\Http\FormRequest;

class StoreVpnUserRequest extends FormRequest
{
    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/', 'unique:vpn_users,username'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'assigned_ip' => ['nullable', 'ipv4'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expired_at' => ['nullable', 'date'],
            'is_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }
}
