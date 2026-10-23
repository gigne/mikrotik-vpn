# Backup & Restore Guide

Lokasi backup: `/var/backups/vpn-panel` (ubah `VPNPANEL_BACKUP_PATH`).

```
/var/backups/vpn-panel/
├── chap-secrets/        # setiap deploy chap-secrets di-backup (timestamped)
├── nginx/               # setiap deploy nginx config di-backup
├── managed/<ts>/        # backup harian file terkelola (vpnpanel:backup-files)
├── database/            # dump harian vpnpanel-YYYY-mm-dd_HH-MM-SS.sql.gz
└── mikrotik/<site>/     # backup konfigurasi RouterOS per cabang
```

## Otomatis (scheduler)

| Kapan | Perintah | Isi |
|---|---|---|
| Setiap menit | `vpnpanel:monitor` | status VPN + ping MikroTik |
| 02:00 harian | `vpnpanel:backup-mikrotiks` | export konfigurasi tiap router |
| 03:00 harian | `vpnpanel:backup-database` | mysqldump + gzip |
| 03:30 harian | `vpnpanel:backup-files` | chap-secrets + nginx + retensi |

Retensi default 30 hari (`--retention-days=30`; opsi panel: 7/30/90 hari).

## Manual

```bash
php artisan vpnpanel:backup-database --retention-days=90
php artisan vpnpanel:backup-files
php artisan vpnpanel:backup-mikrotiks --site=Kantor
php artisan schedule:run
```

## Restore

### chap-secrets

```bash
cp /var/backups/vpn-panel/chap-secrets/chap-secrets.<TIMESTAMP> /etc/ppp/chap-secrets
systemctl restart xl2tpd
```

Atau biarkan panel menulis ulang dari database (database adalah sumber utama):

```bash
# buat satu user dummy atau jalankan perintah deploy (opsi masa depan):
php artisan vpnpanel:backup-files  # verifikasi file terkelola tersimpan
```

### Database

```bash
gunzip < /var/backups/vpn-panel/database/vpnpanel-<TS>.sql.gz | mysql -u vpnpanel -p vpnpanel
```

### Nginx

```bash
cp /var/backups/vpn-panel/managed/<TS>/http.mikrotik.conf /etc/nginx/conf.d/mikrotik.conf
cp /var/backups/vpn-panel/managed/<TS>/stream.mikrotik.conf /etc/nginx/stream.d/mikrotik.conf
nginx -t && systemctl reload nginx
```

## Notifikasi kegagalan

`backup_failed` terkirim ke Telegram (jika diaktifkan) + tercatat pada
`notifications` dan `audit_logs` di dalam panel.