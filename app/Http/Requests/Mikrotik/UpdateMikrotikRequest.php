<?php

declare(strict_types=1);

namespace App\Http\Requests\Mikrotik;

use App\Models\Mikrotik;
use App\Models\NginxMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMikrotikRequest extends FormRequest
{
    /** @return array<string, array<int, string>|string> */
    public function rules(): array
    {
        $mikrotik = $this->route('mikrotik');

        return [
            'site_name' => ['required', 'string', 'max:100',
                Rule::unique('mikrotiks', 'site_name')->ignore($mikrotik?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'vpn_ip' => function ($attribute, $value, $fail) use ($mikrotik) {
                if ($value === '' || $value === null) {
                    return; // Skip validation if empty
                }
                if (!filter_var($value, FILTER_VALIDATE_IP)) {
                    return $fail("{$attribute} must be a valid IPv4 address.");
                }
                // Check if IP is the reserved gateway/server IP
                $serverIp = config('vpnpanel.ip_pool.server_ip', '192.168.43.1');
                if ($value === $serverIp) {
                    return $fail("{$attribute} is reserved for the VPN gateway/server.");
                }
                // Check if IP is in allowed range
                $rangeStart = config('vpnpanel.ip_pool.range_start', '192.168.43.2');
                $rangeEnd = config('vpnpanel.ip_pool.range_end', '192.168.43.199');
                $ipLong = ip2long($value);
                $startLong = ip2long($rangeStart);
                $endLong = ip2long($rangeEnd);
                if ($ipLong < $startLong || $ipLong > $endLong) {
                    return $fail("{$attribute} must be between {$rangeStart} and {$rangeEnd}.");
                }
                // Check uniqueness
                $existing = Mikrotik::where('vpn_ip', $value)
                    ->where('id', '!=', $mikrotik->id)
                    ->exists();
                if ($existing) {
                    return $fail("{$attribute} is already in use by another MikroTik.");
                }
            },
            'vpn_password' => ['nullable', 'string', 'min:8', 'max:64'],
            'winbox_internal_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'webfig_internal_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'winbox_public_port' => function ($attribute, $value, $fail) use ($mikrotik) {
                if ($value === '' || $value === null) {
                    return; // Skip validation if empty
                }
                if (!is_numeric($value)) {
                    return $fail("{$attribute} must be a number.");
                }
                $port = (int)$value;
                if ($port < 1 || $port > 65535) {
                    return $fail("{$attribute} must be between 1 and 65535.");
                }
                $existing = NginxMapping::where('conn_type', 'winbox')
                    ->where('public_port', $port)
                    ->where('mikrotik_id', '!=', $mikrotik->id)
                    ->exists();
                if ($existing) {
                    return $fail("Winbox public port {$port} is already in use by another MikroTik.");
                }
            },
            'webfig_public_port' => function ($attribute, $value, $fail) use ($mikrotik) {
                if ($value === '' || $value === null) {
                    return; // Skip validation if empty
                }
                if (!is_numeric($value)) {
                    return $fail("{$attribute} must be a number.");
                }
                $port = (int)$value;
                if ($port < 1 || $port > 65535) {
                    return $fail("{$attribute} must be between 1 and 65535.");
                }
                $existing = NginxMapping::where('conn_type', 'webfig')
                    ->where('public_port', $port)
                    ->where('mikrotik_id', '!=', $mikrotik->id)
                    ->exists();
                if ($existing) {
                    return $fail("WebFig public port {$port} is already in use by another MikroTik.");
                }
            },
            'routeros_version' => ['nullable', 'string', 'max:40'],
            'ssh_public_key' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }
}
