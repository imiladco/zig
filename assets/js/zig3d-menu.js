/**
 * منویِ اصلی — فقط نسخهٔ موبایل.
 *
 * نوارِ دسکتاپ عمداً هیچ کدی اینجا ندارد: باز شدنش با ‎:hover‎ و
 * ‎:focus-within‎ در CSS است و اگر این فایل اصلاً بار نشود، دسکتاپ کامل
 * کار می‌کند. آنچه اینجاست فقط کشویِ موبایل و رفت‌وبرگشتِ لایه‌هایش است.
 *
 * سه قاعده که از ویجتِ سرچ آمده‌اند و اینجا هم برقرارند:
 *
 *   ۱. عددِ برک‌پوینت تکرار نمی‌شود. اسکریپت ‎display‎ی محاسبه‌شدهٔ دکمهٔ
 *      بازکننده را می‌خوانَد تا بفهمد در حالتِ کشویی هستیم یا نه. عددِ
 *      تکراری دیر یا زود از CSS واگرا می‌شود.
 *
 *   ۲. مدتِ گذار از خودِ CSS خوانده می‌شود، نه از یک ثابتِ اینجا —
 *      چون تنظیم‌شدنی است و ‎prefers-reduced-motion‎ هم صفرش می‌کند.
 *
 *   ۳. هیچ HTMLی از اینجا ساخته نمی‌شود. هر دو آیکونِ دکمهٔ بستن/بازگشت
 *      همان اول در DOM هستند و فقط کلاس عوض می‌شود.
 */
(function () {
	'use strict';

	var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

	function Menu(root) {
		this.root = root;
		this.trigger = root.querySelector('.zig-menu__trigger');
		this.sheet = root.querySelector('.zig-menu__sheet');
		this.backdrop = root.querySelector('.zig-menu__backdrop');
		this.back = root.querySelector('.zig-menu__sheet-back');
		this.backLabel = root.querySelector('.zig-menu__sheet-back-label');

		if (!this.trigger || !this.sheet || !this.back) {
			return;
		}

		this.levels = {};

		var nodes = this.sheet.querySelectorAll('.zig-menu__level');
		var i;

		for (i = 0; i < nodes.length; i++) {
			this.levels[nodes[i].getAttribute('data-level')] = nodes[i];
		}

		/* پشتهٔ لایه‌ها؛ همیشه دستِ‌کم ریشه در آن است */
		this.stack = ['root'];
		this.hideTimer = 0;

		this.bind();
	}

	/** آیا اصلاً در حالتِ کشویی هستیم؟ پاسخ را CSS می‌دهد، نه یک عدد اینجا */
	Menu.prototype.isSheet = function () {
		return 'none' !== window.getComputedStyle(this.trigger).display;
	};

	/** مدتِ گذارِ کشو، بر حسبِ میلی‌ثانیه */
	Menu.prototype.transitionMs = function () {
		var value = window.getComputedStyle(this.sheet).transitionDuration || '0s';
		var first = value.split(',')[0].trim();
		var ms = parseFloat(first) || 0;

		return -1 === first.indexOf('ms') ? ms * 1000 : ms;
	};

	Menu.prototype.bind = function () {
		var self = this;

		this.trigger.addEventListener('click', function () {
			self.open();
		});

		this.back.addEventListener('click', function () {
			/*
			 * یک دکمه، دو کار: تا وقتی تو رفته‌ایم یک پله برمی‌گردد و در
			 * ریشه می‌بندد. همان رفتاری که دکمهٔ بازگشتِ گوشی دارد.
			 */
			if (self.stack.length > 1) {
				self.pop();
				return;
			}

			self.close();
		});

		if (this.backdrop) {
			this.backdrop.addEventListener('click', function () {
				self.close();
			});
		}

		this.sheet.addEventListener('click', function (event) {
			var button = event.target.closest ? event.target.closest('[data-open-level]') : null;

			if (!button || !self.sheet.contains(button)) {
				return;
			}

			event.preventDefault();
			self.push(button.getAttribute('data-open-level'), button);
		});

		document.addEventListener('keydown', function (event) {
			if ('Escape' !== event.key || !self.root.classList.contains('is-sheet-open')) {
				return;
			}

			self.close();
		});

		this.sheet.addEventListener('keydown', function (event) {
			if ('Tab' === event.key) {
				self.trapTab(event);
			}
		});

		/*
		 * چرخاندنِ گوشی یا تغییرِ اندازهٔ پنجره می‌تواند ما را از حالتِ
		 * کشویی بیرون ببرد در حالی که کشو باز است — آن‌وقت اسکرولِ صفحه
		 * قفل می‌مانْد و هیچ دکمه‌ای هم برایِ بازکردنش دیده نمی‌شد.
		 */
		window.addEventListener('resize', function () {
			if (self.root.classList.contains('is-sheet-open') && !self.isSheet()) {
				self.close(true);
			}
		});
	};

	Menu.prototype.open = function () {
		window.clearTimeout(this.hideTimer);

		this.root.classList.add('is-sheet-open');
		this.trigger.setAttribute('aria-expanded', 'true');
		this.lockScroll(true);

		/*
		 * یک reflow اجباری بینِ نمایان‌شدن و افزودنِ کلاسِ حالت: بدونش
		 * مرورگر هر دو تغییر را یک‌جا حساب می‌کند و گذار اصلاً اجرا
		 * نمی‌شود.
		 */
		void this.sheet.offsetWidth;

		this.sheet.classList.add('is-open');

		if (this.backdrop) {
			this.backdrop.classList.add('is-open');
		}

		this.focusFirst();
	};

	Menu.prototype.close = function (immediate) {
		var self = this;

		this.sheet.classList.remove('is-open');

		if (this.backdrop) {
			this.backdrop.classList.remove('is-open');
		}

		this.trigger.setAttribute('aria-expanded', 'false');
		this.lockScroll(false);

		window.clearTimeout(this.hideTimer);

		this.hideTimer = window.setTimeout(function () {
			self.root.classList.remove('is-sheet-open');
			self.reset();
		}, immediate ? 0 : this.transitionMs());

		/*
		 * فوکوس به دکمه‌ای برمی‌گردد که کشو را باز کرده بود. بدونِ این،
		 * فوکوس رویِ عنصرِ ناپیدا می‌مانْد و کاربرِ کیبورد گم می‌شد.
		 */
		if (this.isSheet()) {
			this.trigger.focus();
		}
	};

	/** رفتن به لایهٔ فرزند */
	Menu.prototype.push = function (key, button) {
		var next = this.levels[key];

		if (!next) {
			return;
		}

		this.current().hidden = true;
		next.hidden = false;

		this.stack.push(key);
		button.setAttribute('aria-expanded', 'true');

		this.sheet.classList.add('is-nested');
		this.syncBackLabel();
		this.focusFirst();
	};

	/** یک پله برگشت */
	Menu.prototype.pop = function () {
		if (this.stack.length < 2) {
			return;
		}

		var leaving = this.stack.pop();
		var opener = this.sheet.querySelector('[data-open-level="' + leaving + '"]');

		this.levels[leaving].hidden = true;
		this.current().hidden = false;

		if (opener) {
			opener.setAttribute('aria-expanded', 'false');
			opener.focus();
		}

		if (1 === this.stack.length) {
			this.sheet.classList.remove('is-nested');
		}

		this.syncBackLabel();
	};

	/** برگرداندنِ کشو به لایهٔ ریشه، بعد از بسته‌شدن */
	Menu.prototype.reset = function () {
		var key;

		for (key in this.levels) {
			if (Object.prototype.hasOwnProperty.call(this.levels, key)) {
				this.levels[key].hidden = 'root' !== key;
			}
		}

		var open = this.sheet.querySelectorAll('[data-open-level][aria-expanded="true"]');
		var i;

		for (i = 0; i < open.length; i++) {
			open[i].setAttribute('aria-expanded', 'false');
		}

		this.stack = ['root'];
		this.sheet.classList.remove('is-nested');
		this.syncBackLabel();
	};

	Menu.prototype.current = function () {
		return this.levels[this.stack[this.stack.length - 1]];
	};

	/*
	 * متنِ دکمه از خودِ HTML می‌آید نه از رشته‌ای داخلِ این فایل: هم
	 * ترجمه‌پذیر می‌ماند هم از تبِ محتوایِ المنتور قابلِ تغییر.
	 */
	Menu.prototype.syncBackLabel = function () {
		if (!this.backLabel) {
			return;
		}

		var nested = this.stack.length > 1;
		var label = this.back.getAttribute(nested ? 'data-back-label' : 'data-close-label');

		if (label) {
			this.backLabel.textContent = label;
		}
	};

	Menu.prototype.focusFirst = function () {
		var level = this.current();
		var target = level ? level.querySelector(FOCUSABLE) : null;

		if (target) {
			target.focus();
		}
	};

	/**
	 * نگه‌داشتنِ فوکوس داخلِ کشو.
	 *
	 * لایه‌هایِ پنهان با ‎hidden‎ خودشان از ترتیبِ تب بیرون‌اند، پس
	 * فهرستِ زیر همیشه فقط عناصرِ لایهٔ دیده‌شده را دارد.
	 */
	Menu.prototype.trapTab = function (event) {
		var items = [];
		var all = this.sheet.querySelectorAll(FOCUSABLE);
		var i;

		for (i = 0; i < all.length; i++) {
			if (all[i].offsetParent || all[i] === document.activeElement) {
				items.push(all[i]);
			}
		}

		if (0 === items.length) {
			return;
		}

		var first = items[0];
		var last = items[items.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
			return;
		}

		if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	};

	/*
	 * قفلِ اسکرول با کلاسِ *خودِ* منو، همان الگویِ سرچ.
	 *
	 * کلاسِ جدا لازم است نه مشترک: اگر هر دو از یک کلاس استفاده کنند،
	 * بستنِ یکی قفلِ آن یکی را هم برمی‌دارد. با دو کلاس، هر کدام فقط
	 * مالِ خودش را پاک می‌کند و تا وقتی یکی باز است صفحه قفل می‌ماند.
	 */
	Menu.prototype.lockScroll = function (lock) {
		var root = document.documentElement;

		if (!root) {
			return;
		}

		if (lock && this.isSheet()) {
			root.classList.add('zig-menu-sheet-open');

			return;
		}

		root.classList.remove('zig-menu-sheet-open');
	};

	function init() {
		var roots = document.querySelectorAll('[data-zig-menu]');
		var i;

		for (i = 0; i < roots.length; i++) {
			if (!roots[i].hasAttribute('data-zig-menu-ready')) {
				roots[i].setAttribute('data-zig-menu-ready', '');
				new Menu(roots[i]);
			}
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	/* المنتور بعد از ویرایش، ویجت را دوباره می‌سازد */
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/zig3d-menu.default', init);
		}
	});
})();
