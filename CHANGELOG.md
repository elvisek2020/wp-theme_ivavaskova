# Changelog

## v0.1.2 — 2026-10-20

### Šablona
- **Přepínač vzhledu v patičce** místo ikony v menu: **Systém / Světlý / Tmavý**. „Systém“ se řídí nastavením zařízení, ruční volba se pamatuje.

### Iva Vašková Core
- Beze změny, jen nové číslo verze (plugin a šablona se vydávají společně).

## v0.1.1 — 2026-10-18

### Šablona
- **Podpis v patičce** jako na původním webu, na jednom řádku: „Copyright © 2018–2026 **Ivuš** · Design & code by ElvisEK“ (odkaz na elvisek.cz) · Nastavení cookies · Administrace. Jméno, autora i odkaz jde změnit v Přizpůsobit → Iva Vašková.

### Iva Vašková Core
- Beze změny, jen nové číslo verze (plugin a šablona se vydávají společně).

### Vydání
- Lokální vývoj v Dockeru přepnutý na PHP 8.5 (jako produkce).

## v0.1.0 — 2026-10-18

### Šablona
- **Nový vzhled „Hvězdná noc“** místo šablony Dollah: barvy z ilustrace v logu (tmavě modrá a zlaté hvězdičky), zaoblené karty článků ve třech sloupcích, vzdušnější typografie. Písma Comfortaa a Open Sans se načítají z webu, ne z Google.
- **Světlý a tmavý režim**: řídí se nastavením zařízení, přepíná se ikonou měsíce/slunce v menu a volba se pamatuje.
- **Mobil**: menu se schová pod tlačítko Menu, podmenu Kategorie a Tvořím se rozbalují klepnutím; nic nepřetéká do strany.
- **Hledání** ikonou lupy v menu.
- **Lightbox** na fotky v galeriích a článcích: šipky, klávesnice i přejetí prstem (nahrazuje wp-jquery-lightbox).
- **Externí odkazy** v článcích se otevírají v novém okně (nahrazuje Open External Links).
- **Sociální sítě** jako ikony v patičce; adresy v Přizpůsobit → Iva Vašková (nahrazuje Sticky Side Buttons).
- **Google Analytics 4 s cookie lištou**: měří se až po souhlasu návštěvníka, odkaz „Nastavení cookies“ v patičce; ID měření v Přizpůsobit (nahrazuje MonsterInsights a CookieYes).
- **SEO**: popis stránky, náhled při sdílení na sociálních sítích (Open Graph) a strukturovaná data článků.
- **Antispam komentářů** bez reCAPTCHA: skryté pole a kontrola času vyplnění.
- **YouTube playlisty** vložené odkazem se přehrají, i když první video playlistu zmizí.
- **Stránka 404** s hledáním a nejnovějšími články, **tisk** článků bez menu a patičky.
- Při přepnutí ze šablony Dollah se převezme menu a logo.
- **Aktualizace šablony z GitHubu** přes běžnou obrazovku aktualizací WordPressu.

### Iva Vašková Core
- **Ochrana přihlášení**: po 5 chybných pokusech za 15 minut se IP adresa na 30 minut zablokuje; chybová hláška neprozradí, jestli uživatel existuje (nahrazuje reCAPTCHA na přihlášení).
- **Log přihlášení**: Nástroje → Log přihlášení ukazuje posledních 200 pokusů, v seznamu uživatelů je sloupec Poslední přihlášení (nahrazuje Simple Login Log).
- **Info o serveru**: v patičce administrace verze PHP a databáze, IP serveru a využití paměti (nahrazuje Server IP & Memory Usage).
- **Automatické aktualizace** WordPressu, pluginů, šablon i překladů (nahrazuje Easy Updates Manager).
- **Ochrana obsahu**: návštěvníkům zakáže pravé tlačítko, kopírování, výběr textu a přetahování obrázků; přihlášených se netýká. Vypíná se v Nastavení → Obecné (nahrazuje RCD Right Click Disabler).
- **Sitemap** z WordPressu (`/wp-sitemap.xml`) bez seznamu uživatelů a s datem poslední změny; staré adresy `/sitemap.xml` se po odebrání starého pluginu přesměrují (nahrazuje Google XML Sitemaps).
- **`/llms.txt`**: stručný přehled webu a nejnovějších článků pro AI.
- **Zabezpečení**: vypnuté XML-RPC, skrytý seznam uživatelů v REST API, autorské archivy a `?author=` přesměrované na titulku, bezpečnostní HTTP hlavičky.
- **Údržba webu** (Nástroje → Údržba webu): velikost a optimalizace databáze, smazání tabulek po vypnutých a odebraných pluginech, největší a nepoužité obrázky, rozbité interní odkazy, úklid revizí, koše a transientů.
- **Zdraví webu**: widget na Nástěnce s verzemi a dostupnými aktualizacemi, velikostí databáze a uploads, komentáři ke schválení a časem poslední údržby.
- **Aktualizace pluginu z GitHubu** přes běžnou obrazovku aktualizací WordPressu.

### Vydání
- Založení repozitáře, workflow vydání z tagu `vX.Y.Z` (ZIP pluginu i šablony).
- Lokální vývoj v Dockeru s PHP 7.4 (`dev/`).
