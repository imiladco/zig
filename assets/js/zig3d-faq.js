/**
 * آکاردئونِ «سوالات متداول» — تک‌بازشو، با انیمیشنِ ارتفاع.
 *
 * مکانیزم عیناً همان ‎zig3d-specs.js‎ است (رجوع کنید به توضیحاتِ آن‌جا
 * برایِ چراییِ الگو: ‎<details>‎ی بومی، انیمیت‌کردنِ خودِ ‎height‎ با Web
 * Animations API به‌جایِ ‎max-height‎ یا کلاسِ CSS). فایلِ جدا، نه
 * اشتراکِ کد بینِ دو ویجت، چون هر ویجتِ این افزونه JSِ مستقلِ خودش را
 * دارد — همین‌طور می‌شود بعداً رفتارِ یکی را بدونِ نگرانی از دیگری عوض
 * کرد.
 *
 * بدونِ این اسکریپت، هر ‎<details>‎ مستقل کار می‌کند: باز/بسته می‌شود،
 * بدونِ انیمیشن، و چند سوال هم‌زمان می‌توانند باز بمانند — یعنی خودِ
 * ویجت بدونِ جاوااسکریپت هم کاملاً کاربردی است.
 */
(function () {
	'use strict';

	// مرورگرِ بدونِ Web Animations API همان رفتارِ بومیِ بی‌انیمیشن را نگه می‌دارد
	if (!('animate' in document.createElement('div'))) {
		return;
	}

	var DURATION_OPEN = 230;
	var DURATION_CLOSE = 180;
	var EASING_OPEN = 'cubic-bezier(.22, 1, .36, 1)';
	var EASING_CLOSE = 'cubic-bezier(.4, 0, 1, 1)';
	var FADE_DELAY = 45;

	function Item(details, siblings) {
		this.el = details;
		this.siblings = siblings;
		this.summary = details.querySelector(':scope > .zig-faq__question');
		this.content = details.querySelector(':scope > .zig-faq__answer');
		this.animation = null;
		this.fade = null;
		this.isClosing = false;
		this.isExpanding = false;

		if (!this.summary) {
			return;
		}

		var self = this;
		this.summary.addEventListener('click', function (e) {
			e.preventDefault();
			self.onClick();
		});
	}

	Item.prototype.onClick = function () {
		this.el.style.overflow = 'hidden';

		if (this.isClosing || !this.el.open) {
			this.open();
		} else if (this.isExpanding || this.el.open) {
			this.shrink();
		}
	};

	Item.prototype.shrink = function () {
		this.isClosing = true;

		if (this.fade) {
			this.fade.cancel();
			this.fade = null;
		}

		var startHeight = this.el.offsetHeight + 'px';
		var endHeight = this.summary.offsetHeight + 'px';

		this.runAnimation(startHeight, endHeight, false, DURATION_CLOSE);
	};

	Item.prototype.open = function () {
		var self = this;

		this.el.style.height = this.el.offsetHeight + 'px';
		this.el.open = true;

		/*
		 * بستنِ خواهرها هم داخلِ همین rAF انجام می‌شود، نه بلافاصله —
		 * دقیقاً همان دلیلِ ‎zig3d-specs.js‎: هر دو انیمیشن (باز/بسته) باید
		 * دقیقاً هم‌زمان آغاز شوند.
		 */
		window.requestAnimationFrame(function () {
			self.siblings.forEach(function (other) {
				if (other !== self && other.el.open) {
					other.shrink();
				}
			});
			self.expand();
		});
	};

	Item.prototype.expand = function () {
		this.isExpanding = true;

		// سوالِ بدونِ پاسخ هم می‌شود باز کرد (فقط جعبه، بدونِ محتوایِ زیرش)
		var contentHeight = this.content ? this.content.offsetHeight : 0;
		var startHeight = this.el.offsetHeight + 'px';
		var endHeight = (this.summary.offsetHeight + contentHeight) + 'px';

		this.runAnimation(startHeight, endHeight, true, DURATION_OPEN);

		if (this.content) {
			this.content.style.opacity = '0';
			this.fade = this.content.animate(
				[{ opacity: 0 }, { opacity: 1 }],
				{ duration: Math.max(0, DURATION_OPEN - FADE_DELAY), delay: FADE_DELAY, easing: 'ease-out', fill: 'forwards' }
			);
		}
	};

	Item.prototype.runAnimation = function (startHeight, endHeight, opening, duration) {
		var self = this;

		if (this.animation) {
			this.animation.cancel();
		}

		this.animation = this.el.animate(
			{ height: [startHeight, endHeight] },
			{ duration: duration, easing: opening ? EASING_OPEN : EASING_CLOSE }
		);

		this.animation.onfinish = function () { self.onAnimationFinish(opening); };
		this.animation.oncancel = function () {
			if (opening) { self.isExpanding = false; } else { self.isClosing = false; }
		};
	};

	Item.prototype.onAnimationFinish = function (opening) {
		this.el.open = opening;
		this.animation = null;
		this.isClosing = false;
		this.isExpanding = false;
		this.el.style.height = '';
		this.el.style.overflow = '';
		// از این‌جا به بعد کلاسِ CSSِ خودِ ‎[open]‎ مسئولِ opacity است، نه استایلِ خطی
		if (this.content) {
			this.content.style.opacity = '';
		}
	};

	function setup(root) {
		if (root.__zigFaq) {
			return;
		}
		root.__zigFaq = true;

		var itemEls = root.querySelectorAll(':scope > details.zig-faq__item');
		if (!itemEls.length) {
			return;
		}

		var items = [];
		itemEls.forEach(function (el) {
			items.push(new Item(el, items));
		});
	}

	function initAll(scope) {
		(scope || document).querySelectorAll('.zig-faq').forEach(setup);
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-faq.default',
			function ($el) {
				initAll($el && $el[0] ? $el[0] : document);
			}
		);
	}

	if (document.readyState !== 'loading') {
		initAll(document);
	} else {
		document.addEventListener('DOMContentLoaded', function () {
			initAll(document);
		});
	}
})();
