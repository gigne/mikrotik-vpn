# VPN Panel — Central VPN Management Panel

Control panel terpusat untuk mengelola VPN L2TP/IPSec (berbasis
[hwdsl2/setup-ipsec-vpn](https://github.com/hwdsl2/setup-ipsec-vpn)) dan
cabang-cabang MikroTik melalui satu domain publik (`vpn.example.com`).

## Fitur

- **VPN User Management** — CRUD, enable/disable, reset password, tanggal kedaluwarsa, export CSV.
- **Manajemen MikroTik cabang** — provisioning otomatis (username, password, IP, port), monitoring online/offline, backup konfigurasi, generate script RouterOS + QR.
- **`/etc/ppp/chap-secrets` otomatis** — panel adalah sumber data utama; setiap perubahan menghasilkan ulang file, mem-backup versi lama, memvalidasi format, lalu me-restart xl2tpd.
- **IP Pool Management** — subnet `192.168.43.0/24`, alokasi otomatis/manual, cegah duplikasi & IP di luar subnet.
- **Nginx port forwarding** — Winbox (`:8291+`) via `/etc/nginx/stream.d/mikrotik.conf` (di-include dari blok `stream {}` top-level) dan WebFig (`:8080+`) via `/etc/nginx/conf.d/mikrotik.conf` (http), dengan `nginx -t` + reload otomatis.
- **Monitoring** — scheduler setiap menit (status VPN/IPsec/xl2tpd + ping semua MikroTik), backup harian (RouterOS, database, file terkelola) dengan retensi.
- **Notifikasi Telegram** — VPN server down, MikroTik offline, backup gagal, nginx reload gagal.
- **Keamanan** — role admin/operator, RBAC policy, 2FA Google Authenticator, audit log, enkripsi password MikroTik, rate limiting login, session timeout, CSRF.

## Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 12, PHP 8.3+ (kompatibel 8.5), MySQL/MariaDB, Queue + Scheduler |
| Frontend | Blade, TailwindCSS, Alpine.js, Chart.js |
| Server | Ubuntu 24.04, Nginx, xl2tpd, IPsec (hwdsl2), systemd |

## Instalasi Cepat (Ubuntu 24.04 pada server VPN)

```bash
sudo bash deploy/ubuntu-24.04-install.sh
```

Selesai. Lihat [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) untuk detail lengkap.

## Pengembangan Lokal (Windows/macOS/Linux — tanpa root)

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Mode default adalah **sandbox**: semua file yang "dikelola" ditulis ke
`storage/app/vpn-panel-sandbox/…` dan perintah sistem (systemctl/nginx) difake-kan,
sehingga panel aman dijalankan tanpa root. Untuk produksi set `VPNPANEL_DRIVER=local`.

Akun default (seeder):
- Admin: `admin@example.com` / `ChangeMe!`
- Operator: `operator@example.com` / `ChangeMe!`

## Alur Provisioning MikroTik

Saat admin menambah MikroTik, sistem secara transaksional:

1. Generate username VPN (`mt-<site>`)
2. Generate password VPN
3. Alokasi IP dari pool otomatis
4. Alokasi port Winbox publik
5. Alokasi port WebFig publik
6. Simpan ke database (MikroTik + VpnUser + NginxMapping)
7. Generate ulang `chap-secrets` (backup lama → validasi → replace → restart xl2tpd)
8. Generate ulang konfigurasi Nginx (backup → `nginx -t` → reload)
9. Audit log
10. Generate script RouterOS (copy / download .rsc / QR)

Jika satu langkah gagal, seluruh transaksi database di-rollback dan alokasi
IP/port dibatalkan.

## Struktur Proyek

```
app/
  Console/Commands/     # vpnpanel:monitor, vpnpanel:backup-*
  Contracts/            # FileStore, ProcessRunner, ProcessResult
  DTO/                  # VpnUserData, MikrotikData
  Http/
    Controllers/        # Web + Api controllers
    Middleware/         # TwoFactorChallenge, SessionTimeout
    Requests/           # Form requests + validasi
  Jobs/                 # DeployConfigsJob, MonitorMikrotikJob
  Models/               # VpnUser, Mikrotik, NginxMapping, RouterBackup,
                        # AuditLog, Notification, SystemSetting, User
  Policies/             # MikrotikPolicy, VpnUserPolicy (RBAC)
  Services/             # Service layer (lihat docs/DEPLOYMENT.md)
  Support/              # LocalFileStore, SandboxFileStore, LocalProcessRunner,
                        # SshProcessRunner, FakeProcessRunner
config/vpnpanel.php     # Seluruh konfigurasi panel
deploy/                 # Install script, nginx, systemd, supervisor
docs/                   # Dokumentasi
routes/web.php          # Web UI
routes/api.php          # REST API (Sanctum)
routes/console.php      # Scheduler
```

## Dokumentasi

- [docs/API.md](docs/API.md) — dokumentasi REST API
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — panduan deployment production
- [docs/BACKUP.md](docs/BACKUP.md) — panduan backup & restore
- [docs/MONITORING.md](docs/MONITORING.md) — panduan monitoring & notifikasi
- [docs/ERD.md](docs/ERD.md) — diagram relasi database

## Testing

```bash
php artisan test
```

Suite mencakup unit test (file store, 2FA, generator) dan feature test
(provisioning flow end-to-end dengan sandbox, validasi API, auth).