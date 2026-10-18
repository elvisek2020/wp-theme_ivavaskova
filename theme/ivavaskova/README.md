# Šablona Iva Vašková

Lehká hybridní šablona (PHP šablony + `theme.json`) ve stylu „Hvězdná noc“ — barvy z ilustrace v logu. Bez jQuery, bez build kroku, písma lokálně. Funkce nezávislé na vzhledu jsou v pluginu `ivavaskova-core`.

## Struktura

| Cesta | Obsah |
|---|---|
| `style.css` | hlavička šablony (verze!) |
| `functions.php` | konstanty `IV_VERSION/DIR/URI` + seznam modulů z `inc/` |
| `theme.json` | paleta (odkazuje na CSS proměnné), šířky obsahu, velikosti písma pro editor |
| `header.php`, `footer.php` | hlavička (logo, menu, hledání, přepínač režimu), patička (sítě, copyright, cookies, lightbox) |
| `index.php` | výpis článků: titulka, rubriky, štítky, hledání |
| `single.php`, `page.php` | článek, stránka |
| `comments.php`, `searchform.php`, `404.php` | komentáře, hledání, nenalezeno |
| `template-parts/card.php` | karta článku ve výpisu |
| `inc/setup.php` | podpora šablony, menu, velikosti obrázků, úklid `<head>`, převzetí menu a loga z Dollah, upozornění na chybějící plugin |
| `inc/assets.php` | CSS/JS s verzí podle `filemtime`, preload písem, nastavení režimu bez probliknutí |
| `inc/template-tags.php` | `iv_icon()`, datum, rubriky, meta, logo, sociální sítě, stránkování |
| `inc/customizer.php` | Přizpůsobit → Iva Vašková: Instagram, Facebook, YouTube, text v patičce, GA4 |
| `inc/content.php` | externí odkazy, lightbox k obrázkům a galeriím, responzivní videa |
| `inc/seo.php` | meta description, Open Graph, JSON-LD |
| `inc/analytics-consent.php` | GA4 s Consent Mode v2 + cookie lišta |
| `inc/comments-antispam.php` | honeypot a podepsané časové razítko v komentářích |
| `inc/updater.php` | aktualizace z GitHub Releases |
| `assets/css/` | `fonts.css`, `main.css` (tokeny nahoře), `editor.css`, `print.css` |
| `assets/js/theme.js` | menu, podmenu, hledání, světlý/tmavý režim, lightbox |
| `assets/fonts/` | Comfortaa a Open Sans (woff2, latin + latin-ext), licence OFL |
