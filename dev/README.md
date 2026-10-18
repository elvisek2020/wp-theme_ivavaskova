# Lokální vývoj

Požadavek: Docker (Docker Desktop / OrbStack). PHP 7.4 jako na produkci.

```bash
cd dev
./setup.sh              # první spuštění (import DB + přepis URL)
docker compose up -d    # další spuštění
docker compose down     # zastavení (data zůstanou)
docker compose down -v  # smazání DB → příští setup.sh naimportuje znovu
docker compose --profile cli run --rm cli wp <příkaz>   # WP-CLI
```

- Web: http://localhost:8081
- Šablona se vyvíjí v `../theme/ivavaskova`, plugin v `../plugins/ivavaskova-core` (připojeno do kontejneru, změny jsou vidět hned).
- mu-pluginy jen pro vývoj v `../mu-plugins` (neverzují se, nenasazovat).
- `wp/` je kopie webrootu ze zálohy 2026-10-17 bez ManageWP a s `.htaccess` jen s pravidly WordPressu, `db/` je dump databáze. Obojí se neverzuje.
- Debug log: `wp/wp-content/debug.log`.
