<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Operating driver
    |--------------------------------------------------------------------------
    |
    | "local"   : operate directly on this machine's files and services
    |             (use on the Ubuntu VPN server).
    | "sandbox" : all file operations are re-rooted under
    |             storage/app/sandbox and service commands are faked.
    |             Safe for development, demos and feature tests.
    |
    */

    'driver' => env('VPNPANEL_DRIVER', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Public access
    |--------------------------------------------------------------------------
    */

    'domain' => env('VPNPANEL_DOMAIN', 'vpn.example.com'),

    /*
    |--------------------------------------------------------------------------
    | Files managed by the panel
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'chap_secrets' => env('VPNPANEL_CHAP_SECRETS', '/etc/ppp/chap-secrets'),
        'nginx_conf' => env('VPNPANEL_NGINX_CONF', '/etc/nginx/conf.d/mikrotik.conf'),
        'nginx_stream_conf' => env('VPNPANEL_NGINX_STREAM_CONF', '/etc/nginx/stream-proxy.conf'),
        'backups' => env('VPNPANEL_BACKUP_PATH', '/var/backups/vpn-panel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Process execution (sudo)
    |--------------------------------------------------------------------------
    |
    | When running as a non-root user on the VPN server (production), the
    | panel invokes systemctl / nginx through sudo using a narrow sudoers
    | rule (see deploy/ubuntu-24.04-install.sh). Disable only when the panel
    | process itself runs as root.
    |
    */

    'process_sudo' => (bool) env('VPNPANEL_PROCESS_SUDO', true),

    /*
    |--------------------------------------------------------------------------
    | Process runner (how the panel talks to the system)
    |--------------------------------------------------------------------------
    |
    | local : Symfony Process on the same machine.
    | ssh   : run commands over ssh (VPNPANEL_SSH_TARGET=user@host).
    | fake  : record commands, execute nothing (testing).
    |
    */

    'process_runner' => env('VPNPANEL_PROCESS_RUNNER', 'local'),

    'ssh' => [
        'target' => env('VPNPANEL_SSH_TARGET', 'root@vpn.example.com'),
        'key' => env('VPNPANEL_SSH_KEY', '/home/vpnpanel/.ssh/id_ed25519'),
        'timeout' => (int) env('VPNPANEL_SSH_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | System services
    |--------------------------------------------------------------------------
    */

    'services' => [
        'xl2tpd' => env('VPNPANEL_XL2TPD_SERVICE', 'xl2tpd'),
        'ipsec' => env('VPNPANEL_IPSEC_SERVICE', 'ipsec'),
        'nginx' => env('VPNPANEL_NGINX_SERVICE', 'nginx'),
        'strongswan' => env('VPNPANEL_STRONGSWAN_SERVICE', 'strongswan'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP pool defaults
    |--------------------------------------------------------------------------
    */

    'ip_pool' => [
        'subnet' => env('VPNPANEL_VPN_SUBNET', '192.168.47.0/24'),
        'server_ip' => env('VPNPANEL_VPN_SERVER_IP', '192.168.47.1'),
        'range_start' => env('VPNPANEL_VPN_RANGE_START', '192.168.47.2'),
        'range_end' => env('VPNPANEL_VPN_RANGE_END', '192.168.47.199'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public port defaults
    |--------------------------------------------------------------------------
    */

    'ports' => [
        'winbox_base' => (int) env('VPNPANEL_WINBOX_BASE_PORT', 8291),
        'winbox_max' => (int) env('VPNPANEL_WINBOX_MAX_PORT', 8999),
        'webfig_base' => (int) env('VPNPANEL_WEBFIG_BASE_PORT', 8080),
        'webfig_max' => (int) env('VPNPANEL_WEBFIG_MAX_PORT', 8999),
        'winbox_internal_default' => (int) env('VPNPANEL_WINBOX_INTERNAL_PORT', 8291),
        'webfig_internal_default' => (int) env('VPNPANEL_WEBFIG_INTERNAL_PORT', 80),
    ],

    /*
    |--------------------------------------------------------------------------
    | IPsec pre-shared key used in generated RouterOS scripts.
    |--------------------------------------------------------------------------
    */

    'psk' => env('VPNPANEL_PSK', 'ChangeMe-Replace-With-Strong-PSK'),

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    */

    'backups' => [
        'retention_days' => (int) env('VPNPANEL_BACKUP_RETENTION_DAYS', 30),
        'database' => [
            'mysqldump' => env('VPNPANEL_MYSQLDUMP_BIN', 'mysqldump'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'ping_enabled' => (bool) env('VPNPANEL_PING_ENABLED', true),
        'api_probe' => (bool) env('VPNPANEL_API_PROBE', false),
        'timeout_seconds' => (int) env('VPNPANEL_MONITOR_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram notifications
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'enabled' => (bool) env('VPNPANEL_TELEGRAM_ENABLED', false),
        'bot_token' => env('VPNPANEL_TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('VPNPANEL_TELEGRAM_CHAT_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        'session_timeout_minutes' => (int) env('VPNPANEL_SESSION_TIMEOUT', 60),
        'force_two_factor_for' => ['admin'],
    ],
];
