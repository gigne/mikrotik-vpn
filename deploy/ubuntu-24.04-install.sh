#!/usr/bin/env bash
# =========================================================================
# VPN Panel --- Ubuntu 24.04 installation script
#
# Installs PHP 8.5 (or 8.3 fallback), Nginx (+ stream module), SQLite
# (no external database required), the panel code, and configures it as the
# central control panel for your hwdsl2/setup-ipsec-vpn server.
# Set VPNPANEL_DRIVER=local so the panel writes directly to
# /etc/ppp/chap-secrets, the nginx configs and systemd.
#
# IMPORTANT - install order:
#   1. hwdsl2/setup-ipsec-vpn (creates /etc/ipsec.d, /etc/ppp, /etc/xl2tpd)
#   2. this script (needs those paths, builds the panel's frontend assets)
#
# Run as root:
#   sudo bash deploy/ubuntu-24.04-install.sh
#
# Optional overrides:
#   VPNPANEL_DOMAIN=vpn.example.com   panel domain written to .env
#   VPNPANEL_SRC_DIR=/opt/vpnpanel-src
#   VPNPANEL_ALLOW_MISSING_IPSEC=1    install the panel without the VPN stack
# =========================================================================
set -euo pipefail

APP_DIR="/var/www/vpnpanel"
APP_USER="vpnpanel"
DOMAIN="${VPNPANEL_DOMAIN:-}"
SRC_DIR="${VPNPANEL_SRC_DIR:-/opt/vpnpanel-src}"
PSK="${VPNPANEL_PSK:-changeme-strong-psk}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-changeme-admin-password}"
OPERATOR_EMAIL="${OPERATOR_EMAIL:-operator@example.com}"
OPERATOR_PASSWORD="${OPERATOR_PASSWORD:-changeme-operator-password}"
ALLOW_MISSING_IPSEC="${VPNPANEL_ALLOW_MISSING_IPSEC:-0}"

# ---------------------------------------------------------------------------
# Preflight: the panel is a control panel *for* hwdsl2/setup-ipsec-vpn, so that
# stack has to be installed first. Without it /etc/ipsec.d, /etc/ppp and
# /etc/xl2tpd are missing and every VPN operation fails at runtime.
# ---------------------------------------------------------------------------
MISSING_IPSEC=""
[ -d /etc/ipsec.d ] || MISSING_IPSEC="${MISSING_IPSEC} /etc/ipsec.d"
[ -f /etc/ipsec.conf ] || MISSING_IPSEC="${MISSING_IPSEC} /etc/ipsec.conf"
[ -d /etc/xl2tpd ] || MISSING_IPSEC="${MISSING_IPSEC} /etc/xl2tpd"
if [ -n "$MISSING_IPSEC" ]; then
    echo "WARNING: hwdsl2/setup-ipsec-vpn does not appear to be installed."
    echo "         Missing:${MISSING_IPSEC}"
    echo "         Install the VPN stack FIRST, then re-run this script:"
    echo "           apt-get update && apt-get install -y wget"
    echo "           wget -qO /tmp/vpn-install.sh https://get.vpnsetup.net && bash /tmp/vpn-install.sh"
    if [ "$ALLOW_MISSING_IPSEC" != "1" ]; then
        echo "Aborting. Re-run with VPNPANEL_ALLOW_MISSING_IPSEC=1 to install the panel anyway."
        exit 1
    fi
    echo "         Continuing anyway because VPNPANEL_ALLOW_MISSING_IPSEC=1."
fi

echo "==> Installing system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y \
    software-properties-common curl git unzip nginx nginx-extras \
    php8.5-sqlite3 supervisor ca-certificates cron

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
    [ -f "$APP_DIR/database.sqlite" ] && cp -a "$APP_DIR/database.sqlite" "$STAGING/database.sqlite"
    [ -d "$APP_DIR/storage/logs" ] && cp -a "$APP_DIR/storage/logs" "$STAGING/logs"
    rm -rf "$APP_DIR"
    cp -a "$SRC_DIR"/. "$APP_DIR"
    [ -f "$STAGING/.env" ] && cp -a "$STAGING/.env" "$APP_DIR/.env"
    if [ -f "$STAGING/database.sqlite" ]; then
        cp -a "$STAGING/database.sqlite" "$APP_DIR/database.sqlite"
    fi
    if [ -d "$STAGING/logs" ]; then
        mkdir -p "$APP_DIR/storage/logs"
        cp -a "$STAGING/logs/." "$APP_DIR/storage/logs/"
    fi
    rm -rf "$STAGING"
else
    cp -a "$SRC_DIR"/. "$APP_DIR"
    mkdir -p "$APP_DIR/storage/logs"
    chown -R "$APP_USER:$APP_USER" "$APP_DIR/storage"
fi
chown -R "$APP_USER:$APP_USER" "$APP_DIR"

echo "==> Installing PHP dependencies"
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction -d "$APP_DIR"

echo "==> Building frontend assets (Vite)"
# public/build is gitignored, so it never arrives with the source checkout and
# the panel would answer HTTP 500 ("Vite manifest not found") without it.
if [ ! -f "$APP_DIR/public/build/manifest.json" ]; then
    # Vite 7 requires Node >= 20; Ubuntu 24.04 ships Node 18.
    if ! command -v node >/dev/null 2>&1 || [ "$(node -p 'process.versions.node.split(".")[0]')" -lt 20 ]; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
        apt-get install -y nodejs
    fi
    if [ -f "$SRC_DIR/package-lock.json" ]; then
        ( cd "$SRC_DIR" && npm ci --no-audit --no-fund )
    else
        ( cd "$SRC_DIR" && npm install --no-audit --no-fund )
    fi
    ( cd "$SRC_DIR" && npm run build )
    mkdir -p "$APP_DIR/public/build"
    cp -a "$SRC_DIR/public/build"/. "$APP_DIR/public/build/"
fi
if [ ! -f "$APP_DIR/public/build/manifest.json" ]; then
    echo "ERROR: $APP_DIR/public/build/manifest.json is missing." >&2
    echo "       The panel returns HTTP 500 without it. Build locally" >&2
    echo "       ('npm ci && npm run build') and copy public/build to ${APP_DIR}/public/," >&2
    echo "       or install Node >= 20 so this script can build it." >&2
    exit 1
fi
chown -R "$APP_USER:$APP_USER" "$APP_DIR/public/build"

echo "==> Ensuring SQLite database directory exists"
mkdir -p "$(dirname "$APP_DIR/database.sqlite")"
touch "$APP_DIR/database.sqlite"
chown -R "$APP_USER:$APP_USER" "$APP_DIR/database.sqlite" "$APP_DIR/storage"

echo "==> Writing environment file"
# Preserve APP_KEY on re-runs: it encrypts stored MikroTik VPN passwords, so
# rotating it would make existing records undecryptable. APP_URL is preserved
# too so a re-run does not reset a real domain back to the placeholder.
EXISTING_APP_KEY=""
EXISTING_APP_URL=""
if [ -f "$APP_DIR/.env" ]; then
    EXISTING_APP_KEY="$(grep -E '^APP_KEY=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
    EXISTING_APP_URL="$(grep -E '^APP_URL=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
fi

if [ -z "$DOMAIN" ]; then
    if [ -n "$EXISTING_APP_URL" ]; then
        DOMAIN="${EXISTING_APP_URL#*://}"
    else
        DOMAIN="vpn.example.com"
    fi
fi
DOMAIN="${DOMAIN%%/*}"

sudo -u "$APP_USER" bash -c "cat > '$APP_DIR/.env' <<EOF
APP_NAME='VPN Panel'
APP_ENV=production
APP_KEY=${EXISTING_APP_KEY}
APP_DEBUG=false
APP_URL=http://${DOMAIN}

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=${APP_DIR}/database.sqlite

CACHE_STORE=file
SESSION_DRIVER=file
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

# Auto-generate IKEv2 CA certificates so that IKEv2 user creation works immediately.
echo "==> Generating IKEv2 CA certificates (required for IKEv2 user creation)"
sudo -u "$APP_USER" php "$APP_DIR/artisan" ikev2:generate-ca --force 2>&1 && echo "    CA generated successfully." || echo "    WARNING: CA generation failed - will need manual run of 'php artisan ikev2:generate-ca'"

echo "==> Importing hwdsl2 VPN accounts & deploying chap-secrets from database"
# Pre-create backup dirs owned by $APP_USER BEFORE deploy-configs runs;
# otherwise the first file_put_contents() fails with mkdir(): Permission denied.
BACKUP_DIR="/var/backups/vpn-panel"
mkdir -p "$BACKUP_DIR"/{chap-secrets,nginx,managed,database,mikrotik}
chown -R "$APP_USER:$APP_USER" "$BACKUP_DIR"
chmod 0750 "$BACKUP_DIR"

# The panel owns chap-secrets; ensure it exists and is readable/writable by $APP_USER
# so that ChapSecretsDeployService can back up the old file and write a new one.
mkdir -p /etc/ppp
touch /etc/ppp/chap-secrets
chown "$APP_USER:$APP_USER" /etc/ppp/chap-secrets
chmod 0600 /etc/ppp/chap-secrets
# Parent dir /etc/ppp must be group-writable so atomic temp files can be created
chown root:"$APP_USER" /etc/ppp
chmod 0775 /etc/ppp

# Save pre-panel backup of chap-secrets if it already has real entries.
# We need the directory writable by $APP_USER before this copy can succeed.
if [ -f "$BACKUP_DIR/chap-secrets/"pre-panel-* ]; then
    echo "    Pre-panel backup already exists."
else
    if [ -s /etc/ppp/chap-secrets ] && grep -qP '^\h*"[^"]+"\h+' /etc/ppp/chap-secrets; then
        cp -a /etc/ppp/chap-secrets "$BACKUP_DIR/chap-secrets/pre-panel-$(date +%Y%m%d%H%M%S)"
    fi
fi

# Import existing hwdsl2 accounts (e.g. vpnadmin) from /etc/ppp/chap-secrets into the panel database.
# Skip if file is still empty — nothing to import yet.
if [ -s /etc/ppp/chap-secrets ] && grep -qP '^\h*"[^"]+"\h+' /etc/ppp/chap-secrets; then
    sudo -u "$APP_USER" php "$APP_DIR/artisan" vpnpanel:import-chap-secrets --silent || true
fi

# Set up sudoers FIRST so that the artisan deploy-configs command can use
# sudo systemctl restart xl2tpd/ipsec without password prompts.
echo "==> Permissions: atomic-write helper + sudoers"
chmod 0755 "$APP_DIR/deploy/vpnpanel-atomic-write"
chown "$APP_USER:$APP_USER" "$APP_DIR/deploy/vpnpanel-atomic-write"
cp "$APP_DIR/deploy/sudoers-vpnpanel" /etc/sudoers.d/vpnpanel
sed -i 's/\r$//' /etc/sudoers.d/vpnpanel
chmod 0440 /etc/sudoers.d/vpnpanel
visudo -cf /etc/sudoers.d/vpnpanel

# Pre-create nginx config directories with correct ownership BEFORE artisan migrate/seeds
# These must be writable by $APP_USER for deploy-configs to succeed.
mkdir -p /etc/nginx/conf.d /etc/nginx/stream.d
chown root:"$APP_USER" /etc/nginx /etc/nginx/conf.d /etc/nginx/stream.d
chmod 0775 /etc/nginx /etc/nginx/conf.d /etc/nginx/stream.d

# Rewrite /etc/ppp/chap-secrets + nginx configs from the panel's database.
# The LocalProcessRunner cannot use systemctl directly when running as $APP_USER,
# so we delegate to a root context for service management while keeping the file
# writes safe via our sudoers atomic-write helpers.
DEPLOY_EXIT=0
sudo -u "$APP_USER" php "$APP_DIR/artisan" vpnpanel:deploy-configs > >(grep -v '^$') 2>&1
DEPLOY_EXIT=${PIPESTATUS[0]}

if [ "$DEPLOY_EXIT" -ne 0 ]; then
    echo "    WARNING: deploy-configs returned exit $DEPLOY_EXIT (service restart may require manual steps)"
else
    echo "    Configs deployed successfully."
fi

# Directly manage services as root when deploy-configs could not (LocalProcessRunner
# lacks systemctl access under vpnpanel). This guarantees the configs take effect even
# if the artisan command skipped the restart step due to permission errors.
echo "    Ensuring services are running..."
# Restart xl2tpd if it's running (installed by hwdsl2, managed outside systemd)
if pgrep -x xl2tpd > /dev/null; then
    killall -HUP xl2tpd 2>/dev/null || supervisorctl restart xl2tpd 2>/dev/null || true
    echo "      xl2tpd signal sent"
fi
# Reload nginx config (files were written by deploy-configs or atomic-write)
/usr/sbin/nginx -t 2>&1 | tail -1
systemctl reload nginx 2>/dev/null || service nginx reload 2>/dev/null || { /usr/sbin/nginx -s reload 2>/dev/null; }
echo "      nginx reloaded"

echo "==> Caching configuration"
sudo -u "$APP_USER" php "$APP_DIR/artisan" config:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" route:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" view:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" storage:link || true

echo "==> Installing laravel scheduler cron"
echo "* * * * * vpnpanel /usr/local/bin/php $APP_DIR/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/vpnpanel
chmod 0644 /etc/cron.d/vpnpanel

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
ReadWritePaths=-/etc/ppp -/etc/nginx -/var/backups/vpn-panel -/var/log/vpnpanel -/etc/ipsec.d/certs
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
# /usr/lib/tmpfiles.d/php*.conf (which sets it root:root 0755) - override once.
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

echo ""
echo "==============================================================="
echo " Installation complete."
echo " Panel URL:        http://${DOMAIN}  (add HTTPS via certbot)"
echo " Admin login:      ${ADMIN_EMAIL} / ${ADMIN_PASSWORD}"
echo " Operator login:   ${OPERATOR_EMAIL} / ${OPERATOR_PASSWORD}"
echo " IPsec PSK:        ${PSK}"
echo ""
echo " IMPORTANT: change the admin password immediately."
echo " Managed files:"
echo "   /etc/ppp/chap-secrets            (panel-owned)"
echo "   /etc/nginx/conf.d/mikrotik.conf  (panel-owned, http)"
echo "   /etc/nginx/stream.d/mikrotik.conf (panel-owned, stream)"
echo " Backups: /var/backups/vpn-panel"
echo "==============================================================="
