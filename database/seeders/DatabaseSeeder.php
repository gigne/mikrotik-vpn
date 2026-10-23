<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Mikrotik;
use App\Models\NginxMapping;
use App\Models\User;
use App\Models\VpnUser;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default admin & operator accounts
        $admin = User::factory()->admin()->create([
            'name' => 'VPN Admin',
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe!')),
        ]);

        User::factory()->operator()->create([
            'name' => 'Operator',
            'email' => env('OPERATOR_EMAIL', 'operator@example.com'),
            'password' => Hash::make(env('OPERATOR_PASSWORD', 'ChangeMe!')),
        ]);

        // Seed reference branches from the spec
        $sites = [
            ['site_name' => 'Kantor', 'vpn_ip' => '192.168.47.2', 'vpn_username' => 'mt-kantor', 'winbox_public' => 8291, 'webfig_public' => 8080],
            ['site_name' => 'Gudang', 'vpn_ip' => '192.168.47.3', 'vpn_username' => 'mt-gudang', 'winbox_public' => 8292, 'webfig_public' => 8081],
            ['site_name' => 'Cabang A', 'vpn_ip' => '192.168.47.4', 'vpn_username' => 'mt-cabang-a', 'winbox_public' => 8293, 'webfig_public' => 8082],
            ['site_name' => 'Cabang B', 'vpn_ip' => '192.168.47.5', 'vpn_username' => 'mt-cabang-b', 'winbox_public' => 8294, 'webfig_public' => 8083],
        ];

        foreach ($sites as $site) {
            $mikrotik = Mikrotik::create([
                'site_name' => $site['site_name'],
                'description' => 'auto-seeded branch',
                'vpn_ip' => $site['vpn_ip'],
                'vpn_username' => $site['vpn_username'],
                'vpn_password_encrypted' => Mikrotik::encryptPassword(env('SEED_VPN_PASSWORD', 'password123')),
                'winbox_internal_port' => 8291,
                'webfig_internal_port' => 80,
                'winbox_public_port' => $site['winbox_public'],
                'webfig_public_port' => $site['webfig_public'],
                'routeros_version' => '7.14.3',
                'is_online' => false,
            ]);

            VpnUser::create([
                'username' => $site['vpn_username'],
                'password' => env('SEED_VPN_PASSWORD', 'password123'),
                'assigned_ip' => $site['vpn_ip'],
                'notes' => 'auto-seeded for ' . $site['site_name'],
                'is_enabled' => true,
            ]);

            NginxMapping::create([
                'mikrotik_id' => $mikrotik->id,
                'conn_type' => 'winbox',
                'public_port' => $site['winbox_public'],
                'is_enabled' => true,
            ]);
            NginxMapping::create([
                'mikrotik_id' => $mikrotik->id,
                'conn_type' => 'webfig',
                'public_port' => $site['webfig_public'],
                'is_enabled' => true,
            ]);
        }
    }
}