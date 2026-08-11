/*
 * گالری محصول زیگ.
 *
 * مثل آرشیو، این فایل هیچ چیزی *نمی‌سازد*. سرور همهٔ فریم‌ها را رندر کرده
 * و بندانگشتی‌ها ‎<a href="#frame">‎ واقعی‌اند؛ ظرفِ فریم‌ها هم یک ناحیهٔ
 * ‎scroll-snap‎ است. یعنی بدون این فایل، لمس و کشیدن و کلیک روی بندانگشتی
 * هر سه کار می‌کنند — فقط فلش و شمارنده نیستند (CSS تا رسیدن کلاس
 * ‎is-ready‎ پنهانشان می‌کند، تا هیچ‌وقت کنترلِ بی‌اثر دیده نشود).
 *
 * پس کارِ اینجا سه چیز است و بس:
 *
 *     ۱. ‎is-ready‎ را بگذارد تا فلش و شمارنده ظاهر شوند.
 *     ۲. «فریمِ جاری» را از روی *اسکرول* بخواند، نه از روی کلیک.
 *     ۳. آن یک عدد را به شمارنده، ‎aria-current‎ و وضعیت دکمه‌ها برساند.
 *
 * بند دوم مهم‌ترین تصمیم فایل است. وسوسه‌اش هست که هر کلیک ‎index‎ را جلو
 * ببرد و همان را مبنا بگیریم؛ ولی آن‌وقت دو منبعِ حقیقت داریم و کاربری که
 * با انگشت می‌کشد از هر دو خارج می‌شود: شمارنده روی ۲ می‌ماند و فلشِ بعدی
 * او را به جایی می‌برد که انتظارش را ندارد. با ‎IntersectionObserver‎،
 * *موقعیتِ واقعیِ اسکرول* تنها منبع است و کشیدن با انگشت و کلیک روی فلش
 * دقیقاً یک مسیر دارند.
 */
(function () {
	'use strict';

	function boot(root) {
		if (root.zigGallery) {
			return;
		}

		root.zigGallery = new Gallery(root);
	}

	/* ======================================================================
	 * نمونه
	 * =================================================================== */

	function Gallery(root) {
		this.root = root;
		this.frames = root.querySelectorAll('.zig-gallery__frame');
		this.scroller = root.querySelector('.zig-gallery__frames');
		this.thumbs = root.querySelectorAll('[data-zig-goto]');
		this.counter = root.querySelector('[data-zig-counter]');
		this.navs = root.querySelectorAll('[data-zig-step]');
		this.loop = '1' === root.getAttribute('data-zig-loop');
		this.index = 0;

		if (!this.scroller || this.frames.length < 2) {
			return;
		}

		/*
		 * ارقام از خودِ مارک‌آپ خوانده می‌شوند نه از یک تابعِ تبدیلِ
		 * دوباره در JS: سرور یک بار تصمیم گرفته فارسی باشد یا لاتین، و
		 * تکرارِ آن تصمیم اینجا یعنی روزی که کنترلش عوض شود، شمارنده تا
		 * اولین حرکتِ کاربر یک شکل و بعدش شکل دیگری دارد.
		 */
		this.digits = this.readDigits();

		this.bind();
		this.watch();

		root.classList.add('is-ready');
		this.sync();
	}

	/** ارقامِ شمارهٔ اول، به‌عنوان نمونهٔ الفبای اعداد */
	Gallery.prototype.readDigits = function () {
		if (!this.counter) {
			return null;
		}

		var one = this.counter.textContent.trim();

		// '۱' یعنی فارسی؛ هر چیز دیگری یعنی سرور لاتین چاپ کرده
		return '۱' === one ? '۰۱۲۳۴۵۶۷۸۹' : null;
	};

	Gallery.prototype.format = function (number) {
		var text = String(number);

		if (!this.digits) {
			return text;
		}

		var out = '';

		for (var i = 0; i < text.length; i++) {
			out += this.digits.charAt(Number(text.charAt(i)));
		}

		return out;
	};

	/* ======================================================================
	 * رویدادها
	 * =================================================================== */

	Gallery.prototype.bind = function () {
		var self = this;

		for (var i = 0; i < this.navs.length; i++) {
			this.navs[i].addEventListener('click', function (event) {
				event.preventDefault();
				self.step(Number(this.getAttribute('data-zig-step')) || 0);
			});
		}

		for (var j = 0; j < this.thumbs.length; j++) {
			this.thumbs[j].addEventListener('click', function (event) {
				/*
				 * جلوگیری از پرشِ صفحه.
				 *
				 * لنگر بدون JS دقیقاً همین کار را می‌کند، ولی مرورگر برای
				 * رساندنِ فریم به دید، *صفحه* را هم جابه‌جا می‌کند. با JS
				 * فقط ظرف را می‌بریم و صفحه سر جایش می‌ماند.
				 */
				event.preventDefault();
				self.go(Number(this.getAttribute('data-zig-goto')) || 0);
			});
		}
	};

	/**
	 * تماشای اسکرول.
	 *
	 * ‎threshold‎ روی ‎0.6‎ است نه ‎0.5‎: با نصف، در میانهٔ یک کشیدنِ آرام
	 * هر دو فریم هم‌زمان از آستانه رد می‌شوند و شمارنده بین دو عدد
	 * می‌لرزد. با ۶۰٪ همیشه حداکثر یکی برنده است.
	 */
	Gallery.prototype.watch = function () {
		var self = this;

		if (!window.IntersectionObserver) {
			return;
		}

		var observer = new window.IntersectionObserver(
			function (entries) {
				for (var i = 0; i < entries.length; i++) {
					if (entries[i].isIntersecting) {
						self.index = Number(entries[i].target.getAttribute('data-zig-index')) || 0;
						self.sync();
					}
				}
			},
			{ root: this.scroller, threshold: 0.6 }
		);

		for (var i = 0; i < this.frames.length; i++) {
			observer.observe(this.frames[i]);
		}
	};

	/* ======================================================================
	 * حرکت
	 * =================================================================== */

	Gallery.prototype.step = function (delta) {
		var next = this.index + delta;
		var last = this.frames.length - 1;

		if (next < 0) {
			next = this.loop ? last : 0;
		} else if (next > last) {
			next = this.loop ? 0 : last;
		}

		this.go(next);
	};

	/**
	 * بردنِ ظرف روی یک فریم.
	 *
	 * محاسبه از روی ‎getBoundingClientRect‎ است و نه ‎offsetLeft‎ یا
	 * ‎scrollLeft‎، و این برای چیدمانِ راست‌به‌چپ حیاتی است: معنای
	 * ‎scrollLeft‎ در RTL بین مرورگرها یکسان نبوده (صفر در یک سمت، منفی در
	 * دیگری) و هر فرمولی که رویش بنا شود، در نیمی از مرورگرها برعکس حرکت
	 * می‌کند. اختلافِ دو مستطیل همیشه فیزیکی و همیشه درست است.
	 */
	Gallery.prototype.go = function (index) {
		var frame = this.frames[index];

		if (!frame) {
			return;
		}

		var delta = frame.getBoundingClientRect().left - this.scroller.getBoundingClientRect().left;

		this.scroller.scrollBy({
			left: delta,
			behavior: this.motion() ? 'smooth' : 'auto'
		});

		/*
		 * وضعیت اینجا عوض *نمی‌شود*. ناظرِ اسکرول آن را می‌گذارد، حتی وقتی
		 * حرکت از همین‌جا شروع شده — همان قاعدهٔ تک‌منبع که بالای فایل
		 * توضیح داده شد. تنها استثنا نبودنِ ‎IntersectionObserver‎ است.
		 */
		if (!window.IntersectionObserver) {
			this.index = index;
			this.sync();
		}
	};

	/** آیا کاربر حرکت می‌خواهد */
	Gallery.prototype.motion = function () {
		return !(
			window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
		);
	};

	/* ======================================================================
	 * همگام‌سازی
	 * =================================================================== */

	Gallery.prototype.sync = function () {
		this.syncCounter();
		this.syncThumbs();
		this.syncNav();
	};

	Gallery.prototype.syncCounter = function () {
		if (this.counter) {
			this.counter.textContent = this.format(this.index + 1);
		}
	};

	Gallery.prototype.syncThumbs = function () {
		for (var i = 0; i < this.thumbs.length; i++) {
			var on = Number(this.thumbs[i].getAttribute('data-zig-goto')) === this.index;

			/*
			 * حذفِ کامل، نه ‎aria-current="false"‎: صفحه‌خوان‌ها مقدارِ
			 * ‎false‎ را هم به‌عنوان یک حالت اعلام می‌کنند و کاربر برای هر
			 * تصویر یک بار «فعلی: خیر» می‌شنود.
			 */
			if (on) {
				this.thumbs[i].setAttribute('aria-current', 'true');
			} else {
				this.thumbs[i].removeAttribute('aria-current');
			}
		}
	};

	Gallery.prototype.syncNav = function () {
		if (this.loop) {
			return;
		}

		var last = this.frames.length - 1;

		for (var i = 0; i < this.navs.length; i++) {
			var step = Number(this.navs[i].getAttribute('data-zig-step')) || 0;
			var dead = (step < 0 && 0 === this.index) || (step > 0 && this.index === last);

			this.navs[i].disabled = dead;
		}
	};

	/* ======================================================================
	 * راه‌اندازی
	 * =================================================================== */

	function scan(scope) {
		var roots = (scope || document).querySelectorAll('[data-zig-gallery]');

		for (var i = 0; i < roots.length; i++) {
			boot(roots[i]);
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', function () {
			scan();
		});
	} else {
		scan();
	}

	/*
	 * ادیتور المنتور ویجت را بدون بارگذاری دوبارهٔ صفحه جایگزین می‌کند، پس
	 * بدون این، طراح بعد از هر تغییر تنظیمات یک گالری مرده می‌بیند.
	 */
	window.addEventListener('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
			return;
		}

		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-product-gallery.default',
			function ($scope) {
				scan($scope && $scope[0] ? $scope[0] : null);
			}
		);
	});
})();
