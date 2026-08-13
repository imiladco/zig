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
 *
 * موشن این‌بار Style Control است (برخلافِ آکاردئونِ مشخصاتِ فنی که
 * زمان‌بندی‌اش کدنویسی‌شده و ثابت است): مدت/easing از رویِ متغیرهایِ
 * CSSِ همان نمونهٔ ویجت خوانده می‌شود — نه عددِ ثابت این‌جا — پس هر
 * نمونه‌ای از این ویجت رویِ صفحه می‌تواند زمان‌بندیِ خودش را داشته باشد.
 */
(function () {
	'use strict';

	// ثانیه‌های پیش‌فرض، فقط برایِ وقتی متغیرِ CSS به هر دلیلی خوانده نشد
	var FALLBACK_DURATION = 260;
	var FALLBACK_EASING = 'cubic-bezier(.22, 1, .36, 1)';
	var STAGGER_STEP = 28; // فاصلهٔ خیلی‌کمِ شروعِ هر بخشِ متن نسبت به قبلی

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
		this.anims = [];

		/*
		 * ‎data-zig-motion="off"‎ یعنی کنترلِ استایلِ «گذارِ نرمِ سوییچ»
		 * خاموش است — مستقل از ‎prefers-reduced-motion‎، که همیشه هرچه
		 * ادمین گذاشته را هم می‌پوشاند.
		 */
		this.motionEnabled = 'off' !== root.getAttribute('data-zig-motion');

		var styles = getComputedStyle(root);
		var durationRaw = parseFloat(styles.getPropertyValue('--zig-feature-motion-duration'));
		this.duration = isNaN(durationRaw) ? FALLBACK_DURATION : durationRaw;
		var easingRaw = (styles.getPropertyValue('--zig-feature-motion-easing') || '').trim();
		this.easing = easingRaw || FALLBACK_EASING;

		/*
		 * انیمیشنِ ورودِ Glow باید دقیقاً به همان شدتِ تنظیم‌شده در استایل
		 * برسد، نه به ‎opacity:1‎ِ ثابت — وگرنه لحظهٔ آخرِ گذار، Glow یک
		 * لحظه روشن‌تر از حالتِ آرامش می‌شود و بعد ناگهان کم‌نور می‌شود.
		 */
		var glowOpacityRaw = parseFloat(styles.getPropertyValue('--zig-feature-glow-opacity'));
		this.glowOpacity = isNaN(glowOpacityRaw) ? .55 : glowOpacityRaw / 100;

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

	/**
	 * گذارِ ورودِ پنلِ تازه‌فعال‌شده: رسانه، محتوا (با یک stagger خیلی‌کم
	 * بینِ meta/عنوان/توضیح)، و لکه‌هایِ Glow — همه با هم، همان
	 * مدت/easingِ خوانده‌شده از استایل. کنسل‌کردنِ همهٔ انیمیشن‌هایِ
	 * قبلی *قبل* از شروعِ دسته‌ی تازه یعنی کلیکِ سریع رویِ چند تب هیچ‌وقت
	 * انیمیشنِ نیمه‌تمامِ قبلی را رویِ عنصرِ اشتباه تمام نمی‌کند.
	 */
	Showcase.prototype.playEntrance = function (panel) {
		this.anims.forEach(function (anim) { anim.cancel(); });
		this.anims = [];

		if (!panel || !this.motionEnabled || !('animate' in panel) || reducedMotion()) {
			return;
		}

		var self = this;
		var duration = this.duration;
		var easing = this.easing;

		/*
		 * ‎releaseOnFinish‎ فقط برایِ Glow لازم است: حالتِ «آرام»ِ Glow صرفِ
		 * ‎opacity:1‎ نیست — یک متغیرِ CSS (‎--zig-feature-glow-opacity‎) و
		 * احتمالاً یک انیمیشنِ پالسِ بی‌پایان است. اگر انیمیشنِ WAAPI با
		 * ‎fill:'both'‎ رویِ حالتِ پایانی قفل بماند، آن متغیر و آن پالس
		 * هیچ‌وقت دوباره میدان‌دار نمی‌شوند. با ‎cancel()‎ کردنِ خودِ
		 * انیمیشن — نه فقط حذف از فهرستِ ردیابی — کنترل به CSSِ زیرین
		 * برمی‌گردد. رسانه/متن این مشکل را ندارند چون حالتِ آرامشان
		 * (opacity:۱، بدونِ transform) دقیقاً همان چیزی است که WAAPI رویش
		 * نگه می‌دارد.
		 */
		function run(el, keyframes, opts, releaseOnFinish) {
			if (!el) {
				return;
			}
			var anim = el.animate(keyframes, opts);
			self.anims.push(anim);
			anim.oncancel = function () {
				var at = self.anims.indexOf(anim);
				if (-1 !== at) { self.anims.splice(at, 1); }
			};
			anim.onfinish = function () {
				if (releaseOnFinish) { anim.cancel(); return; }
				var at = self.anims.indexOf(anim);
				if (-1 !== at) { self.anims.splice(at, 1); }
			};
		}

		var media = panel.querySelector(':scope > .zig-feature__media');
		var content = panel.querySelector(':scope > .zig-feature__content');
		var glowContent = panel.querySelector(':scope > .zig-feature__glow--content');
		var glowMedia = panel.querySelector(':scope > .zig-feature__glow--media');

		// رسانه: fade + یک لغزشِ خیلی‌ملایمِ افقی — «image fade/slide subtle»
		run(media,
			[{ opacity: 0, transform: 'translateX(6px)' }, { opacity: 1, transform: 'translateX(0)' }],
			{ duration: duration, easing: easing, fill: 'both' }
		);

		// محتوا: هر بخش (شماره/برچسب، عنوان، توضیح) با تأخیرِ خیلی‌کمِ نسبت‌به‌قبلی — stagger بسیار ملایم
		var textParts = [
			content && content.querySelector(':scope > .zig-feature__meta'),
			content && content.querySelector(':scope > .zig-feature__feature-title'),
			content && content.querySelector(':scope > .zig-feature__feature-desc'),
		].filter(Boolean);

		textParts.forEach(function (el, i) {
			run(el,
				[{ opacity: 0, transform: 'translateY(4px)' }, { opacity: 1, transform: 'translateY(0)' }],
				{ duration: duration, delay: i * STAGGER_STEP, easing: easing, fill: 'both' }
			);
		});

		// Glow: با محتوا/رسانه محو و بزرگ می‌شود — «glow morph/fade»، نه یک هالهٔ ثابت
		var glowOpacity = this.glowOpacity;
		run(glowContent,
			[{ opacity: 0, transform: 'scale(.85)' }, { opacity: glowOpacity, transform: 'scale(1)' }],
			{ duration: duration + 60, easing: 'ease-out', fill: 'both' },
			true
		);
		run(glowMedia,
			[{ opacity: 0, transform: 'scale(.85)' }, { opacity: glowOpacity, transform: 'scale(1)' }],
			{ duration: duration + 60, easing: 'ease-out', fill: 'both' },
			true
		);
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
