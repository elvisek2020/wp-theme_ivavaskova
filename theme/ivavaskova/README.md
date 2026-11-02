# Šablona Iva Vašková

Lehká hybridní šablona (PHP šablony + `theme.json`) ve stylu „Hvězdná noc“ — barvy z ilustrace v logu. Bez jQuery, bez build kroku, písma lokálně. Funkce jsou kanonické moduly skillu `wp-custom-theme` (`inc/ekt/`, `assets/ekt/`), které se na web kopírují beze změny (`scripts/modules.py sync`); vlastní je jen vzhled. Funkce nezávislé na vzhledu jsou v pluginu `ivavaskova-core`.

## Struktura

| Cesta | Obsah |
|---|---|
| `style.css` | hlavička šablony (verze, Update URI) |
| `functions.php` | identita (`EKT_*`, `EKT_LEGACY = ['iv']`) a načtení modulů |
| `theme.json` | paleta (odkazuje na CSS proměnné), šířky obsahu, velikosti písma pro editor |
| `header.php`, `footer.php` | hlavička (logo, menu, hledání), patička (sítě, copyright, přepínač Systém / Světlý / Tmavý) |
| `index.php` | výpis článků: titulka, rubriky, štítky, hledání |
| `single.php`, `page.php` | článek, stránka (obsah v `data-ekt-content` kvůli lightboxu) |
| `comments.php`, `searchform.php`, `404.php` | komentáře, hledání, nenalezeno |
| `template-parts/card.php` | karta článku ve výpisu |
| `inc/setup.php` | menu, logo, velikosti obrázků, písma a jejich preload, styly a skript, barva lišty prohlížeče, vydavatel Person v JSON-LD, 9 článků na stránku, galerie |
| `inc/template-tags.php` | logo s názvem, datum, rubriky, meta článku, stránkování |
| `inc/site-ivavaskova.php` | výchozí hodnoty Přizpůsobit ze starého webu (GA ID, sítě, patička, popis webu) |
| `inc/ekt/*.php` | kanonické moduly: `common`, `seo`, `analytics`, `content-links`, `lightbox`, `embeds`, `social`, `color-scheme`, `comments-antispam`, `updater` |
| `assets/ekt/` | JS a funkční CSS kanonických modulů |
| `assets/css/` | `fonts.css`, `main.css` (tokeny nahoře + most `--ekt-*`), `editor.css`, `print.css` |
| `assets/js/theme.js` | menu, podmenu, hledání |
| `assets/fonts/` | Comfortaa a Open Sans (woff2, latin + latin-ext), licence OFL |
