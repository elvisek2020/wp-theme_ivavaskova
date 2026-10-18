#!/usr/bin/env bash
# Jednorázové spuštění lokálního webu ze zálohy (dev/wp = kopie webrootu, dev/db = dump).
set -euo pipefail
cd "$(dirname "$0")"

echo "▶ Startuju DB a WordPress…"
docker compose up -d

echo "▶ Čekám na import databáze…"
until docker compose exec -T db mariadb -uwp -pwp ivavaskova -e "SELECT 1 FROM wp_options LIMIT 1" >/dev/null 2>&1; do sleep 3; done

WP="docker compose run --rm cli wp"
echo "▶ Přepisuju URL na localhost…"
for old in https://www.ivavaskova.cz http://www.ivavaskova.cz https://ivavaskova.cz https://www.madeofstarsandlove.cz http://www.madeofstarsandlove.cz; do
  $WP search-replace "$old" 'http://localhost:8081' --all-tables --skip-columns=guid --report-changed-only
done

echo "▶ Vypínám ManageWP (lokálně nechceme)…"
$WP plugin deactivate worker 2>/dev/null || true
$WP cache flush || true

echo
echo "✅ Hotovo: http://localhost:8081  (admin: /wp-admin, stejné přihlášení jako na produkci)"
