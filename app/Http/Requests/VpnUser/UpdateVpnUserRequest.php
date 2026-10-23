<?php

declare(strict_types=1);

namespace App\Http\Requests\VpnUser;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVpnUserRequest extends FormRequest
{
    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
            $vpnUser = $this->route('vpnUser') ?? $this->route('vpn_user');

        return [
            'username' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('vpn_users', 'username')->ignore($vpnUser?->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:64'],
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
