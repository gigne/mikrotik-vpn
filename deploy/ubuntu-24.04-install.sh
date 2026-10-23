#!/usr/bin/env bash
# =========================================================================
# VPN Panel — Ubuntu 24.04 installation script
#
# Installs PHP 8.5 (or 8.3 fallback), MariaDB, Nginx (+ stream module), the
# panel code, and configures it as the central control panel for your
# hwdsl2/setup-ipsec-vpn server. Set VPNPANEL_DRIVER=local so the panel
# writes directly to /etc/ppp/chap-secrets, the nginx configs and systemd.
#
# Run as root:
#   sudo bash deploy/ubuntu-24.04-install.sh
# =========================================================================
set -euo pipefail

APP_DIR="/var/www/vpnpanel"
APP_USER="vpnpanel"
DOMAIN="${VPNPANEL_DOMAIN:-vpn.example.com}"
SRC_DIR="${VPNPANEL_SRC_DIR:-/opt/vpnpanel-src}"
DB_PASS="${VPNPANEL_DB_PASS:-changeme-db-password}"
PSK="${VPNPANEL_PSK:-changeme-strong-psk}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-changeme-admin-password}"
OPERATOR_EMAIL="${OPERATOR_EMAIL:-operator@example.com}"
OPERATOR_PASSWORD="${OPERATOR_PASSWORD:-changeme-operator-password}"

echo "==> Installing system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y \
    software-properties-common curl git unzip nginx nginx-extras \
    mariadb-server supervisor ca-certificates cron

echo "==> Installing PHP (prefers 8.5 via ppa:ondrej/php, falls back to 8.3)"
if ! command -v php8.5 >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
    apt-get update -y
    apt-get install -y php8.5-cli php8.5-fpm php8.5-mysql php8.5-mbstring \
        php8.5-xml php8.5-curl php8.5-zip php8.5-intl php8.5-bcmath php8.5-gd \
        php8.5-sqlite3 php8.5-common >/dev/null 2>&1 || true
fi
if ! command -v php8.5 >/dev/null 2>&1; then
    apt-get install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
        php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath php8.3-gd \
        php8.3-sqlite3 php8.3-opcache
fi
PHP_BIN="$(command -v php8.5 || command -v php8.3)"
PHP_FPM_VERSION="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
echo "    Using PHP ${PHP_FPM_VERSION} (${PHP_BIN})"
# Make this PHP the default for all downstream calls (artisan, composer, cron)
ln -sf "$PHP_BIN" /usr/local/bin/php
ln -sf "$PHP_BIN" /usr/local/bin/vpnpanel-php

echo "==> Installing Composer"
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Creating application user"
id -u "$APP_USER" >/dev/null 2>&1 || useradd --system --home "$APP_DIR" --shell /bin/bash "$APP_USER"

echo "==> Installing application code from ${SRC_DIR}"
if [ ! -d "$SRC_DIR" ]; then
    echo "ERROR: source dir $SRC_DIR not found. Upload the panel code first." >&2
    exit 1
fi
mkdir -p "$(dirname "$APP_DIR")"
if [ -d "$APP_DIR" ]; then
    # Preserve runtime state across re-runs: .env (holds APP_KEY, which encrypts
    # stored VPN passwords), storage/logs and the local sqlite db if any.
    STAGING="$(mktemp -d)"
    [ -f "$APP_DIR/.env" ] && cp -a "$APP_DIR/.env" "$STAGING/.env"
    [ -d "$APP_DIR/storage/logs" ] && cp -a "$APP_DIR/storage/logs" "$STAGING/logs"
    rm -rf "$APP_DIR"
    cp -a "$SRC_DIR" "$APP_DIR"
    [ -f "$STAGING/.env" ] && cp -a "$STAGING/.env" "$APP_DIR/.env"
    [ -d "$STAGING/logs" ] && cp -a "$STAGING/logs/." "$APP_DIR/storage/logs/"
    rm -rf "$STAGING"
else
    cp -a "$SRC_DIR" "$APP_DIR"
fi
chown -R "$APP_USER:$APP_USER" "$APP_DIR"

echo "==> Installing PHP dependencies"
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction -d "$APP_DIR"

echo "==> Configuring MariaDB"
mysql -e "CREATE DATABASE IF NOT EXISTS vpnpanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'vpnpanel'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON vpnpanel.* TO 'vpnpanel'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Writing environment file"
# Preserve APP_KEY on re-runs: it encrypts stored MikroTik VPN passwords, so
# rotating it would make existing records undecryptable.
EXISTING_APP_KEY=""
if [ -f "$APP_DIR/.env" ]; then
    EXISTING_APP_KEY="$(grep -E '^APP_KEY=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
fi

sudo -u "$APP_USER" bash -c "cat > '$APP_DIR/.env' <<EOF
APP_NAME='VPN Panel'
APP_ENV=production
APP_KEY=${EXISTING_APP_KEY}
APP_DEBUG=false
APP_URL=http://${DOMAIN}

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vpnpanel
DB_USERNAME=vpnpanel
DB_PASSWORD=${DB_PASS}

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# --- Auto-generated VPN files ---
VPNPANEL_DRIVER=local
VPNPANEL_DOMAIN=${DOMAIN}
VPNPANEL_PROCESS_RUNNER=local
VPNPANEL_PROCESS_SUDO=true
VPNPANEL_CHAP_SECRETS=/etc/ppp/chap-secrets
VPNPANEL_NGINX_CONF=/etc/nginx/conf.d/mikrotik.conf
VPNPANEL_NGINX_STREAM_CONF=/etc/nginx/stream.d/mikrotik.conf
VPNPANEL_BACKUP_PATH=/var/backups/vpn-panel

# --- VPN pool ---
VPNPANEL_VPN_SUBNET=192.168.42.0/24
VPNPANEL_VPN_SERVER_IP=192.168.42.1
VPNPANEL_VPN_RANGE_START=192.168.42.2
VPNPANEL_VPN_RANGE_END=192.168.42.199
VPNPANEL_WINBOX_BASE_PORT=8291
VPNPANEL_WEBFIG_BASE_PORT=8080
VPNPANEL_PSK=${PSK}

# --- Notifications ---
VPNPANEL_TELEGRAM_ENABLED=false
VPNPANEL_TELEGRAM_BOT_TOKEN=
VPNPANEL_TELEGRAM_CHAT_ID=

# --- Default admin (database seeder) ---
ADMIN_EMAIL=${ADMIN_EMAIL}
ADMIN_PASSWORD='${ADMIN_PASSWORD}'
OPERATOR_EMAIL=${OPERATOR_EMAIL}
OPERATOR_PASSWORD='${OPERATOR_PASSWORD}'
EOF"

echo "==> Generating application key (only if absent) + migrating + seeding"
if ! grep -qE '^APP_KEY=base64:' "$APP_DIR/.env"; then
    sudo -u "$APP_USER" php "$APP_DIR/artisan" key:generate --force
else
    echo "    APP_KEY already set - preserving it"
fi
sudo -u "$APP_USER" php "$APP_DIR/artisan" migrate --force
sudo -u "$APP_USER" php "$APP_DIR/artisan" db:seed --force

echo "==> Caching configuration"
sudo -u "$APP_USER" php "$APP_DIR/artisan" config:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" route:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" view:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" storage:link || true

echo "==> Installing laravel scheduler cron"
echo "* * * * * vpnpanel /usr/local/bin/php $APP_DIR/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/vpnpanel
chmod 0644 /etc/cron.d/vpnpanel

echo "==> Empty chap-secrets bootstrap (panel owns this file from now on)"
BACKUP_DIR="/var/backups/vpn-panel"
mkdir -p "$BACKUP_DIR/chap-secrets" "$BACKUP_DIR/nginx" "$BACKUP_DIR/managed" "$BACKUP_DIR/database" "$BACKUP_DIR/mikrotik"
# The panel writes backups as $APP_USER; root-owned dirs would make every
# chap-secrets/nginx deploy fail with "Permission denied".
chown -R "$APP_USER:$APP_USER" "$BACKUP_DIR"
chmod 0750 "$BACKUP_DIR"
if [ -f /etc/ppp/chap-secrets ]; then
    cp -a /etc/ppp/chap-secrets "$BACKUP_DIR/chap-secrets/pre-panel-$(date +%Y%m%d%H%M%S)"
    chown "$APP_USER:$APP_USER" "$BACKUP_DIR/chap-secrets/pre-panel-"* 2>/dev/null || true
fi
touch /etc/ppp/chap-secrets
chown "$APP_USER:$APP_USER" /etc/ppp/chap-secrets
chmod 0600 /etc/ppp/chap-secrets
# Parent dir /etc/ppp must be group-writable so atomic temp files can be created
chown root:"$APP_USER" /etc/ppp
chmod 0775 /etc/ppp

echo "==> Nginx: virtual host + panel-owned config files"
cp "$APP_DIR/deploy/nginx-vpnpanel.conf" /etc/nginx/sites-available/vpnpanel
# Point the vhost at the installed PHP-FPM socket (8.5 or 8.3)
sed -i "s|php8\.5-fpm\.sock|php${PHP_FPM_VERSION}-fpm.sock|" /etc/nginx/sites-available/vpnpanel
ln -sf /etc/nginx/sites-available/vpnpanel /etc/nginx/sites-enabled/vpnpanel

# http-context directives (limit_req_zone cannot live inside server{})
cp "$APP_DIR/deploy/nginx-vpnpanel-http.conf" /etc/nginx/conf.d/vpnpanel-http.conf

# The panel manages two files:
#   /etc/nginx/conf.d/mikrotik.conf    WebFig http servers   (http {} context)
#   /etc/nginx/stream.d/mikrotik.conf  Winbox stream proxies (stream {} context)
# stream.d is included from the top level of nginx.conf because `stream {}` is
# invalid inside the http {} context that owns conf.d.
mkdir -p /etc/nginx/conf.d /etc/nginx/stream.d
touch /etc/nginx/conf.d/mikrotik.conf /etc/nginx/stream.d/mikrotik.conf
chown "$APP_USER:$APP_USER" /etc/nginx/conf.d/mikrotik.conf /etc/nginx/stream.d/mikrotik.conf
# Parent dirs must be group-writable so atomic temp files can be created
chown root:"$APP_USER" /etc/nginx /etc/nginx/conf.d /etc/nginx/stream.d
chmod 0775 /etc/nginx /etc/nginx/conf.d /etc/nginx/stream.d

# Log dir must exist before ReadWritePaths is applied: a missing path makes
# php-fpm fail with status=226/NAMESPACE.
mkdir -p /var/log/vpnpanel
chown -R "$APP_USER:$APP_USER" /var/log/vpnpanel

# Override PHP-FPM systemd sandbox (ProtectSystem=full blocks writes to /etc)
FPM_OVERRIDE_DIR="/etc/systemd/system/php${PHP_FPM_VERSION}-fpm.service.d"
mkdir -p "$FPM_OVERRIDE_DIR"
cat > "$FPM_OVERRIDE_DIR/override.conf" <<EOF
[Service]
ReadWritePaths=/etc/ppp /etc/nginx /var/backups/vpn-panel /var/log/vpnpanel /etc/ipsec.d/certs
EOF
systemctl daemon-reload

# PHP-FPM pool must run as $APP_USER so it can directly write the panel-owned
# files (/etc/ppp/chap-secrets, /etc/nginx/conf.d/mikrotik.conf, etc.).
# Default www.conf runs as www-data; patch user/group/listen owner in-place
# (idempotent: sed only matches www-data so re-runs are safe).
FPM_POOL="/etc/php/${PHP_FPM_VERSION}/fpm/pool.d/www.conf"
sed -i "s/^user = www-data/user = ${APP_USER}/" "$FPM_POOL"
sed -i "s/^group = www-data/group = ${APP_USER}/" "$FPM_POOL"
sed -i "s/^listen\.owner = www-data/listen.owner = ${APP_USER}/" "$FPM_POOL"
sed -i "s/^listen\.group = www-data/listen.group = ${APP_USER}/" "$FPM_POOL"
echo "    PHP-FPM pool: user/group = ${APP_USER}"

# Nginx worker (www-data) needs read access to the FPM socket (mode 0660).
# Adding www-data to the vpnpanel group is enough; nginx -s reload picks it up
# immediately because the master process respawns workers.
usermod -aG "$APP_USER" www-data || true

# /run/php must be traversable by vpnpanel group so FPM can create the socket
# and nginx can connect. Runtime tmpfs is re-created on boot by
# /usr/lib/tmpfiles.d/php*.conf (which sets it root:root 0755) — override once.
# On next boot php-fpm start will recreate the socket inside the dir.
chown root:"$APP_USER" /run/php 2>/dev/null || true
chmod 0775 /run/php 2>/dev/null || true

# Make /run/php group ownership survive reboots: tmpfiles.d recreates /run/php
# on boot (root:root 0755); override with a higher-priority snippet.
cat > /etc/tmpfiles.d/php-vpnpanel.conf <<EOF
# /run/php must be group-vpnpanel so FPM socket (0660 vpnpanel:vpnpanel) is
# accessible by nginx worker (www-data, member of vpnpanel group).
d /run/php 0775 root vpnpanel - -
EOF

systemctl restart "php${PHP_FPM_VERSION}-fpm"

# Idempotently add the top-level stream include for the panel's Winbox proxies.
# `stream {}` is invalid inside the http {} context that owns conf.d, so
# /etc/nginx/stream.d/ gets its own top-level block. The marker comment makes
# this re-runnable without leaving duplicate stream {} blocks behind.
# NOTE: ngx_stream_module is already loaded by nginx-extras (50-mod-stream.conf);
# loading it again makes `nginx -t` fail with "module is already loaded".
rm -f /etc/nginx/modules-enabled/zz-stream-vpnpanel.conf
sed -i '/# vpnpanel-stream-include/,+3d' /etc/nginx/nginx.conf
sed -i '\|include /etc/nginx/stream-proxy\.conf;|d' /etc/nginx/nginx.conf
sed -i '/^http {/i # vpnpanel-stream-include\nstream {\n    include /etc/nginx/stream.d/*.conf;\n}\n' /etc/nginx/nginx.conf
# Drop the pre-stream.d layout from older installs.
rm -f /etc/nginx/stream-proxy.conf

# Remove the stock default site so the panel vhost answers on this host.
rm -f /etc/nginx/sites-enabled/default

nginx -t && systemctl reload nginx

echo "==> Queue worker (systemd) + logs"
mkdir -p /var/log/vpnpanel
chown -R "$APP_USER:$APP_USER" /var/log/vpnpanel
cp "$APP_DIR/deploy/vpnpanel-worker.service" /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now vpnpanel-worker

echo "==> Permissions: atomic-write helper + sudoers"
# The panel owns the target *files* but their parent dirs (/etc/ppp, /etc/nginx)
# stay root-owned. A dedicated sudo helper writes via stdin so secrets never
# appear in argv or /proc/<pid>/cmdline.
chmod 0755 /var/www/vpnpanel/deploy/vpnpanel-atomic-write
chown "$APP_USER:$APP_USER" /var/www/vpnpanel/deploy/vpnpanel-atomic-write

cp "$APP_DIR/deploy/sudoers-vpnpanel" /etc/sudoers.d/vpnpanel
sed -i 's/\r$//' /etc/sudoers.d/vpnpanel
chmod 0440 /etc/sudoers.d/vpnpanel
visudo -cf /etc/sudoers.d/vpnpanel

echo ""
echo "==============================================================="
echo " Installation complete."
echo " Panel URL:        http://${DOMAIN}  (add HTTPS via certbot)"
echo " Admin login:      ${ADMIN_EMAIL} / ${ADMIN_PASSWORD}"
echo " Operator login:   ${OPERATOR_EMAIL} / ${OPERATOR_PASSWORD}"
echo " IPsec PSK:        ${PSK}"
echo " DB password:      ${DB_PASS}"
echo ""
echo " IMPORTANT: change the admin password immediately."
echo " Managed files:"
echo "   /etc/ppp/chap-secrets            (panel-owned)"
echo "   /etc/nginx/conf.d/mikrotik.conf  (panel-owned, http)"
echo "   /etc/nginx/stream.d/mikrotik.conf (panel-owned, stream)"
echo " Backups: /var/backups/vpn-panel"
echo "==============================================================="