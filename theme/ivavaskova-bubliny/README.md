# Šablona Iva Vašková – Mluvící bubliny

Nový vzhled webu podle varianty 5, kterou si Iva vybrala v listopadu 2026 (`_docs/varianty-2026-11/v5.html`): texty v bublinách s ocáskem k fotkám, oblázkové fotky se zlatým obrysem, fáze lekce na dráze slunce a dětské knihy v nočním světě se zlatými hvězdami. Bez jQuery, bez build kroku, písma lokálně, bez tmavého režimu (tmavá je jen „noc“ knih).

Funkce jsou kanonické moduly skillu `wp-custom-theme` (`inc/ekt/`, `assets/ekt/`), na web se kopírují beze změny (`scripts/modules.py sync`). Rezervace, recenze a kontaktní formulář dodává plugin `ivavaskova-core` (moduly `reservations`, `testimonials`, `contact-form`); šablona je jen vykresluje a bez pluginu příslušné sekce (i tlačítka Rezervovat) vynechá.

Stará šablona „Hvězdná noc“ (`theme/ivavaskova`) zůstává v repu jako záloha. Obě se vydávají stejným tagem, každá má vlastní ZIP.

## Před aktivací na produkci

Povinně statická úvodní stránka: úvodní **Domů**, stránka příspěvků **Blog** (stránky založit předem, pokud chybí). Nastav ji **v Živém náhledu nové šablony** (Přizpůsobit → Nastavení úvodní stránky) – uloží se až s tlačítkem **Aktivovat a publikovat**. Kdyby se nastavila dřív v **Nastavení → Čtení**, stará šablona (počítá s „Posledními příspěvky“) by do přepnutí ukazovala na titulce prázdnou stránku Domů. Bez statické úvodní stránky nová šablona titulku (`front-page.php`) nepoužije a na „/“ je jen výpis článků bez úvodu, nabídek a rezervace.

## Obsah webu

| Co | Jak to šablona pozná |
|---|---|
| Titulka | stránka nastavená jako úvodní (`front-page.php`, jen se statickou úvodní stránkou); obsah stránky Domů, když nějaký má, se vypíše pod nabídkami |
| Nabídky (karty „Co nabízím“) | stránky se šablonou stránky **Nabídka**, pořadí podle Atributy stránky → Pořadí |
| Co nabízím | běžná stránka; její podstránky se pod obsahem vypíšou jako karty |
| O mně | stránka s adresou `o-mne` (fotka = náhledový obrázek, text = úryvek) |
| Moje dětské knihy | stránka se šablonou **Svět knih**; náhledový obrázek = ilustrace v obláčku, podstránky = knihy |
| Kontakt a rezervace | stránka se šablonou **Kontakt a rezervace**: obsah se shortcodem `[ekc_rezervace]` jde do rozhovoru místo formuláře; obsah bez shortcodu (úvodní text) se vypíše nad panelem a v rozhovoru je formulář modulu |
| Blog | stránka příspěvků (Nastavení → Čtení); bez ní archiv rubriky `archiv`, jinak se odkaz „Archiv článků“ nevypíše |

Na stránkách:

- **Úryvek** (panel Stránka → Úryvek) je úvodní text v bublině vedle fotky a text karty.
- **Nastavení článku → Štítky**: „skupinově, individuálně“, „e-book“… (u nabídek a knih). Nabídka se štítkem obsahujícím „skupinově“ se v rezervaci ptá na formu (skupinově / individuálně) i bez vypsaného termínu; bez něj (Energetický obraz – „individuální domluva“) jde rovnou na poptávku.
- **Nastavení článku → Výřez náhledového obrázku**: která část fotky zůstane v oblázku.
- **Vzory bloků → Iva Vašková**: „Jak lekce probíhá“ (nadpis, podtitulek, 5 fází na dráze slunce, tlačítko Objednat se), „Bublina s textem“, „Kniha v noční bublině“.
- **Styly bloků**: Skupina → Bublina / Dráha slunce (fáze) / Noc s hvězdami, Média a text → Kniha (noc).
- **Recenze** na stránce nabídky: recenze s kontextem = přesný název stránky, jak je uložený (spojovník zůstává spojovníkem). Stejný název patří i ke službě v nastavení rezervací.

## Přizpůsobit → Iva Vašková – Mluvící bubliny

- **Titulka**: slogan, text v bublině, 3 střídající se fotky úvodu, malá fotka v rohu, pruh nad hlavičkou (prázdný = nejbližší skupinový termín).
- **Patička**: kontaktní e-mail a telefon, fotka do rozhovoru (avatar), odkaz a text odběru novinek, copyright a podpis.
- **Sociální sítě**, **SEO a analytika**: kanonické volby (GA ID, sítě, popis webu).
- Menu: **Hlavní menu** (2 úrovně, Co nabízím s podmenu) a **Odkazy v patičce** (bez menu: Soma Synergy, Kundalini Activation Training, Kontakt a rezervace, Archiv článků).
- **Obecné → Logo (zlaté sluníčko)**: obrázek vedle názvu webu v hlavičce a obrázek osoby v JSON-LD; prázdné = zástupné sluníčko. Jádrové logo (Identita webu) šablona nepoužívá: `custom_logo` a globální `site_logo` sdílí WordPress se starou šablonou, logo jedné by se tak ukázalo i v druhé.

## Struktura

| Cesta | Obsah |
|---|---|
| `style.css`, `functions.php` | hlavička šablony (verze, Update URI), identita `EKT_*` (`EKT_LEGACY = []`) a načtení modulů |
| `front-page.php` | titulka: úvod, nabídky, O mně, noc knih s „portálem“, blog, rezervace, recenze |
| `page.php`, `page-nabidka.php`, `page-knihy.php`, `page-rezervace.php` | stránka, šablony stránek Nabídka, Svět knih, Kontakt a rezervace |
| `single.php`, `index.php`, `404.php`, `comments.php`, `searchform.php` | článek, výpisy (blog, rubriky, hledání), nenalezeno, komentáře, hledání |
| `template-parts/` | úvod, karty nabídek, O mně, knihy, blog, položka článku, rezervace, kontakt, recenze, začátek stránky |
| `inc/setup.php` | menu, paleta editoru, písma a preload, styly a skript, titulka jen se statickou úvodní stránkou, volby Přizpůsobit (logo, titulka, patička) a Nastavení stránky |
| `inc/template-tags.php` | pomocné funkce (`ivb_*`): nabídky, knihy, odkaz na rezervaci a blog, oblázky, štítky, sluníčko, hvězdy, pruh |
| `inc/blocks.php` | styly a vzory bloků |
| `inc/site-ivavaskova.php` | výchozí hodnoty Přizpůsobit (GA, sítě, patička, popis webu), doprovodný plugin `ivavaskova-core` pro upozornění v administraci |
| `inc/ekt/*.php` | kanonické moduly: `common`, `seo`, `analytics`, `content-links`, `lightbox`, `embeds`, `social`, `comments-antispam`, `typography`, `updater` |
| `assets/css/` | `fonts.css`, `main.css` (tokeny varianty 5 + most `--ekt-*`), `editor.css` |
| `assets/js/theme.js` | střídání fotek, menu a podmenu, tlačítko Rezervovat, slunce na dráze, balíček recenzí, vstup do noci |
| `assets/fonts/` | Bricolage Grotesque (osa opsz), Karla, Caveat, Playwrite CZ – woff2 a licence OFL |
| `assets/img/` | hvězdy a obláček (SVG) |

## Lokální vývoj

`dev/docker-compose.yml` mapuje obě šablony. Obsah pro test (stránky, fotky z návrhu, menu, ukázkové recenze): `dev/seed-bubliny.php` (postup v hlavičce souboru).
