/* ==========================================================================
   EAGLEEYE motion layer — GSAP + ScrollTrigger
   Progressive enhancement: without JavaScript or with reduced motion the
   page renders exactly as authored.
   ========================================================================== */
(function () {
	'use strict';

	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var progressBar = document.querySelector('.ee-progress span');

	/* On phones the contents panel starts collapsed so the policy opens with
	   its readable text rather than a long list of links. */
	var policyToc = document.querySelector('.policy-toc');
	if (policyToc && window.innerWidth < 900) {
		policyToc.removeAttribute('open');
	}

	/* Reading progress and print actions work independently of motion. */
	function updateProgress() {
		if (!progressBar) {
			return;
		}

		var max = document.documentElement.scrollHeight - window.innerHeight;
		var progress = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;

		progressBar.style.transform = 'scaleX(' + progress + ')';
	}

	if (progressBar) {
		updateProgress();
		window.addEventListener('scroll', updateProgress, { passive: true });
		window.addEventListener('resize', updateProgress);
	}

	document.addEventListener('click', function (event) {
		var printButton = event.target.closest('[data-print-page]');

		if (printButton) {
			event.preventDefault();
			window.print();
		}
	});

	/* Media settling. An image that is still downloading fades in when it
	   arrives instead of stamping itself onto the page, and the picture that
	   leads a page is uncovered once it is genuinely there. */
	function settleImage(img, isLead) {
		img.classList.remove('ee-img-pending');
		img.classList.add('ee-img-ready');

		if (isLead && !reduceMotion) {
			img.classList.add('ee-curtain');
		}
	}

	var leadImage = document.querySelector(
		'.hero__lead img, .article__figure img, .article-hero img, .entry-hero img, .post-thumbnail img, .bigread__media img, .brand-page-hero img'
	);

	Array.prototype.forEach.call(
		document.querySelectorAll('.entry-content img, .article__figure img, .card__media img, .hero__media img, .ee-ad img, .bigread__media img'),
		function (img) {
			var isLead = img === leadImage;

			if (img.complete && img.naturalWidth > 0) {
				settleImage(img, isLead);
				return;
			}

			img.classList.add('ee-img-pending');

			var settle = function () {
				settleImage(img, isLead);
			};

			img.addEventListener('load', settle, { once: true });
			img.addEventListener('error', settle, { once: true });
		}
	);

	if (!window.gsap || !window.ScrollTrigger || reduceMotion) {
		return;
	}

	var gsap = window.gsap;
	var ScrollTrigger = window.ScrollTrigger;

	gsap.registerPlugin(ScrollTrigger);
	gsap.defaults({ ease: 'power2.out', duration: 0.7 });
	document.documentElement.classList.add('has-motion');

	/* Masthead: a quiet entrance. */
	var brand = document.querySelector('.ee-brand');
	if (brand) {
		gsap.from(brand, { y: -8, duration: 0.55 });
	}

	/* Homepage lead and features. */
	var heroLead = document.querySelector('.hero__lead');
	var heroCards = gsap.utils.toArray('.hero__features .card');

	if (heroLead) {
		gsap.from(heroLead, { y: 26, duration: 0.85 });
	}

	if (heroCards.length) {
		gsap.from(heroCards, {
			y: 22,
			duration: 0.7,
			stagger: 0.08,
			delay: 0.1
		});
	}

	/* Brand pages: copy and logo panel settle into place together. */
	var brandHero = document.querySelector('.about-hero__copy, .brand-page-hero__copy');
	if (brandHero) {
		gsap.from(brandHero.children, {
			y: 26,
			opacity: 0,
			duration: 0.8,
			stagger: 0.07,
			clearProps: 'opacity,transform'
		});
	}

	var heroPanel = document.querySelector('.about-hero__seal, .brand-page-hero__panel, .page-hero__aside');
	if (heroPanel) {
		gsap.from(heroPanel, {
			y: 30,
			opacity: 0,
			duration: 0.95,
			delay: 0.12,
			clearProps: 'opacity,transform'
		});
	}

	var archiveHead = document.querySelector('.archive-head .wrap');
	if (archiveHead) {
		gsap.from(archiveHead.children, {
			y: 18,
			opacity: 0,
			duration: 0.65,
			stagger: 0.06,
			clearProps: 'opacity,transform'
		});
	}

	/* Section headers: rule draws in, label lifts. */
	gsap.utils.toArray('.section-head').forEach(function (head) {
		gsap.from(head, {
			y: 14,
			duration: 0.6,
			scrollTrigger: { trigger: head, start: 'top 92%', once: true }
		});
	});

	/* Cards: stagger in as their row enters view. */
	ScrollTrigger.batch('.card, .opinion-card', {
		start: 'top 94%',
		once: true,
		interval: 0.08,
		batchMax: 4,
		onEnter: function (batch) {
			gsap.from(batch, { y: 20, duration: 0.55, stagger: 0.05, overwrite: true });
		}
	});

	/* Long-form page sections reveal once as they enter the viewport. */
	var revealTargets = [
		'.about-intro',
		'.about-pillar',
		'.about-block',
		'.about-sections-section',
		'.about-publisher',
		'.policy-rail',
		'.policy-main',
		'.policy-end',
		'.contact-layout > *',
		'.advertise-grid > *',
		'.page__layout',
		'.search-results > *',
		'.error-404'
	].join(',');

	if (document.querySelector(revealTargets)) {
		ScrollTrigger.batch(revealTargets, {
			start: 'top 90%',
			once: true,
			interval: 0.09,
			batchMax: 4,
			onEnter: function (batch) {
				gsap.from(batch, {
					y: 24,
					opacity: 0,
					duration: 0.75,
					stagger: 0.08,
					overwrite: true,
					clearProps: 'opacity,transform'
				});
			}
		});
	}

	/* About statistics count up once, with the authored number as fallback. */
	gsap.utils.toArray('.about-hero__stats strong').forEach(function (number) {
		var finalText = number.textContent.trim();
		var finalValue = parseInt(finalText, 10);

		if (isNaN(finalValue)) {
			return;
		}

		var counter = { value: 0 };

		gsap.to(counter, {
			value: finalValue,
			duration: 1.15,
			delay: 0.35,
			ease: 'power2.out',
			onUpdate: function () {
				number.textContent = String(Math.round(counter.value)).padStart(finalText.length, '0');
			},
			onComplete: function () {
				number.textContent = finalText;
			}
		});
	});

	/* Keep the policy contents rail in step with the section being read. */
	var tocLinks = gsap.utils.toArray('.policy-toc a');

	function syncToc() {
		var active = null;

		tocLinks.forEach(function (link) {
			var id = link.getAttribute('href');
			var target = id && id.charAt(0) === '#' ? document.querySelector(id) : null;

			if (target && target.getBoundingClientRect().top <= 160) {
				active = link;
			}
		});

		tocLinks.forEach(function (link) {
			var isActive = link === active;
			link.classList.toggle('is-active', isActive);

			if (isActive) {
				link.setAttribute('aria-current', 'location');
			} else {
				link.removeAttribute('aria-current');
			}
		});
	}

	if (tocLinks.length) {
		syncToc();
		window.addEventListener('scroll', syncToc, { passive: true });
		window.addEventListener('resize', syncToc);
	}

	/* The Big Read image drifts slightly for depth. */
	var bigRead = document.querySelector('.bigread__media img');
	if (bigRead) {
		gsap.fromTo(bigRead, { yPercent: -3 }, {
			yPercent: 3,
			ease: 'none',
			scrollTrigger: {
				trigger: '.bigread',
				start: 'top bottom',
				end: 'bottom top',
				scrub: 0.6
			}
		});
	}

	/* Footer settles in. */
	var foot = document.querySelector('.site-foot__main');
	if (foot) {
		gsap.from(foot, {
			y: 18,
			duration: 0.6,
			scrollTrigger: { trigger: foot, start: 'top 96%', once: true }
		});
	}

	/* Nav gains a shadow once the page moves. */
	var nav = document.querySelector('[data-sticky-nav]');
	if (nav) {
		ScrollTrigger.create({
			start: 80,
			onEnter: function () { nav.classList.add('is-stuck'); },
			onLeaveBack: function () { nav.classList.remove('is-stuck'); }
		});
	}

	/* Refresh positions after images and fonts settle. */
	window.addEventListener('load', function () {
		ScrollTrigger.refresh();
		updateProgress();
	});
})();
