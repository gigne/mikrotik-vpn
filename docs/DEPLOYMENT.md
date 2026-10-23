# Deployment Guide — Ubuntu 24.04 (server VPN)

Panel dirancang untuk berjalan **di server yang sama** dengan
[hwdsl2/setup-ipsec-vpn](https://github.com/hwdsl2/setup-ipsec-vpn) sehingga
bisa menulis `/etc/ppp/chap-secrets`, me-reload nginx, dan me-restart xl2tpd
secara langsung.

## 1. Prasyarat

- Ubuntu 24.04 dengan setup-ipsec-vpn telah terpasang (xl2tpd + IPsec aktif).
- Domain `vpn.example.com` mengarah ke IP publik server.
- Port terbuka: 80/443 (panel), 500/4500/1701 UDP (IPsec/L2TP dari MikroTik),
  serta rentang port publik Winbox (8291+) dan WebFig (8080+).
- User root / sudo.

## 2. Instalasi

```bash
cd /opt
git clone https://github.com/your-org/vpn-panel.git vpnpanel-src
cd vpnpanel-src
sudo bash deploy/ubuntu-24.04-install.sh
```

Script melakukan: instal PHP 8.3 + ekstensi, MariaDB, Composer, nginx (extras
untuk stream module), membuat user sistem `vpnpanel`, menulis `.env`
(`VPNPANEL_DRIVER=local`), migrate+seed, cron scheduler, systemd worker,
backup dirs, dan sudoers agar panel bisa `systemctl restart xl2tpd`,
`systemctl reload nginx`, `nginx -t`.

## 3. Konfigurasi Opsional SSH runner

Jika panel berada di mesin berbeda dari server VPN, set `.env`:

```
VPNPANEL_PROCESS_RUNNER=ssh
VPNPANEL_SSH_TARGET=root@vpn.example.com
VPNPANEL_SSH_KEY=/home/vpnpanel/.ssh/id_ed25519
```

dan pastikan akses SSH tanpa password (authorized_keys). Dengan mode ini
`VPNPANEL_DRIVER` tetap `local` dan file dikelola via `scp`-style copy yang
dipetakan oleh `SshFileStore` (konfigurasi lanjutan).

## 4. Driver & keamanan file terkelola

| Setting | Nilai | Efek |
|---|---|---|
| `VPNPANEL_DRIVER` | `local` | Tulis file nyata (`/etc/ppp/chap-secrets`, `/etc/nginx/conf.d/mikrotik.conf`) |
| | `sandbox` | Semua file di-root ulang ke `storage/app/vpn-panel-sandbox` (dev/demo) |
| `VPNPANEL_PROCESS_RUNNER` | `local` | Jalankan systemctl/nginx langsung |
| | `ssh` | Jalankan via SSH ke host VPN |
| | `fake` | Rekam saja (testing) |

Setelah mengubah `.env`, jalankan:

```bash
php artisan config:cache
```

## 5. Laravel worker & scheduler

Scheduler dijalankan via cron (dibuat script install):

```
* * * * * vpnpanel php /var/www/vpnpanel/artisan schedule:run
```

Queue worker via systemd: `systemctl status vpnpanel-worker`.

## 6. Verifikasi

```bash
sudo -u vpnpanel php /var/www/vpnpanel/artisan vpnpanel:monitor
sudo -u vpnpanel php /var/www/vpnpanel/artisan about
# dari panel: tambah MikroTik "Kantor" → cek:
cat /etc/ppp/chap-secrets                        # berisi mt-kantor
cat /etc/nginx/conf.d/mikrotik.conf              # berisi listen 8291 + 8080
sudo nginx -t                                    # OK
ipsec status                                     # tetap normal
```

## 7. HTTPS opsional

Gunakan certbot untuk sertifikat panel:

```bash
sudo apt-get install certbot python3-certbot-nginx
sudo certbot --nginx -d vpn.example.com
```