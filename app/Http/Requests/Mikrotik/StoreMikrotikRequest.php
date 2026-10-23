<?php

declare(strict_types=1);

namespace App\Http\Requests\Mikrotik;

use Illuminate\Foundation\Http\FormRequest;

class StoreMikrotikRequest extends FormRequest
{
    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:100', 'unique:mikrotiks,site_name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'vpn_ip' => ['nullable', 'ipv4'],
            'vpn_password' => ['nullable', 'string', 'min:8', 'max:64'],
            'winbox_internal_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'webfig_internal_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'routeros_version' => ['nullable', 'string', 'max:40'],
            'ssh_public_key' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }
}
