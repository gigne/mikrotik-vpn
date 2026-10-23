#!/usr/bin/env bash
# =========================================================================
# VPN Panel — Ubuntu 24.04 installation script
#
# Installs PHP 8.3, MariaDB, Nginx (+ stream module), the panel code, and
# configures it as the central control panel for your hwdsl2/setup-ipsec-vpn
# server. Set VPNPANEL_DRIVER=local so the panel writes directly to
# /etc/ppp/chap-secrets, /etc/nginx/conf.d/mikrotik.conf and systemd.
#
# Run as root:
#   sudo bash deploy/ubuntu-24.04-install.sh
# =========================================================================
set -euo pipefail

APP_DIR="/var/www/vpnpanel"
APP_USER="vpnpanel"
DOMAIN="${VPNPANEL_DOMAIN:-vpn.example.com}"
DB_PASS="$(openssl rand -base64 24 2>/dev/null || tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)"
PSK="${VPNPANEL_PSK:-$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32)}"

echo "==> Installing system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y \
    software-properties-common curl git unzip nginx nginx-extras \
    mariadb-server supervisor ca-certificates cron

echo "==> Installing PHP 8.3 (Ubuntu 24.04 default) + extensions"
apt-get install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath php8.3-gd \
    php8.3-sqlite3 php8.3-opcache

echo "==> Installing Composer"
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Creating application user"
id -u "$APP_USER" >/dev/null 2>&1 || useradd --system --home "$APP_DIR" --shell /bin/bash "$APP_USER"

echo "==> Cloning application"
if [ -d "$APP_DIR/.git" ]; then
    git -C "$APP_DIR" pull --ff-only
else
    git clone "${VPNPANEL_GIT_URL:-https://github.com/your-org/vpn-panel.git}" "$APP_DIR"
fi
chown -R "$APP_USER:$APP_USER" "$APP_DIR"

echo "==> Installing PHP dependencies"
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction -d "$APP_DIR"

echo "==> Configuring MariaDB"
mysql -e "CREATE DATABASE IF NOT EXISTS vpnpanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'vpnpanel'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON vpnpanel.* TO 'vpnpanel'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Writing environment file"
sudo -u "$APP_USER" bash -c "cat > '$APP_DIR/.env' <<EOF
APP_NAME='VPN Panel'
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://${DOMAIN}

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
VPNPANEL_CHAP_SECRETS=/etc/ppp/chap-secrets
VPNPANEL_NGINX_CONF=/etc/nginx/conf.d/mikrotik.conf
VPNPANEL_BACKUP_PATH=/var/backups/vpn-panel

# --- VPN pool ---
VPNPANEL_VPN_SUBNET=192.168.47.0/24
VPNPANEL_VPN_SERVER_IP=192.168.47.1
VPNPANEL_VPN_RANGE_START=192.168.47.2
VPNPANEL_VPN_RANGE_END=192.168.47.254
VPNPANEL_WINBOX_BASE_PORT=8291
VPNPANEL_WEBFIG_BASE_PORT=8080
VPNPANEL_PSK=${PSK}

# --- Notifications ---
VPNPANEL_TELEGRAM_ENABLED=false
VPNPANEL_TELEGRAM_BOT_TOKEN=
VPNPANEL_TELEGRAM_CHAT_ID=

# --- Default admin (database seeder) ---
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD='PleaseChangeMe!'
OPERATOR_EMAIL=operator@example.com
OPERATOR_PASSWORD='PleaseChangeMeToo!'
EOF"

echo "==> Generating application key + migrating + seeding"
sudo -u "$APP_USER" php "$APP_DIR/artisan" key:generate --force
sudo -u "$APP_USER" php "$APP_DIR/artisan" migrate --force
sudo -u "$APP_USER" php "$APP_DIR/artisan" db:seed --force

echo "==> Caching configuration"
sudo -u "$APP_USER" php "$APP_DIR/artisan" config:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" route:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" view:cache
sudo -u "$APP_USER" php "$APP_DIR/artisan" storage:link || true

echo "==> Installing laravel scheduler cron"
echo "* * * * * vpnpanel php $APP_DIR/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/vpnpanel
chmod 0644 /etc/cron.d/vpnpanel

echo "==> Empty chap-secrets bootstrap (panel owns this file from now on)"
BACKUP_DIR="/var/backups/vpn-panel"
mkdir -p "$BACKUP_DIR/chap-secrets" "$BACKUP_DIR/nginx" "$BACKUP_DIR/managed" "$BACKUP_DIR/database" "$BACKUP_DIR/mikrotik"
if [ -f /etc/ppp/chap-secrets ]; then
    cp -a /etc/ppp/chap-secrets "$BACKUP_DIR/chap-secrets/pre-panel-$(date +%Y%m%d%H%M%S)"
fi
touch /etc/ppp/chap-secrets
chown "$APP_USER:$APP_USER" /etc/ppp/chap-secrets

echo "==> Nginx: virtual host + upstream stream module"
cp "$APP_DIR/deploy/nginx-vpnpanel.conf" /etc/nginx/sites-available/vpnpanel
ln -sf /etc/nginx/sites-available/vpnpanel /etc/nginx/sites-enabled/vpnpanel
nginx -t && systemctl reload nginx

echo "==> Queue worker (systemd) + logs"
mkdir -p /var/log/vpnpanel
cp "$APP_DIR/deploy/vpnpanel-worker.service" /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now vpnpanel-worker

echo "==> Permissions: let panel restart xl2tpd/nginx via sudo"
cat > /etc/sudoers.d/vpnpanel <<EOF
vpnpanel ALL=(root) NOPASSWD: /usr/bin/systemctl restart xl2tpd, /usr/bin/systemctl reload nginx, /usr/bin/systemctl restart ipsec, /usr/bin/nginx -t
EOF
chmod 0440 /etc/sudoers.d/vpnpanel

echo ""
echo "==============================================================="
echo " Installation complete."
echo " Panel URL:        https://${DOMAIN}"
echo " Admin login:      admin@example.com / PleaseChangeMe!"
echo " IPsec PSK:        ${PSK}"
echo " DB password:      ${DB_PASS}"
echo ""
echo " IMPORTANT: change the admin password immediately, then set"
echo " VPNPANEL_PSK in .env to a strong shared secret."
echo " Managed files:"
echo "   /etc/ppp/chap-secrets            (panel-owned)"
echo "   /etc/nginx/conf.d/mikrotik.conf  (panel-owned)"
echo " Backups: /var/backups/vpn-panel"
echo "==============================================================="