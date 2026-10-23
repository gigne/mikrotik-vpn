# Entity Relationship Diagram

```mermaid
erDiagram
    USERS ||--o{ AUDIT_LOGS : "dibuat oleh"
    USERS ||--o{ NOTIFICATIONS : "dituju"
    VPN_USERS ||--o{ MIKROTIKS : "login VPN 1:1"
    MIKROTIKS ||--o{ NGINX_MAPPINGS : "port forwarding"
    MIKROTIKS ||--o{ ROUTER_BACKUPS : "backup config"

    USERS {
        bigint id PK
        string name
        string email UK
        bool is_admin
        string phone
        text two_factor_secret
        text two_factor_recovery_codes
        datetime two_factor_confirmed_at
    }

    VPN_USERS {
        bigint id PK
        string username UK
        string password
        ip assigned_ip UK nullable
        text notes
        datetime expired_at nullable
        bool is_enabled
        datetime last_used_at
        datetime deleted_at
    }

    MIKROTIKS {
        bigint id PK
        string site_name UK
        text description
        ip vpn_ip UK
        string vpn_username UK
        string vpn_password_encrypted
        int winbox_internal_port
        int webfig_internal_port
        int winbox_public_port
        int webfig_public_port
        string routeros_version
        bool is_online
        datetime last_seen_at
        string ssh_public_key
        datetime deleted_at
    }

    NGINX_MAPPINGS {
        bigint id PK
        bigint mikrotik_id FK
        enum conn_type "winbox|webfig"
        int public_port UK(conn_type,port)
        bool is_enabled
    }

    ROUTER_BACKUPS {
        bigint id PK
        bigint mikrotik_id FK
        string backup_path
        char checksum(32)
        int file_size
    }

    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK nullable
        string event
        string subject_type
        bigint subject_id
        json meta
        ip ip_address
        string user_agent
        datetime created_at
    }

    NOTIFICATIONS {
        bigint id PK
        bigint user_id FK nullable
        string type
        string title
        text message
        bool is_read
        datetime sent_at
    }

    SYSTEM_SETTINGS {
        bigint id PK
        string key UK
        text value
        string cast
    }
```

## Relasi penting

- Satu `Mikrotik` selalu punya `VpnUser` dengan `username = vpn_username`
  (dibuat otomatis saat provisioning).
- `NginxMapping` satu per jenis (`winbox`, `webfig`) per MikroTik; constraint
  unik `(conn_type, public_port)` mencegah konflik port.
- `assigned_ip` (VPN user) dan `vpn_ip` (MikroTik) bersama-sama membentuk pool
  `192.168.43.0/24`; keduanya unik sehingga alokasi ganda dicegah di level DB
  maupun aplikasi (`IpAllocatorService`).
- `AuditLog` mengikuti pola morph (subject_type + subject_id) untuk merekam
  aksi terhadap model apa pun.
- Tabel `jobs`, `failed_jobs`, `cache`, `sessions`, `personal_access_tokens`
  disediakan oleh framework (queue, cache, session, Sanctum).