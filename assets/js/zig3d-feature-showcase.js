/**
 * تب‌هایِ «نمایشِ قابلیت‌های محصول».
 *
 * بدونِ این اسکریپت، همهٔ پنل‌ها در HTML هستند — فقط پنل‌هایِ غیرِفعال
 * ‎hidden‎اند و راهی برایِ بازکردنشان نیست، پس دستِ‌کم قابلیتِ اول کامل و
 * قابل‌خواندن می‌ماند. فایل فقط این‌ها را رویش سوار می‌کند: سوییچِ
 * فعال/غیرفعال، کیبورد (الگویِ واقعیِ ARIA Tabs)، ناوبریِ سرریز، و
 * انیمیشنِ ورود.
 *
 * چرا فعال‌سازیِ دستی (Manual Activation): پیکان‌هایِ چپ/راست فقط فوکوس
 * را بینِ تب‌ها جابه‌جا می‌کنند؛ Enter/Space همان تبِ فوکوس‌شده را انتخاب
 * می‌کند. با Roving Tabindex فقط یک تب — همانِ فعال — در توالیِ Tab است؛
 * تبی که با کلید جابه‌جا شده هنوز «انتخاب» نیست تا وقتی کاربر با
 * Enter/Space تأییدش کند.
 */
(function () {
	'use strict';

	var DURATION = 210;
	var EASING = 'cubic-bezier(.22, 1, .36, 1)';

	function reducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function Showcase(root) {
		this.root = root;
		this.nav = root.querySelector(':scope > .zig-feature__nav');
		this.tablist = this.nav ? this.nav.querySelector(':scope > .zig-feature__tablist') : null;
		this.tabs = this.tablist ? Array.prototype.slice.call(this.tablist.querySelectorAll(':scope > .zig-feature__tab')) : [];
		this.panelsWrap = root.querySelector(':scope > .zig-feature__panels');
		this.panels = this.panelsWrap ? Array.prototype.slice.call(this.panelsWrap.querySelectorAll(':scope > .zig-feature__panel')) : [];
		this.prevBtn = this.nav ? this.nav.querySelector('[data-zig-feature-prev]') : null;
		this.nextBtn = this.nav ? this.nav.querySelector('[data-zig-feature-next]') : null;
		this.currentAnim = null;

		// حالتِ فعالِ اولیه از همان چیزی خوانده می‌شود که PHP رندر کرده —
		// نه همیشه صفر، چون کنترلِ «قابلیتِ فعالِ اول» می‌تواند آن را عوض کرده باشد
		this.activeIndex = 0;
		for (var i = 0; i < this.tabs.length; i++) {
			if ('true' === this.tabs[i].getAttribute('aria-selected')) {
				this.activeIndex = i;
				break;
			}
		}

		if (!this.tabs.length) {
			return;
		}

		this.bind();
		this.checkOverflow();

		var self = this;
		if (window.ResizeObserver) {
			this.ro = new ResizeObserver(function () { self.checkOverflow(); });
			this.ro.observe(this.tablist);
		} else {
			window.addEventListener('resize', function () { self.checkOverflow(); });
		}
	}

	Showcase.prototype.bind = function () {
		var self = this;

		// یک listener رویِ خودِ tablist برایِ همهٔ تب‌ها — نه یکی به‌ازایِ هرکدام
		this.tablist.addEventListener('click', function (e) {
			var tab = e.target.closest('.zig-feature__tab');
			if (tab) {
				self.activate(tab);
			}
		});

		this.tablist.addEventListener('keydown', function (e) {
			self.onKeydown(e);
		});

		if (this.prevBtn) {
			this.prevBtn.addEventListener('click', function () { self.scrollByViewport(-1); });
		}
		if (this.nextBtn) {
			this.nextBtn.addEventListener('click', function () { self.scrollByViewport(1); });
		}
	};

	Showcase.prototype.onKeydown = function (e) {
		var currentIndex = this.tabs.indexOf(document.activeElement);

		if (-1 === currentIndex) {
			return;
		}

		if ('Enter' === e.key || ' ' === e.key || 'Spacebar' === e.key) {
			e.preventDefault();
			this.activate(this.tabs[currentIndex]);
			return;
		}

		// در آرایشِ راست‌به‌چپ، «چپ» یعنی به‌سمتِ تبِ بعدی (index بزرگ‌تر)، «راست» یعنی قبلی
		var isRTL = 'rtl' === getComputedStyle(this.root).direction;
		var nextKey = isRTL ? 'ArrowLeft' : 'ArrowRight';
		var prevKey = isRTL ? 'ArrowRight' : 'ArrowLeft';
		var targetIndex = null;

		if (e.key === nextKey) {
			targetIndex = (currentIndex + 1) % this.tabs.length;
		} else if (e.key === prevKey) {
			targetIndex = (currentIndex - 1 + this.tabs.length) % this.tabs.length;
		} else if ('Home' === e.key) {
			targetIndex = 0;
		} else if ('End' === e.key) {
			targetIndex = this.tabs.length - 1;
		} else {
			return;
		}

		e.preventDefault();
		this.focusTabAt(targetIndex);
	};

	/** فقط فوکوس و Roving Tabindex را جابه‌جا می‌کند — انتخاب را عوض نمی‌کند */
	Showcase.prototype.focusTabAt = function (index) {
		this.tabs.forEach(function (tab, i) {
			tab.tabIndex = (i === index) ? 0 : -1;
		});
		this.tabs[index].focus();
		this.tabs[index].scrollIntoView({ inline: 'nearest', block: 'nearest' });
	};

	Showcase.prototype.activate = function (tab) {
		var index = this.tabs.indexOf(tab);

		if (-1 === index) {
			return;
		}

		if (index === this.activeIndex) {
			this.focusTabAt(index);
			return;
		}

		this.activeIndex = index;

		/*
		 * همگام‌سازیِ DOM کاملاً همزمان است — مستقل از این‌که انیمیشنِ
		 * قبلی هنوز در حالِ اجراست یا نه. یعنی رویِ کلیکِ سریع رویِ چند
		 * تب، وضعیتِ نهایی همیشه همان *آخرین* کلیک است؛ انیمیشن هیچ‌وقت
		 * نمی‌تواند این وضعیت را برگرداند، چون فقط ظاهرِ گذار را می‌سازد،
		 * نه منبعِ حقیقتِ کدام تب فعال است.
		 */
		this.tabs.forEach(function (t, i) {
			var isActive = (i === index);
			t.classList.toggle('is-active', isActive);
			t.setAttribute('aria-selected', isActive ? 'true' : 'false');
			t.tabIndex = isActive ? 0 : -1;
		});

		this.panels.forEach(function (panel, i) {
			panel.hidden = (i !== index);
		});

		this.focusTabAt(index);
		this.playEntrance(this.panels[index]);
	};

	Showcase.prototype.playEntrance = function (panel) {
		if (this.currentAnim) {
			this.currentAnim.cancel();
			this.currentAnim = null;
		}

		if (!panel || !('animate' in panel) || reducedMotion()) {
			return;
		}

		var self = this;
		this.currentAnim = panel.animate(
			[
				{ opacity: 0, transform: 'translateY(3px)' },
				{ opacity: 1, transform: 'translateY(0)' },
			],
			{ duration: DURATION, easing: EASING, fill: 'both' }
		);
		this.currentAnim.onfinish = function () { self.currentAnim = null; };
		this.currentAnim.oncancel = function () { self.currentAnim = null; };
	};

	/** ناوبریِ سرریز — فقط اسکرولِ ردیفِ تب‌ها، بدونِ تغییرِ انتخاب */
	Showcase.prototype.scrollByViewport = function (dir) {
		if (!this.tablist) {
			return;
		}

		var amount = Math.max(160, this.tablist.clientWidth * .6);
		var isRTL = 'rtl' === getComputedStyle(this.tablist).direction;
		var signed = (isRTL ? -1 : 1) * dir * amount;

		this.tablist.scrollBy({ left: signed, behavior: reducedMotion() ? 'auto' : 'smooth' });
	};

	Showcase.prototype.checkOverflow = function () {
		if (!this.nav || !this.tablist) {
			return;
		}

		var isOverflowing = this.tablist.scrollWidth > this.tablist.clientWidth + 1;
		this.nav.classList.toggle('is-overflowing', isOverflowing);
	};

	function setup(root) {
		if (root.__zigFeature) {
			return;
		}
		root.__zigFeature = true;

		new Showcase(root);
	}

	function initAll(scope) {
		(scope || document).querySelectorAll('.zig-feature').forEach(setup);
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-feature-showcase.default',
			function ($el) {
				initAll($el && $el[0] ? $el[0] : document);
			}
		);
	}

	if ('loading' !== document.readyState) {
		initAll(document);
	} else {
		document.addEventListener('DOMContentLoaded', function () {
			initAll(document);
		});
	}
})();
