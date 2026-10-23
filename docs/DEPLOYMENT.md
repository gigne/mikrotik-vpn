# Deployment Guide — Ubuntu 24.04 (server VPN)

Panel dirancang untuk berjalan **di server yang sama** dengan
[hwdsl2/setup-ipsec-vpn](https://github.com/hwdsl2/setup-ipsec-vpn) sehingga
bisa menulis `/etc/ppp/chap-secrets`, me-reload nginx, dan me-restart xl2tpd
secara langsung.

## 1. Prasyarat

- Ubuntu 24.04 dengan setup-ipsec-vpn telah terpasang (xl2tpd + IPsec aktif).
- IP publik server. Domain bersifat opsional: vhost panel adalah *catch-all*,
  jadi panel bisa diakses langsung lewat `http://<IP>` maupun lewat domain apa
  pun yang mengarah ke IP tersebut.
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
| `VPNPANEL_DRIVER` | `local` | Tulis file nyata (`/etc/ppp/chap-secrets`, `/etc/nginx/conf.d/mikrotik.conf`, `/etc/nginx/stream.d/mikrotik.conf`) |
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
cat /etc/nginx/stream.d/mikrotik.conf            # berisi listen 8291
cat /etc/nginx/conf.d/mikrotik.conf              # berisi listen 8080
sudo nginx -t                                    # OK
ipsec status                                     # tetap normal
```

## 7. IKEv2 (klien mobile) — auth berbasis sertifikat

Panel menandatangani sertifikat klien dengan **CA server yang sudah ada**
(layout hwdsl2), sehingga klien langsung diterima oleh `conn ikev2-cp`
(`rightca=%same`). Tidak ada konfigurasi ipsec per-user yang perlu ditulis.

Sekali per server:

```bash
# Ekspor CA + private key dari NSS DB milik strongswan/Libreswan
sudo -i
cd /tmp
pk12util -o ca.p12 -n 'IKEv2 VPN CA' -d sql:/etc/ipsec.d -W ''
openssl pkcs12 -in ca.p12 -passin pass: -nodes -nokeys  -out ca-cert.pem
openssl pkcs12 -in ca.p12 -passin pass: -nodes -nocerts -out ca-key.pem

install -o root -g root     -m 0644 ca-cert.pem /etc/ipsec.d/certs/ca-cert.pem
install -o root -g vpnpanel -m 0640 ca-key.pem  /etc/ipsec.d/private/ca-key.pem
chown root:vpnpanel /etc/ipsec.d/certs/ca-serial
chmod 0664 /etc/ipsec.d/certs/ca-serial
rm -f ca.p12 ca-cert.pem ca-key.pem
```

Set identitas publik yang harus diverifikasi klien (samakan dengan SAN
sertifikat server dan `leftid` di `/etc/ipsec.d/ikev2.conf`):

```
VPNPANEL_IKEV2_SERVER_ID=203.0.113.10
```

Lalu `php artisan config:cache`. Jalankan `php artisan ikev2:generate-ca`
hanya bila server belum punya CA sendiri (akan membuat CA baru).

Catatan: `VPNPANEL_IKEV2_AUTH_MODE=certificate` (default) tidak menyentuh
`/etc/ipsec.d/*.conf`. Set ke `eap` hanya untuk instalasi strongSwan yang
memang memakai EAP-MSCHAPv2.

## 8. HTTPS opsional

Gunakan certbot untuk sertifikat panel:

```bash
sudo apt-get install certbot python3-certbot-nginx
sudo certbot --nginx -d vpn.example.com
```