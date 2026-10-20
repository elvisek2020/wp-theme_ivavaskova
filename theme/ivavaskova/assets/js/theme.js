/**
 * Šablona Iva Vašková — menu na mobilu, podmenu, hledání, přepínač režimu v patičce, lightbox.
 * Bez jQuery.
 */
(function () {
	'use strict';

	var root = document.documentElement;

	/* ---------- Světlý / tmavý režim (přepínač v patičce: Systém / Světlý / Tmavý) ---------- */
	var themeButtons = document.querySelectorAll('[data-iv-theme-set]');
	function syncTheme() {
		var t = root.getAttribute('data-theme') || 'auto';
		themeButtons.forEach(function (b) {
			b.setAttribute('aria-pressed', b.getAttribute('data-iv-theme-set') === t ? 'true' : 'false');
		});
	}
	themeButtons.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var t = btn.getAttribute('data-iv-theme-set');
			if (t === 'auto') {
				root.removeAttribute('data-theme');
				try { localStorage.removeItem('iv-theme'); } catch (e) {}
			} else {
				root.setAttribute('data-theme', t);
				try { localStorage.setItem('iv-theme', t); } catch (e) {}
			}
			syncTheme();
		});
	});
	syncTheme();

	/* ---------- Menu ---------- */
	var toggle = document.querySelector('.iv-menu-toggle');
	var nav = document.getElementById('iv-nav');
	if (toggle && nav) {
		toggle.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}
	// Položky s podmenu: klik na rodiče (často odkaz „#“) podmenu otevře / zavře.
	document.querySelectorAll('.iv-menu .menu-item-has-children > a').forEach(function (a) {
		a.setAttribute('aria-haspopup', 'true');
		a.setAttribute('aria-expanded', 'false');
		a.addEventListener('click', function (e) {
			var li = a.parentNode;
			var href = a.getAttribute('href') || '';
			var mobile = window.matchMedia('(max-width: 860px)').matches;
			if (href === '#' || href === '' || (mobile && !li.classList.contains('is-open'))) {
				e.preventDefault();
				var open = !li.classList.contains('is-open');
				li.parentNode.querySelectorAll(':scope > li.is-open').forEach(function (o) {
					if (o !== li) { o.classList.remove('is-open'); o.firstElementChild.setAttribute('aria-expanded', 'false'); }
				});
				li.classList.toggle('is-open', open);
				a.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
		});
	});
	document.addEventListener('click', function (e) {
		if (!e.target.closest('.iv-menu')) {
			document.querySelectorAll('.iv-menu li.is-open').forEach(function (li) {
				li.classList.remove('is-open');
				li.firstElementChild.setAttribute('aria-expanded', 'false');
			});
		}
	});

	/* ---------- Hledání v hlavičce ---------- */
	var searchBtn = document.querySelector('[data-iv-search]');
	var searchBar = document.querySelector('.iv-searchbar');
	if (searchBtn && searchBar) {
		searchBtn.addEventListener('click', function (e) {
			e.preventDefault();
			searchBar.hidden = !searchBar.hidden;
			if (!searchBar.hidden) {
				var input = searchBar.querySelector('input[type="search"]');
				if (input) { input.focus(); }
			}
		});
	}

	/* ---------- Lightbox ---------- */
	var box = document.getElementById('iv-lightbox');
	var links = Array.prototype.slice.call(document.querySelectorAll('a[data-iv-lightbox]'));
	if (box && links.length) {
		var img = box.querySelector('img');
		var cap = box.querySelector('figcaption');
		var index = 0;
		var lastFocus = null;

		var captionFor = function (a) {
			var fig = a.closest('figure, .gallery-item, .wp-caption');
			var c = fig && fig.querySelector('figcaption, .wp-caption-text, .gallery-caption');
			var i = a.querySelector('img');
			return (c && c.textContent.trim()) || (i && i.getAttribute('alt')) || '';
		};
		var show = function (i) {
			index = (i + links.length) % links.length;
			var a = links[index];
			img.src = a.getAttribute('href');
			img.alt = captionFor(a);
			cap.textContent = captionFor(a);
		};
		var open = function (i) {
			lastFocus = document.activeElement;
			box.classList.toggle('is-single', links.length < 2);
			show(i);
			box.hidden = false;
			document.body.classList.add('iv-lb-open');
			box.querySelector('.iv-lb-close').focus();
		};
		var close = function () {
			box.hidden = true;
			img.removeAttribute('src');
			document.body.classList.remove('iv-lb-open');
			if (lastFocus) { lastFocus.focus(); }
		};

		links.forEach(function (a, i) {
			a.addEventListener('click', function (e) {
				if (e.metaKey || e.ctrlKey || e.shiftKey) { return; }
				e.preventDefault();
				open(i);
			});
		});
		box.querySelector('.iv-lb-close').addEventListener('click', close);
		box.querySelector('.iv-lb-prev').addEventListener('click', function () { show(index - 1); });
		box.querySelector('.iv-lb-next').addEventListener('click', function () { show(index + 1); });
		box.addEventListener('click', function (e) { if (e.target === box || e.target.tagName === 'FIGURE') { close(); } });
		document.addEventListener('keydown', function (e) {
			if (box.hidden) { return; }
			if (e.key === 'Escape') { close(); }
			if (e.key === 'ArrowLeft') { show(index - 1); }
			if (e.key === 'ArrowRight') { show(index + 1); }
		});
		// Přejetí prstem na mobilu.
		var startX = null;
		box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
		box.addEventListener('touchend', function (e) {
			if (startX === null) { return; }
			var dx = e.changedTouches[0].clientX - startX;
			if (Math.abs(dx) > 50) { show(index + (dx < 0 ? 1 : -1)); }
			startX = null;
		});
	}
})();
