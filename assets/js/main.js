/**
 * Eagleeye front-end interactions.
 */
(function () {
	'use strict';

	var doc = document;
	var body = doc.body;

	/* -------------------------------------------------------------
	 * Mobile navigation drawer
	 * ----------------------------------------------------------- */
	var toggle = doc.querySelector('[data-nav-toggle]');
	var drawer = doc.getElementById('mobile-drawer');
	var scrim = doc.querySelector('.drawer-scrim');

	function openDrawer() {
		if (!drawer || !scrim) {
			return;
		}
		drawer.hidden = false;
		scrim.hidden = false;
		requestAnimationFrame(function () {
			drawer.classList.add('is-open');
			scrim.classList.add('is-open');
		});
		if (toggle) {
			toggle.setAttribute('aria-expanded', 'true');
		}
		body.classList.add('nav-open');
		var first = drawer.querySelector('a, button');
		if (first) {
			first.focus({ preventScroll: true });
		}
	}

	function closeDrawer() {
		if (!drawer || !scrim) {
			return;
		}
		drawer.classList.remove('is-open');
		scrim.classList.remove('is-open');
		if (toggle) {
			toggle.setAttribute('aria-expanded', 'false');
			toggle.focus({ preventScroll: true });
		}
		body.classList.remove('nav-open');
		window.setTimeout(function () {
			if (!drawer.classList.contains('is-open')) {
				drawer.hidden = true;
				scrim.hidden = true;
			}
		}, 320);
	}

	if (toggle) {
		toggle.addEventListener('click', function () {
			if (drawer && drawer.classList.contains('is-open')) {
				closeDrawer();
			} else {
				openDrawer();
			}
		});
	}

	doc.querySelectorAll('[data-nav-close]').forEach(function (el) {
		el.addEventListener('click', closeDrawer);
	});

	/* -------------------------------------------------------------
	 * Search panel
	 * ----------------------------------------------------------- */
	var searchPanel = doc.getElementById('search');
	var searchField = searchPanel ? searchPanel.querySelector('input[type="search"]') : null;

	function openSearch() {
		if (!searchPanel) {
			return;
		}
		searchPanel.hidden = false;
		requestAnimationFrame(function () {
			searchPanel.classList.add('is-open');
		});
		if (searchField) {
			searchField.focus({ preventScroll: true });
		}
	}

	function closeSearch() {
		if (!searchPanel) {
			return;
		}
		searchPanel.classList.remove('is-open');
		window.setTimeout(function () {
			if (!searchPanel.classList.contains('is-open')) {
				searchPanel.hidden = true;
			}
		}, 320);
	}

	doc.querySelectorAll('[data-search-open]').forEach(function (el) {
		el.addEventListener('click', function (event) {
			event.preventDefault();
			openSearch();
		});
	});

	doc.querySelectorAll('[data-search-close]').forEach(function (el) {
		el.addEventListener('click', closeSearch);
	});

	/* "/" opens search, the shortcut readers already expect on a news site. */
	doc.addEventListener('keydown', function (event) {
		if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) {
			return;
		}

		var field = event.target;
		var isTyping = field && (
			field.tagName === 'INPUT' ||
			field.tagName === 'TEXTAREA' ||
			field.isContentEditable
		);

		if (isTyping || !searchPanel) {
			return;
		}

		event.preventDefault();
		openSearch();
	});

	doc.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') {
			return;
		}
		if (searchPanel && searchPanel.classList.contains('is-open')) {
			closeSearch();
		}
		if (drawer && drawer.classList.contains('is-open')) {
			closeDrawer();
		}
	});

	/* -------------------------------------------------------------
	 * Sticky navigation shadow
	 * ----------------------------------------------------------- */
	var nav = doc.querySelector('[data-sticky-nav]');
	if (nav && 'IntersectionObserver' in window) {
		var sentinel = doc.createElement('div');
		sentinel.setAttribute('aria-hidden', 'true');
		nav.parentNode.insertBefore(sentinel, nav);
		new IntersectionObserver(function (entries) {
			nav.classList.toggle('is-stuck', !entries[0].isIntersecting);
		}, { rootMargin: '-48px 0px 0px 0px' }).observe(sentinel);
	}

	/* -------------------------------------------------------------
	 * Ticker: pace the marquee to its content width
	 * ----------------------------------------------------------- */
	doc.querySelectorAll('[data-ticker]').forEach(function (ticker) {
		var set = ticker.querySelector('.ticker__set');
		var track = ticker.querySelector('.ticker__track');
		if (!set || !track) {
			return;
		}
		var width = set.getBoundingClientRect().width;
		if (width > 0) {
			var duration = Math.max(24, Math.round(width / 62));
			track.style.animationDuration = duration + 's';
		}
	});

	/* -------------------------------------------------------------
	 * Reading progress on articles
	 * ----------------------------------------------------------- */
	var articleBody = doc.querySelector('.entry-content');
	if (articleBody) {
		var bar = doc.createElement('div');
		bar.className = 'reading-progress';
		bar.setAttribute('aria-hidden', 'true');
		bar.innerHTML = '<span></span>';
		body.appendChild(bar);
		var fill = bar.firstChild;

		var updateProgress = function () {
			var rect = articleBody.getBoundingClientRect();
			var total = rect.height - window.innerHeight + 240;
			var scrolled = Math.min(Math.max(-rect.top + 240, 0), Math.max(total, 1));
			fill.style.transform = 'scaleX(' + (scrolled / Math.max(total, 1)) + ')';
		};

		var ticking = false;
		window.addEventListener('scroll', function () {
			if (ticking) {
				return;
			}
			ticking = true;
			requestAnimationFrame(function () {
				updateProgress();
				ticking = false;
			});
		}, { passive: true });
		updateProgress();
	}

	/* -------------------------------------------------------------
	 * Reveal on scroll
	 * ----------------------------------------------------------- */
	var reveals = doc.querySelectorAll('.hero, .brief, .section, .bigread, .opinion-band, .newsletter');
	reveals.forEach(function (el) {
		el.classList.add('reveal');
	});

	if ('IntersectionObserver' in window) {
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
					observer.unobserve(entry.target);
				}
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

		reveals.forEach(function (el) {
			observer.observe(el);
		});

		// Safety net: never leave content hidden if the observer stalls.
		window.setTimeout(function () {
			reveals.forEach(function (el) {
				if (!el.classList.contains('is-visible')) {
					el.classList.add('reveal-fallback');
				}
			});
		}, 1600);
	} else {
		reveals.forEach(function (el) {
			el.classList.add('is-visible');
		});
	}

	/* -------------------------------------------------------------
	 * Copy link
	 * ----------------------------------------------------------- */
	doc.querySelectorAll('[data-copy-url]').forEach(function (button) {
		button.addEventListener('click', function () {
			var url = button.getAttribute('data-copy-url') || window.location.href;
			var label = button.textContent;

			var done = function () {
				button.textContent = (window.eagleeyeData && window.eagleeyeData.labels.copied) || 'Link copied';
				window.setTimeout(function () {
					button.textContent = label;
				}, 1800);
			};

			var fallback = function () {
				var field = document.createElement('textarea');
				var copied = false;

				field.value = url;
				field.setAttribute('readonly', '');
				field.style.position = 'fixed';
				field.style.top = '-9999px';
				field.style.opacity = '0';
				document.body.appendChild(field);
				field.select();

				try {
					copied = document.execCommand('copy');
				} catch (error) {
					copied = false;
				}

				document.body.removeChild(field);

				if (copied) {
					done();
				} else {
					window.prompt('Copy this link', url);
				}
			};

			if (navigator.clipboard && window.isSecureContext && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(done).catch(fallback);
			} else {
				fallback();
			}
		});
	});
})();
