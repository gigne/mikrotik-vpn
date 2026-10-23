#!/usr/bin/env bash
# =========================================================================
# VPN Panel — one-shot server bootstrap (Ubuntu 24.04)
#
# Sets up EVERYTHING on a fresh Ubuntu 24.04 VPS:
#   1. Swap (for low-memory droplets)
#   2. System packages (nginx-extras, MariaDB, supervisor, PHP 8.5)
#   3. hwdsl2/setup-ipsec-vpn (L2TP/IPsec server)
#   4. VPN subnet rewrite (default 192.168.47.0/24)
#   5. The panel itself (deploy/ubuntu-24.04-install.sh)
#
# Idempotent: safe to re-run; completed steps are skipped.
#
# Usage:
#   # from your workstation (requires ssh access as root):
#   ./bootstrap.sh --host <YOUR_SERVER_IP> [--ssh-key ~/.ssh/id_ed25519]
#
#   # or directly on the server:
#   ./bootstrap.sh --local
#
# Configuration via environment variables (all optional):
#   VPN_DOMAIN=vpn.example.com          public domain of the VPN server
#   VPN_IPSEC_PSK=...                 IPsec pre-shared key
#   VPN_USER=vpnadmin                 built-in L2TP/IPsec account
#   VPN_PASSWORD=...
#   VPN_SUBNET=192.168.47             /24 subnet used by the panel + xl2tpd
#   SWAP_SIZE=2G                      swap file size
#   PANEL_SRC=/opt/vpnpanel-src       where the panel source was uploaded
# =========================================================================
set -euo pipefail

# ---- defaults ------------------------------------------------------------
VPN_DOMAIN="${VPN_DOMAIN:-vpn.example.com}"
VPN_IPSEC_PSK="${VPN_IPSEC_PSK:-changeme-strong-psk}"
VPN_USER="${VPN_USER:-vpnadmin}"
VPN_PASSWORD="${VPN_PASSWORD:-changeme-secure-password}"
VPN_SUBNET="${VPN_SUBNET:-192.168.47}"
SWAP_SIZE="${SWAP_SIZE:-2G}"
PANEL_SRC="${PANEL_SRC:-/opt/vpnpanel-src}"
INSTALL_SCRIPT="${PANEL_SRC}/deploy/ubuntu-24.04-install.sh"

# ---- argument parsing ----------------------------------------------------
MODE="local"
SSH_HOST=""
SSH_KEY=""

while [ $# -gt 0 ]; do
    case "$1" in
        --local)   MODE="local"; shift ;;
        --host)    MODE="ssh"; SSH_HOST="$2"; shift 2 ;;
        --ssh-key) SSH_KEY="$2"; shift 2 ;;
        -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 2 ;;
    esac
done

if [ "$MODE" = "ssh" ]; then
    # Re-invoke this script on the remote host
    [ -n "$SSH_HOST" ] || { echo "--host requires an IP/hostname" >&2; exit 2; }
    SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=15)
    [ -n "$SSH_KEY" ] && SSH_OPTS+=(-i "$SSH_KEY")
    echo "==> Uploading bootstrap to ${SSH_HOST} and running it there"
    scp "${SSH_OPTS[@]}" "$0" "root@${SSH_HOST}:/root/bootstrap.sh"
    ssh "${SSH_OPTS[@]}" "root@${SSH_HOST}" \
        "VPN_DOMAIN='${VPN_DOMAIN}' VPN_IPSEC_PSK='${VPN_IPSEC_PSK}' VPN_USER='${VPN_USER}' VPN_PASSWORD='${VPN_PASSWORD}' VPN_SUBNET='${VPN_SUBNET}' SWAP_SIZE='${SWAP_SIZE}' PANEL_SRC='${PANEL_SRC}' bash /root/bootstrap.sh --local"
    exit $?
fi

# ---- must run as root locally --------------------------------------------
[ "$(id -u)" = "0" ] || { echo "Run as root (or use --host)" >&2; exit 1; }

log()  { echo -e "\n==> $*"; }
skip() { echo "    (skipped: $*)"; }

export DEBIAN_FRONTEND=noninteractive

log "Step 0/6: sanity checks"
. /etc/os-release
[ "${VERSION_ID:-}" = "24.04" ] || echo "WARNING: expected Ubuntu 24.04, found ${PRETTY_NAME}"
free -m | awk '/Mem:/ {print "    RAM: " $2 " MB"}'

# ---------------------------------------------------------------------------
log "Step 1/6: swap (${SWAP_SIZE})"
if swapon --show | grep -q .; then
    skip "swap already configured"
elif [ -f /swapfile ]; then
    skip "/swapfile exists but not active — activating"
    swapon /swapfile || true
else
    fallocate -l "$SWAP_SIZE" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo "    swap created"
fi

# ---------------------------------------------------------------------------
log "Step 2/6: system packages + PHP 8.5"
apt-get update -qq
apt-get install -y -qq software-properties-common curl ca-certificates python3 >/dev/null

# PHP 8.5 from ondrej PPA (Ubuntu 24.04 ships 8.3 which cannot run this lockfile)
if ! command -v php8.5 >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
    apt-get update -qq
    apt-get install -y -qq \
        php8.5-cli php8.5-fpm php8.5-mysql php8.5-mbstring php8.5-xml \
        php8.5-curl php8.5-zip php8.5-intl php8.5-bcmath php8.5-gd \
        php8.5-sqlite3 php8.5-common >/dev/null
fi
ln -sf "$(command -v php8.5)" /usr/local/bin/php
php --version | head -1

# ---------------------------------------------------------------------------
log "Step 3/6: hwdsl2/setup-ipsec-vpn (L2TP/IPsec server)"
if systemctl is-active --quiet xl2tpd && systemctl is-active --quiet ipsec; then
    skip "ipsec + xl2tpd already active"
else
    curl -fsSL https://raw.githubusercontent.com/hwdsl2/setup-ipsec-vpn/master/vpnsetup.sh \
        -o /root/vpnsetup.sh
    chmod +x /root/vpnsetup.sh
    # Patch the three credential assignments in-place (keep upstream logic intact)
    python3 - "$VPN_IPSEC_PSK" "$VPN_USER" "$VPN_PASSWORD" <<'PY'
import re, sys
psk, user, pw = sys.argv[1], sys.argv[2], sys.argv[3]
p = '/root/vpnsetup.sh'
s = open(p).read()
s = re.sub(r"YOUR_IPSEC_PSK='[^']*'", "YOUR_IPSEC_PSK='%s'" % psk, s, count=1)
s = re.sub(r"YOUR_USERNAME='[^']*'",   "YOUR_USERNAME='%s'"   % user, s, count=1)
s = re.sub(r"YOUR_PASSWORD='[^']*'",   "YOUR_PASSWORD='%s'"   % pw,   s, count=1)
open(p, 'w').write(s)
PY
    bash /root/vpnsetup.sh
fi

# ---------------------------------------------------------------------------
log "Step 4/6: rewrite VPN subnet to ${VPN_SUBNET}.0/24"
XL2TPD_CONF=/etc/xl2tpd/xl2tpd.conf
if grep -q "ip range = ${VPN_SUBNET}\." "$XL2TPD_CONF"; then
    skip "xl2tpd already on ${VPN_SUBNET}.0/24"
else
    cp -a "$XL2TPD_CONF" "${XL2TPD_CONF}.bak-$(date +%Y%m%d%H%M%S)"
    # Static IPs managed by the panel occupy ${VPN_SUBNET}.2-.199;
    # the dynamic pool is confined to .200-.250 to avoid collisions.
    sed -i \
        -e "s|^ip range = .*|ip range = ${VPN_SUBNET}.200-${VPN_SUBNET}.250|" \
        -e "s|^local ip = .*|local ip = ${VPN_SUBNET}.1|" \
        "$XL2TPD_CONF"
    systemctl restart xl2tpd
    echo "    xl2tpd subnet updated"
fi
grep -E '^(ip range|local ip)' "$XL2TPD_CONF" | sed 's/^/    /'

# ---------------------------------------------------------------------------
log "Step 5/6: panel installation"
if [ ! -f "$INSTALL_SCRIPT" ]; then
    echo "ERROR: ${INSTALL_SCRIPT} not found." >&2
    echo "Upload the panel source to ${PANEL_SRC} first, e.g.:" >&2
    echo "  tar -czf vpnpanel.tar.gz --exclude=node_modules --exclude=vendor . && \\" >&2
    echo "  scp vpnpanel.tar.gz root@<host>:/root/ && \\" >&2
    echo "  ssh root@<host> 'mkdir -p ${PANEL_SRC} && tar -xzf /root/vpnpanel.tar.gz -C ${PANEL_SRC}'" >&2
    exit 1
fi
sed -i 's/\r$//' "${PANEL_SRC}"/deploy/*.sh "${PANEL_SRC}"/deploy/*.service "${PANEL_SRC}"/deploy/*.conf 2>/dev/null || true
VPNPANEL_DOMAIN="$VPN_DOMAIN" VPNPANEL_PSK="$VPN_IPSEC_PSK" bash "$INSTALL_SCRIPT"

# ---------------------------------------------------------------------------
log "Step 6/6: firewall"
if command -v ufw >/dev/null 2>&1; then
    ufw allow 22/tcp   >/dev/null
    ufw allow 80/tcp   >/dev/null
    ufw allow 443/tcp  >/dev/null
    ufw allow 500/udp  >/dev/null   # IPsec IKE
    ufw allow 4500/udp >/dev/null   # IPsec NAT-T
    ufw allow 1701/udp >/dev/null   # L2TP
    ufw allow 8080:8999/tcp >/dev/null  # WebFig + Winbox public ports
    ufw --force enable >/dev/null
    echo "    ufw rules applied"
fi

log "Bootstrap complete"
cat <<SUMMARY
===============================================================
 VPN server:   ${VPN_DOMAIN}
 L2TP/IPsec:   PSK=${VPN_IPSEC_PSK}  user=${VPN_USER}  pass=${VPN_PASSWORD}
 VPN subnet:   ${VPN_SUBNET}.0/24 (static .2-.199, dynamic .200-.250)
 Panel:        http://${VPN_DOMAIN}  (admin/operator created by seeder)
 Logs:         /root/install.log, /root/bootstrap-*.log, journalctl -u vpnpanel-worker
===============================================================
SUMMARY
