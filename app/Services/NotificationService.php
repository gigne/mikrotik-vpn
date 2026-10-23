<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications via Telegram and database.
 */
class NotificationService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {
    }

    public function send(
        string $type,
        string $title,
        string $message,
        ?int $userId = null,
        bool $telegram = true
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
                'is_read' => false,
                'sent_at' => $telegram && $this->sendTelegram($title, $message) ? now() : null,
            ]);

        $this->auditLog->log('notification_sent', $notification, [
            'type' => $type,
            'title' => $title,
            'telegram' => $telegram,
        ]);

        return $notification;
    }

    public function sendToAll(
        string $type,
        string $title,
        string $message,
        bool $telegram = true
    ): array {
        $users = User::all();
        $notifications = [];

        foreach ($users as $user) {
            $notifications[] = $this->send($type, $title, $message, $user->id, $telegram);
        }

        return $notifications;
    }

    public function vpnServerDown(string $details): Notification
    {
        return $this->send(
            'vpn_server_down',
            'VPN Server Down',
            "VPN server is down or unreachable: {$details}",
            null,
            true
        );
    }

    public function mikrotikOffline(string $siteName, string $ip): Notification
    {
        return $this->send(
            'mikrotik_offline',
            'MikroTik Offline',
            "MikroTik {$siteName} ({$ip}) is offline",
            null,
            true
        );
    }

    public function vpnUserLogin(string $username, string $ip): Notification
    {
        return $this->send(
            'vpn_user_login',
            'VPN User Login',
            "User {$username} connected from {$ip}",
            null,
            false
        );
    }

    public function vpnUserLogout(string $username, string $ip): Notification
    {
        return $this->send(
            'vpn_user_logout',
            'VPN User Logout',
            "User {$username} disconnected from {$ip}",
            null,
            false
        );
    }

    public function backupFailed(string $type, string $details): Notification
    {
        return $this->send(
            'backup_failed',
            'Backup Failed',
            "Backup type {$type} failed: {$details}",
            null,
            true
        );
    }

    public function nginxReloadFailed(string $details): Notification
    {
        return $this->send(
            'nginx_reload_failed',
            'Nginx Reload Failed',
            "Failed to reload Nginx: {$details}",
            null,
            true
        );
    }

    private function sendTelegram(string $title, string $message): bool
    {
        if (! config('vpnpanel.telegram.enabled')) {
            return false;
        }

        $token = config('vpnpanel.telegram.bot_token');
        $chatId = config('vpnpanel.telegram.chat_id');

        if (empty($token) || empty($chatId)) {
            Log::warning('Telegram enabled but bot_token or chat_id is empty');
            return false;
        }

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => "*{$title}*\n\n{$message}",
                    'parse_mode' => 'Markdown',
                ]
            );

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Telegram send failed: ' . $e->getMessage());
            return false;
        }
    }
}
