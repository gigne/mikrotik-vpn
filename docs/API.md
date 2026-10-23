# REST API Documentation

Base URL: `https://vpn.example.com/api`
Auth: Bearer token (Laravel Sanctum). Issued to panel users after login.

Buat token:

```
POST /api/... (menggunakan session) atau via tinker:
$user->createToken('panel')->plainTextToken
```

Semua endpoint dibawah memerlukan header:

```
Authorization: Bearer <token>
Accept: application/json
```

## MikroTik

### GET /api/mikrotik

Query params: `search` (teks), `online` (boolean), `per_page` (default 25).

```json
{
  "data": [
    {
      "id": 1,
      "site_name": "Kantor",
      "vpn_ip": "192.168.42.2",
      "vpn_username": "mt-kantor",
      "winbox_internal_port": 8291,
      "webfig_internal_port": 80,
      "winbox_public_port": 8291,
      "webfig_public_port": 8080,
      "routeros_version": "7.14.3",
      "is_online": true,
      "last_seen_at": "2026-10-22T12:00:00Z"
    }
  ],
  "links": {}, "meta": {}
}
```

### POST /api/mikrotik

Provision MikroTik baru (transaksional: alokasi IP + port, chap-secrets,
nginx, script RouterOS).

```json
{
  "site_name": "Cabang A",
  "description": "Opsional",
  "vpn_ip": "192.168.42.4",          // opsional → auto-alokasi
  "vpn_password": "optional-pass",   // opsional → auto-generate
  "winbox_internal_port": 8291,
  "webfig_internal_port": 80,
  "routeros_version": "7.14.3",
  "ssh_public_key": "ssh-ed25519 ..."
}
```

Response: `201` + resource MikroTik.

### GET /api/mikrotik/{mikrotik}

Lihat detail. `GET /api/mikrotik/{mikrotik}/script` mengembalikan:

```json
{
  "data": {
    "script": "/interface l2tp-client add ...",
    "qr_data": "{\"server\":\"vpn.example.com\",...}",
    "summary": {
      "vpn_server": "vpn.example.com",
      "username": "mt-cabang-a",
      "password": "...",
      "vpn_ip": "192.168.42.4",
      "psk": "...",
      "winbox_url": "vpn.example.com:8293",
      "webfig_url": "vpn.example.com:8082"
    }
  }
}
```

### PUT /api/mikrotik/{mikrotik}

Fields sama dengan POST (parsial diperbolehkan; `vpn_password` kosong = dipertahankan).

### DELETE /api/mikrotik/{mikrotik}

Decommission: hapus mappings, VPN user, release IP/port, regenerate file.

---

## VPN Users

### GET /api/vpn-users

Query params: `search`, `status` (`all|active|expired|disabled`), `per_page`.

### POST /api/vpn-users

```json
{
  "username": "maria",
  "password": "secret123",
  "assigned_ip": "192.168.42.50",   // opsional
  "notes": "Karyawan cabang",
  "expired_at": "2027-01-01 00:00:00",
  "is_enabled": true
}
```

Response: `201`. Validasi: username unik `[a-zA-Z0-9_-]`, password 8-64.

### GET /api/vpn-users/{vpnUser}

### PUT /api/vpn-users/{vpnUser}

Sama dengan POST (password boleh kosong untuk dipertahankan).

### DELETE /api/vpn-users/{vpnUser}

Soft delete + regenerate chap-secrets.

### POST /api/vpn-users/{vpnUser}/toggle

Flip `is_enabled`.

---

## IP Pool

### GET /api/ip-pools

```json
{
  "data": {
    "stats": { "total": 253, "used": 5, "free": 248, "usage_percent": 1.98 },
    "entries": [
      { "ip": "192.168.42.2", "status": "used" },
      { "ip": "192.168.42.6", "status": "free" }
    ]
  }
}
```

---

## System

| Method | Route | Deskripsi |
|---|---|---|
| POST | `/api/reload-nginx` | Regenerate + deploy + `nginx -t` + reload |
| POST | `/api/restart-xl2tpd` | Restart service xl2tpd |
| GET | `/api/status` | Status VPN server (xl2tpd, ipsec) |
| GET | `/api/dashboard` | Statistik dashboard |

## Errors

| Status | Makna |
|---|---|
| 401 | Belum/tidak terautentikasi |
| 403 | Tidak punya izin (non-admin) |
| 404 | Resource tidak ditemukan |
| 422 | Validasi gagal / provisioning error (duplikat IP/port, dsb) |
| 500 | Kesalahan sistem (deploy nginx/chap-secrets gagal) |

Error body:

```json
{ "message": "IP 192.168.42.4 is already in use", "errors": { "field": ["..."] } }
```