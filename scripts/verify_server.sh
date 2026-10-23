#!/usr/bin/env bash
# Smoke-test the deployed panel: REST API, managed files and listening ports.
#
# Usage (on the VPN server):
#   bash scripts/verify_server.sh <sanctum-token>
#   VPNPANEL_TOKEN=... bash scripts/verify_server.sh
#
# Optional: BASE=http://vpn.example.com
set -uo pipefail

BASE="${BASE:-http://vpn.example.com}"
TOKEN="${1:-${VPNPANEL_TOKEN:-}}"

if [ -z "$TOKEN" ]; then
    echo "usage: $0 <sanctum-token>   (or export VPNPANEL_TOKEN=...)" >&2
    exit 2
fi

HDR=(-H "Authorization: Bearer ${TOKEN}" -H 'Accept: application/json')
PASS=0
FAIL=0

code_of() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

check() { # check <label> <expected> <url>
    local label="$1" want="$2" url="$3" got
    got=$(code_of "${HDR[@]}" "$url")
    if [ "$got" = "$want" ]; then
        printf '  OK   %-30s %s\n' "$label" "$got"
        PASS=$((PASS + 1))
    else
        printf '  FAIL %-30s got %s, want %s\n' "$label" "$got" "$want"
        FAIL=$((FAIL + 1))
    fi
}

echo "=== authentication ==="
GUEST=$(code_of -H 'Accept: application/json' "$BASE/api/mikrotik")
if [ "$GUEST" = "401" ]; then
    printf '  OK   %-30s %s\n' "guest rejected" "$GUEST"; PASS=$((PASS + 1))
else
    printf '  FAIL %-30s got %s, want 401\n' "guest rejected" "$GUEST"; FAIL=$((FAIL + 1))
fi

echo "=== REST API ==="
check "GET /api/user"        200 "$BASE/api/user"
check "GET /api/dashboard"   200 "$BASE/api/dashboard"
check "GET /api/status"      200 "$BASE/api/status"
check "GET /api/mikrotik"    200 "$BASE/api/mikrotik"
check "GET /api/vpn-users"   200 "$BASE/api/vpn-users"
check "GET /api/ip-pools"    200 "$BASE/api/ip-pools"

echo "=== web UI ==="
for path in / /login /dashboard /mikrotiks /vpn-users; do
    got=$(curl -s -o /dev/null -w '%{http_code}' -L --max-redirs 0 "$BASE$path")
    case "$got" in
        200|302) printf '  OK   %-30s %s\n' "GET $path" "$got"; PASS=$((PASS + 1)) ;;
        *)       printf '  FAIL %-30s %s\n' "GET $path" "$got"; FAIL=$((FAIL + 1)) ;;
    esac
done

echo
echo "=== payload samples ==="
echo "-- /api/dashboard --"
curl -s "${HDR[@]}" "$BASE/api/dashboard"
echo
echo "-- /api/mikrotik?per_page=1 --"
curl -s "${HDR[@]}" "$BASE/api/mikrotik?per_page=1" | head -c 600
echo

echo
echo "=== managed files ==="
echo "-- /etc/ppp/chap-secrets (passwords redacted) --"
sed -E 's/("[^"]*") (l2tpd) "[^"]*"/\1 \2 <redacted>/' /etc/ppp/chap-secrets 2>/dev/null | head -20

echo "-- /etc/nginx/stream-proxy.conf --"
head -20 /etc/nginx/stream-proxy.conf 2>/dev/null

echo "-- /etc/nginx/conf.d/mikrotik.conf --"
head -12 /etc/nginx/conf.d/mikrotik.conf 2>/dev/null

echo "-- nginx -t --"
nginx -t 2>&1 | tail -2

echo
echo "=== listening public ports (8080-8999) ==="
ss -ltn 2>/dev/null | awk 'NR==1 || $4 ~ /:(80[89][0-9]|8[1-9][0-9][0-9])$/' | head -14

echo
echo "=== services ==="
for s in nginx mariadb php8.5-fpm xl2tpd ipsec vpnpanel-worker; do
    printf '  %-18s %s\n' "$s" "$(systemctl is-active "$s" 2>/dev/null || echo unknown)"
done

echo
echo "RESULT: ${PASS} passed, ${FAIL} failed"
[ "$FAIL" -eq 0 ]
