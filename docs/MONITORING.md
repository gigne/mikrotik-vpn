# Monitoring & Notification Guide

## Scheduler monitoring

`vpnpanel:monitor` berjalan setiap menit (routes/console.php):

1. Cek service system VPN server: `systemctl is-active xl2tpd ipsec`.
2. Ping setiap MikroTik (`ping -c 3 -W <timeout> <vpn_ip>`).
3. Update kolom `is_online` + `last_seen_at`.
4. Transisi online → offline memicu notifikasi Telegram `mikrotik_offline`.
5. Status VPN server yang tidak sehat memicu `vpn_server_down`.
6. Setiap pengecekan ditulis ke `audit_logs` (event `mikrotik_checked`).

Jalankan manual:

```bash
php artisan vpnpanel:monitor
php artisan vpnpanel:monitor --mikrotiks-only
```

## Dashboard

- Status VPN server (healthy/degraded), xl2tpd, IPsec.
- Total/online/offline MikroTik, total VPN users.
- Grafik online vs offline (Chart.js), IP pool usage, port usage.
- Tabel site: site name, VPN IP, status, last seen, URL Winbox & WebFig publik.
- Aktivitas terbaru dari audit log.

## Telegram

Aktifkan di `.env`:

```
VPNPANEL_TELEGRAM_ENABLED=true
VPNPANEL_TELEGRAM_BOT_TOKEN=123456:ABC-...
VPNPANEL_TELEGRAM_CHAT_ID=-100123456789
```

Acara yang dikirim:

| Tipe | Kapan |
|---|---|
| `vpn_server_down` | xl2tpd/ipsec tidak aktif |
| `mikrotik_offline` | ping gagal setelah sebelumnya online |
| `backup_failed` | backup MikroTik/database gagal |
| `nginx_reload_failed` | `nginx -t`/reload gagal saat deploy |
| `service_restart_failed` | restart xl2tpd gagal |

Notifikasi juga tersimpan di tabel `notifications` (untuk tampilan in-app).

## Timeout & kustomisasi

`.env`:

```
VPNPANEL_MONITOR_TIMEOUT=5   # detik per ping
VPNPANEL_PING_ENABLED=true   # matikan untuk mode API-probe saja
```

Jika cabang berada di link ber-latensi tinggi, naikkan timeout. Hindari
timeout terlalu kecil agar status tidak berfluktuasi (flapping).