/**
 * Šablona Iva Vašková — menu na mobilu, podmenu a hledání v hlavičce. Bez jQuery.
 * Světlý/tmavý režim, lightbox a cookie lišta jsou v kanonických modulech (assets/ekt/js/).
 */
(function () {
	'use strict';

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
})();
