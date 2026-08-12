/**
 * آکاردئونِ «مشخصاتِ فنی محصول» — تک‌بازشو، با انیمیشنِ ارتفاع.
 *
 * بدونِ این اسکریپت، هر ‎<details>‎ مستقل کار می‌کند: باز/بسته می‌شود،
 * بدونِ انیمیشن، و چند گروه هم‌زمان می‌توانند باز بمانند — یعنی خودِ
 * ویجت بدونِ جاوااسکریپت هم کاملاً کاربردی است. این فایل فقط دو رفتار
 * رویش سوار می‌کند: (۱) باز شدنِ یکی، بقیه را می‌بندد، (۲) باز/بسته‌شدن
 * به‌جایِ پرش، نرم است.
 *
 * چرا خودِ ارتفاعِ ‎<details>‎ را انیمیت می‌کنیم، نه max-height یا کلاسِ
 * CSS: ‎<details>‎ استاندارد بینِ «باز» و «بسته» فقط ‎display: block/none‎
 * سوییچ می‌کند — چیزی برای گذار (transition) وجود ندارد. الگوی زیر
 * (اندازه‌گیریِ ارتفاعِ شروع/پایان و انیمیت‌کردنِ خودِ ‎height‎ با Web
 * Animations API) همان روشی است که خودِ مشخصاتِ HTML برایِ این مسئله
 * پیشنهاد می‌دهد؛ بدونِ آن، تنها راه یک کتابخانهٔ جدا یا اندازه‌گیریِ
 * دستیِ ‎scrollHeight‎ با کلاس بود که با محتوایِ پویا (فونتِ لود دیرتر،
 * تغییرِ اندازهٔ صفحه) هماهنگ نمی‌ماند.
 */
(function () {
	'use strict';

	// مرورگرِ بدونِ Web Animations API همان رفتارِ بومیِ بی‌انیمیشن را نگه می‌دارد
	if (!('animate' in document.createElement('div'))) {
		return;
	}

	var DURATION = 260;
	var EASING = 'cubic-bezier(0.4, 0, 0.2, 1)';

	function Group(details, siblings) {
		this.el = details;
		this.siblings = siblings;
		this.summary = details.querySelector(':scope > .zig-specs__group-title');
		this.content = details.querySelector(':scope > .zig-specs__list');
		this.animation = null;
		this.isClosing = false;
		this.isExpanding = false;

		if (!this.summary || !this.content) {
			return;
		}

		var self = this;
		this.summary.addEventListener('click', function (e) {
			e.preventDefault();
			self.onClick();
		});
	}

	Group.prototype.onClick = function () {
		this.el.style.overflow = 'hidden';

		if (this.isClosing || !this.el.open) {
			this.open();
		} else if (this.isExpanding || this.el.open) {
			this.shrink();
		}
	};

	Group.prototype.shrink = function () {
		this.isClosing = true;

		var startHeight = this.el.offsetHeight + 'px';
		var endHeight = this.summary.offsetHeight + 'px';

		this.runAnimation(startHeight, endHeight, false);
	};

	Group.prototype.open = function () {
		var self = this;

		// تک‌بازشو: قبل از بازکردنِ این یکی، هرکدام از خواهر‌ها که باز است بسته می‌شود
		this.siblings.forEach(function (other) {
			if (other !== self && other.el.open) {
				other.shrink();
			}
		});

		this.el.style.height = this.el.offsetHeight + 'px';
		this.el.open = true;

		window.requestAnimationFrame(function () {
			self.expand();
		});
	};

	Group.prototype.expand = function () {
		this.isExpanding = true;

		var startHeight = this.el.offsetHeight + 'px';
		var endHeight = (this.summary.offsetHeight + this.content.offsetHeight) + 'px';

		this.runAnimation(startHeight, endHeight, true);
	};

	Group.prototype.runAnimation = function (startHeight, endHeight, opening) {
		var self = this;

		if (this.animation) {
			this.animation.cancel();
		}

		this.animation = this.el.animate(
			{ height: [startHeight, endHeight] },
			{ duration: DURATION, easing: EASING }
		);

		this.animation.onfinish = function () { self.onAnimationFinish(opening); };
		this.animation.oncancel = function () {
			if (opening) { self.isExpanding = false; } else { self.isClosing = false; }
		};
	};

	Group.prototype.onAnimationFinish = function (opening) {
		this.el.open = opening;
		this.animation = null;
		this.isClosing = false;
		this.isExpanding = false;
		this.el.style.height = '';
		this.el.style.overflow = '';
	};

	function setup(root) {
		if (root.__zigSpecs) {
			return;
		}
		root.__zigSpecs = true;

		/*
		 * فقط ‎<details>‎ی «آکاردئونی» انیمیت می‌شود. حالتِ «تخت»
		 * (‎<div>‎، همه همیشه باز) اصلاً toggle ندارد — چیزی برایِ
		 * سوارکردنِ این رفتار رویش نیست.
		 */
		var detailsEls = root.querySelectorAll(':scope > details.zig-specs__group');
		if (!detailsEls.length) {
			return;
		}

		var groups = [];
		detailsEls.forEach(function (el) {
			groups.push(new Group(el, groups));
		});
	}

	function initAll(scope) {
		(scope || document).querySelectorAll('.zig-specs').forEach(setup);
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-product-specs.default',
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
