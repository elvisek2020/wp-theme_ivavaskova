/*
 * Šablona „Mluvící bubliny“: střídání fotek úvodu, mobilní menu a podmenu, plovoucí tlačítko
 * Rezervovat, slunce na dráze fází, balíček recenzí, vstup do noci knih. Bez knihoven.
 * Respektuje prefers-reduced-motion (bez střídání a animací).
 */
(function () {
	'use strict';

	var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var root = document.documentElement;
	var hasIO = 'IntersectionObserver' in window;

	/* ---------- Fotky úvodu ---------- */
	document.querySelectorAll('[data-ivb-rotate]').forEach(function (box) {
		var imgs = box.querySelectorAll('img'), i = 0;
		if (reduce || imgs.length < 2) { return; }
		setInterval(function () {
			if (document.hidden) { return; }
			imgs[i].classList.remove('on');
			i = (i + 1) % imgs.length;
			imgs[i].classList.add('on');
		}, 5500);
	});

	/* ---------- Menu ---------- */
	var nav = document.getElementById('nav');
	var openBtn = document.querySelector('[data-ivb-menu-open]');
	var closeBtn = document.querySelector('[data-ivb-menu-close]');
	var wide = window.matchMedia('(min-width: 981px)');
	function setMenu(open) {
		if (!nav) { return; }
		nav.classList.toggle('open', open);
		root.classList.toggle('menu-open', open);
		if (openBtn) { openBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
		if (open && closeBtn) { closeBtn.focus(); }
	}
	if (nav && openBtn) {
		openBtn.addEventListener('click', function () { setMenu(true); });
		if (closeBtn) { closeBtn.addEventListener('click', function () { setMenu(false); openBtn.focus(); }); }
		nav.addEventListener('click', function (e) { if (e.target.closest('a')) { setMenu(false); } });
		// Otevřený panel překrývá stránku: Tab a Shift+Tab kolují jen mezi jeho viditelnými prvky.
		nav.addEventListener('keydown', function (e) {
			if (e.key !== 'Tab' || !nav.classList.contains('open') || wide.matches) { return; }
			var f = Array.prototype.filter.call(nav.querySelectorAll('a[href], button:not([disabled])'), function (el) { return el.getClientRects().length > 0; });
			if (!f.length) { return; }
			var first = f[0], last = f[f.length - 1], act = document.activeElement;
			if (e.shiftKey && act === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && act === last) { e.preventDefault(); first.focus(); }
		});
		// Po rozšíření okna na desktopové menu se panel zavře, aby nezůstal stav „menu-open“.
		var onWide = function () { if (wide.matches && nav.classList.contains('open')) { setMenu(false); } };
		if (wide.addEventListener) { wide.addEventListener('change', onWide); } else if (wide.addListener) { wide.addListener(onWide); }
	}

	/* podmenu: tlačítko se šipkou vedle odkazu nadřazené položky */
	var parents = nav ? nav.querySelectorAll('.nav__list > .menu-item-has-children, .nav__list > .page_item_has_children') : [];
	function closeAll(except) {
		parents.forEach(function (li) {
			if (li !== except) {
				li.classList.remove('open');
				var b = li.querySelector(':scope > .dd-btn');
				if (b) { b.setAttribute('aria-expanded', 'false'); }
			}
		});
	}
	parents.forEach(function (li, n) {
		var link = li.querySelector(':scope > a');
		var sub = li.querySelector(':scope > .sub-menu, :scope > .children');
		if (!link || !sub) { return; }
		sub.id = sub.id || 'ivb-sub-' + n;
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'dd-btn';
		btn.setAttribute('aria-expanded', 'false');
		btn.setAttribute('aria-controls', sub.id);
		btn.setAttribute('aria-label', 'Podmenu ' + link.textContent.trim());
		btn.innerHTML = '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 4l4 4 4-4"/></svg>';
		link.after(btn);
		btn.addEventListener('click', function () {
			var open = !li.classList.contains('open');
			closeAll(li);
			li.classList.toggle('open', open);
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		li.addEventListener('focusout', function (e) { if (!li.contains(e.relatedTarget)) { li.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); } });
	});
	document.addEventListener('click', function (e) { if (nav && !nav.contains(e.target)) { closeAll(null); } });
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		// Fokus uvnitř otevřeného podmenu se vrátí na jeho tlačítko (jinak by zmizel se skrytým podmenu).
		var act = document.activeElement;
		var li = act && act.closest ? act.closest('.nav__list > li.open') : null;
		var back = li ? li.querySelector(':scope > .dd-btn') : null;
		closeAll(null);
		if (nav && nav.classList.contains('open')) { setMenu(false); if (openBtn) { openBtn.focus(); } return; }
		if (back && act !== back) { back.focus(); }
	});

	/* ---------- Plovoucí tlačítko Rezervovat (skryté v úvodu, u rezervace a v patičce) ---------- */
	var fab = document.querySelector('[data-ivb-fab]');
	if (fab && hasIO) {
		var seen = new Map();
		var watch = document.querySelectorAll('.hero, .page-top, .single-top, #rezervace, .res-panel, .foot, .ekc-res');
		var fio = new IntersectionObserver(function (entries) {
			entries.forEach(function (x) { seen.set(x.target, x.isIntersecting); });
			var any = false;
			seen.forEach(function (v) { any = any || v; });
			fab.classList.toggle('show', !any);
		});
		watch.forEach(function (el) { fio.observe(el); });
	}

	/* ---------- Dráha slunce (styl bloku Skupina: Dráha slunce) ---------- */
	document.querySelectorAll('.is-style-ivb-arc').forEach(function (arc) {
		arc.insertAdjacentHTML('afterbegin',
			'<svg class="arc-line" viewBox="0 0 1000 660" preserveAspectRatio="none" aria-hidden="true"><path d="M100 540 A400 400 0 0 1 900 540" fill="none" stroke="#C9A54A" stroke-width="1" stroke-dasharray="6 7" vector-effect="non-scaling-stroke"/></svg>' +
			'<svg class="arc-wave" viewBox="0 0 40 600" preserveAspectRatio="none" aria-hidden="true"><path d="M20 0 C40 50 0 100 20 150 S40 250 20 300 S0 400 20 450 S40 550 20 600" fill="none" stroke="#C9A54A" stroke-width="1" stroke-dasharray="6 7" vector-effect="non-scaling-stroke"/></svg>' +
			'<svg class="arc-sun" viewBox="0 0 100 100" aria-hidden="true"><use href="#ivb-sun"/></svg>');
		var sun = arc.querySelector('.arc-sun');
		if (!sun) { return; }
		function place(t) {
			var a = Math.PI * (1 - t);
			sun.style.left = (50 + 40 * Math.cos(a)) + '%';
			sun.style.top = (540 - 400 * Math.sin(a)) + 'px';
		}
		if (reduce || !hasIO) { place(0.12); return; }
		var done = false;
		new IntersectionObserver(function (en) {
			if (!en[0].isIntersecting || done || getComputedStyle(sun).display === 'none') { return; }
			done = true;
			var t0 = null;
			requestAnimationFrame(function step(ts) {
				if (!t0) { t0 = ts; }
				var p = Math.min((ts - t0) / 5200, 1);
				place(p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2);
				if (p < 1) { requestAnimationFrame(step); }
			});
		}, { threshold: 0.45 }).observe(arc);
	});

	/* ---------- Recenze – balíček bublin ---------- */
	document.querySelectorAll('[data-ivb-deck]').forEach(function (deck) {
		var cards = Array.prototype.slice.call(deck.querySelectorAll('.rev-card'));
		if (cards.length < 2) { return; }
		var wrap = deck.closest('.deck-wrap') || deck.parentNode;
		var order = cards.map(function (_, i) { return i; });
		var busy = false, paused = false, timer = null;
		function apply() {
			order.forEach(function (ci, pos) {
				cards[ci].dataset.pos = pos;
				if (pos === 0) { cards[ci].removeAttribute('aria-hidden'); } else { cards[ci].setAttribute('aria-hidden', 'true'); }
			});
		}
		function next() {
			if (busy) { return; }
			var front = cards[order[0]];
			if (reduce) { order.push(order.shift()); apply(); return; }
			busy = true;
			front.classList.add('out');
			setTimeout(function () { order.push(order.shift()); apply(); front.classList.remove('out'); busy = false; }, 480);
		}
		function prev() { if (busy) { return; } order.unshift(order.pop()); apply(); }
		function restart() { clearInterval(timer); if (!paused && !reduce) { timer = setInterval(next, 8000); } }
		apply();
		var nb = wrap.querySelector('[data-ivb-deck-next]'), pb = wrap.querySelector('[data-ivb-deck-prev]'), sb = wrap.querySelector('[data-ivb-deck-pause]');
		if (nb) { nb.addEventListener('click', function () { next(); restart(); }); }
		if (pb) { pb.addEventListener('click', function () { prev(); restart(); }); }
		if (sb) {
			if (reduce) { sb.hidden = true; }
			sb.addEventListener('click', function () {
				paused = !paused;
				sb.setAttribute('aria-pressed', paused ? 'true' : 'false');
				sb.setAttribute('aria-label', paused ? 'Spustit střídání recenzí' : 'Pozastavit střídání recenzí');
				sb.innerHTML = paused
					? '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5l11 7-11 7z"/></svg>'
					: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="7" y="5" width="3.5" height="14" rx="1.5"/><rect x="13.5" y="5" width="3.5" height="14" rx="1.5"/></svg>';
				restart();
			});
		}
		if (hasIO) {
			var started = false;
			new IntersectionObserver(function (en) {
				if (en[0].isIntersecting && !started) { started = true; restart(); }
			}, { threshold: 0.6 }).observe(deck);
		} else { restart(); }
		var sx = null;
		deck.addEventListener('pointerdown', function (e) { sx = e.clientX; });
		deck.addEventListener('pointerup', function (e) {
			if (sx === null) { return; }
			var dx = e.clientX - sx; sx = null;
			if (dx < -40) { next(); restart(); } else if (dx > 40) { prev(); restart(); }
		});
	});

	/* ---------- Rezervace jako rozhovor ----------
	 * Lekce → skupinově / individuálně → termín a přihláška (.ekc-res, modul reservations), nebo
	 * poptávka (modul contact-form). Bez JS jsou oba formuláře vidět pod sebou. */
	document.querySelectorAll('[data-ivb-chat]').forEach(function (chat) {
		var res = chat.querySelector('.ekc-res');
		var partGroup = chat.querySelector('[data-ivb-part="group"]');
		var partSingle = chat.querySelector('[data-ivb-part="single"]');
		var svcBox = chat.querySelector('[data-ivb-services]');
		var singleText = chat.querySelector('[data-ivb-single-text]');
		var inquiry = partSingle ? partSingle.querySelector('textarea') : null;
		var norm = function (s) { return (s || '').replace(/\s+/g, ' ').trim().toLowerCase(); };
		var groups = res ? Array.prototype.slice.call(res.querySelectorAll('.ekc-res__group')) : [];
		var nameOf = function (g) { var h = g.querySelector('.ekc-res__service'); return h ? h.textContent.trim() : ''; };
		var groupOf = function (service) { return service ? groups.filter(function (g) { return norm(nameOf(g)) === norm(service); })[0] || null : null; };
		var hasOpen = function (g) { return !!(g && g.querySelector('.ekc-res__event--open')); };
		if (!svcBox) { return; }
		// Otevřené termíny bez názvu nabídky (termín bez nabídky, shortcode se sluzba="…") nejdou
		// přiřadit k odpovědi: rozhovor se nezapne a zůstanou oba formuláře pod sebou jako bez JS.
		if (groups.some(function (g) { return hasOpen(g) && !nameOf(g); })) { return; }

		// Nabídky s otevřenými termíny, které nemají vlastní stránku, se do výběru doplní.
		groups.forEach(function (g) {
			var name = nameOf(g);
			if (!name || !hasOpen(g)) { return; }
			var known = Array.prototype.some.call(svcBox.querySelectorAll('input'), function (i) { return norm(i.value) === norm(name); });
			if (known) { return; }
			var l = document.createElement('label');
			l.className = 'ans';
			l.innerHTML = '<input type="radio" name="ivb_service"><span></span>';
			l.querySelector('input').value = name;
			l.querySelector('span').textContent = name;
			svcBox.appendChild(l);
		});
		if (!svcBox.querySelector('input')) { return; } // nic na výběr: zůstanou oba formuláře

		var state = { service: '', mode: '' }, prefilled = '';
		var steps = function (name) { return chat.querySelectorAll('[data-ivb-step="' + name + '"]'); };
		var show = function (name, on) { steps(name).forEach(function (el) { el.hidden = !on; }); };

		function render() {
			var g = groupOf(state.service), open = hasOpen(g);
			// Nabídka se štítkem „skupinově“ (data-ivb-group) se na formu ptá i bez vypsaného termínu.
			var picked = svcBox.querySelector('input:checked');
			var groupy = !!(picked && picked.hasAttribute('data-ivb-group'));
			show('service', true);
			var askMode = !!state.service && !!partSingle && (open || groupy);
			show('mode', askMode);
			if (state.service && !askMode) { state.mode = open ? 'group' : (partSingle ? 'single' : 'group'); }
			chat.querySelectorAll('input[name="ivb_mode"]').forEach(function (i) { i.checked = i.value === state.mode; });

			// Skupinově bez otevřeného termínu: místo přihlášky poptávka („dám vědět o dalším termínu“).
			var noTerm = state.mode === 'group' && !open && !!partSingle && !!state.service;
			var showGroup = state.mode === 'group' && !noTerm, showSingle = state.mode === 'single' || noTerm;
			if (partGroup) { partGroup.hidden = !showGroup; }
			if (partSingle) { partSingle.hidden = !showSingle; }
			show('group', showGroup);
			show('single', showSingle);

			// Jen termíny vybrané nabídky; zaškrtnutí v jiné nabídce se zruší.
			groups.forEach(function (x) {
				var mine = !g || x === g;
				x.hidden = !mine;
				if (!mine) { x.querySelectorAll('input[type="radio"]').forEach(function (r) { r.checked = false; }); }
			});

			if (showSingle) {
				if (singleText) {
					singleText.textContent = noTerm
						? 'Skupinový termín teď vypsaný není. Napiš a dám vědět o dalším – nebo domluvíme individuální lekci.'
						: open
							? 'Individuální lekci domluvíme po odeslání – napiš, kdy by se hodila.'
							: (state.service ? 'Termín se domlouvá individuálně – stačí napsat.' : 'Napiš, domluvíme termín.');
				}
				if (inquiry && state.service && (inquiry.value === '' || inquiry.value === prefilled)) {
					prefilled = 'Dobrý den, mám zájem o ' + (noTerm ? 'skupinovou lekci ' : (open ? 'individuální lekci ' : '')) + state.service + '. ';
					inquiry.value = prefilled;
				}
			}
		}

		chat.classList.add('chat--on');
		svcBox.addEventListener('change', function (e) {
			if (e.target.name !== 'ivb_service') { return; }
			state.service = e.target.value;
			state.mode = '';
			render();
		});
		chat.addEventListener('change', function (e) {
			if (e.target.name !== 'ivb_mode') { return; }
			state.mode = e.target.value;
			render();
			var target = chat.querySelector('[data-ivb-part]:not([hidden])');
			if (target && target.scrollIntoView) { target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'nearest' }); }
		});

		// Výchozí stav: výsledek poptávky, výsledek rezervace, termín z odkazu (?termin=ID), jinak
		// začátek rozhovoru. Modul sám zaškrtne i jediný volný termín – to ještě není volba návštěvníka.
		var checked = res ? res.querySelector('.ekc-res__radio:checked') : null;
		var resDone = res && res.querySelector('.ekc-form__notice, .ekc-res__summary');
		var askDone = partSingle && partSingle.querySelector('.ekc-form__notice');
		var fromUrl = /[?&]termin=\d/.test(location.search);
		if (askDone) {
			state.mode = 'single';
		} else if (resDone || (checked && fromUrl)) {
			var cg = checked ? checked.closest('.ekc-res__group') : null;
			state.service = cg ? nameOf(cg) : '';
			state.mode = 'group';
		}
		svcBox.querySelectorAll('input').forEach(function (i) { i.checked = !!state.service && norm(i.value) === norm(state.service); });
		render();
	});

	/* Cena termínu zadaná jen číslem („800“) dostane „Kč“. */
	document.querySelectorAll('.ekc-res__price').forEach(function (el) {
		var t = el.textContent.trim();
		if (/^\d[\d\s.,]*$/.test(t)) { el.textContent = t + '\u00a0Kč'; }
	});

	/* ---------- Noc knih: vstup a výstup skrz bublinu ---------- */
	var portals = document.querySelectorAll('[data-ivb-portal]');
	if (portals.length) {
		var mq = window.matchMedia('(prefers-reduced-motion: no-preference) and (max-height: 1300px)');
		var ticking = false;
		var onScroll = function () {
			if (!root.classList.contains('portal-on') || ticking) { return; }
			ticking = true;
			requestAnimationFrame(function () {
				ticking = false;
				var w = innerWidth, h = innerHeight, rMax = 0.75 * Math.hypot(w, h);
				portals.forEach(function (p) {
					var r = p.getBoundingClientRect(), total = p.offsetHeight - h;
					if (total <= 0) { return; }
					var k = Math.min(Math.max(-r.top / total, 0), 1);
					var st = p.querySelector('.stage'), e;
					if (p.dataset.ivbPortal === 'in') {
						e = Math.pow(k, 1.7);
						st.style.setProperty('--r', (40 + (rMax - 40) * e) + 'px');
						st.style.setProperty('--t', Math.min(Math.max((k - 0.45) / 0.35, 0), 1));
						st.style.setProperty('--capo', Math.max(1 - k * 4, 0));
					} else {
						e = Math.pow(1 - k, 1.7);
						st.style.setProperty('--r', (40 + (rMax - 40) * e) + 'px');
					}
				});
			});
		};
		// Výstup se zasune pod poslední výšku okna světa knih (nejvýš celou jeho výšku): zavírání
		// bubliny běží, zatímco knihy odjíždějí, místo prázdné noci za nimi.
		var out = document.querySelector('[data-ivb-portal="out"]');
		var books = document.getElementById('knihy');
		var fitOut = function () {
			if (!out || !books) { return; }
			if (!root.classList.contains('portal-on')) { out.style.marginTop = out.style.height = ''; return; }
			var o = Math.min(innerHeight, books.offsetHeight);
			out.style.marginTop = -o + 'px';
			out.style.height = (innerHeight + o) + 'px';
		};
		var setMode = function () { root.classList.toggle('portal-on', mq.matches); fitOut(); onScroll(); };
		if (mq.addEventListener) { mq.addEventListener('change', setMode); }
		addEventListener('scroll', onScroll, { passive: true });
		addEventListener('resize', function () { fitOut(); onScroll(); });
		if (books && window.ResizeObserver) { new ResizeObserver(function () { fitOut(); onScroll(); }).observe(books); }
		setMode();
	}
})();
