/**
 * کنترلر مشترک مودال‌های زیگ.
 *
 * پورتِ مستقیمِ ‎window.AlmasaraModal‎ از افزونهٔ almasara-elementor-widgets
 * (‎assets/js/modal.js‎ آنجا) — همان منطق، فقط با نامِ سراسریِ
 * ‎window.Zig3dModal‎ و کلاسِ قفلِ اسکرولِ ‎zig-gallery-noscroll‎ به‌جای
 * ‎amw-pg-noscroll‎.
 *
 * حبسِ فوکوس، ‎inert‎کردنِ پس‌زمینه، بستن با Esc، و برگرداندنِ فوکوس به
 * عنصرِ بازکننده — طبقِ WCAG، یک‌جا، تا هر مودالِ تازه‌ای که این افزونه
 * بعداً اضافه کند دوباره این کار را نسازد.
 */
(function () {
	'use strict';

	var FOCUSABLE = [
		'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
		'select:not([disabled])', 'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])'
	].join(',');

	// پشتهٔ مودال‌های باز — فقط بالاترین به Escape و Tab پاسخ می‌دهد
	var stack = [];

	function focusable(modal) {
		return Array.prototype.filter.call(
			modal.querySelectorAll(FOCUSABLE),
			function (el) {
				// عناصر پنهان نباید فوکوس بگیرند
				return el.offsetWidth > 0 || el.offsetHeight > 0 || el === document.activeElement;
			}
		);
	}

	function top() {
		return stack.length ? stack[stack.length - 1] : null;
	}

	/**
	 * بقیهٔ صفحه از دسترس صفحه‌خوان و Tab خارج می‌شود.
	 *
	 * در تمام زنجیرهٔ اجداد بالا می‌رویم و در هر سطح، هم‌نیاهای مودال را
	 * خنثی می‌کنیم — نه فقط فرزندان مستقیم body. چون مودال معمولاً داخل
	 * ساختار المنتور رندر می‌شود، خنثی‌کردنِ فقط سطح body یعنی باقی محتوای
	 * همان کانتینر برای صفحه‌خوان باز می‌ماند: حبس فوکوس جلوی Tab را
	 * می‌گیرد ولی درخت دسترس‌پذیری کامل محدود نمی‌شود.
	 */
	function setBackgroundInert(modal, on) {
		var node = modal;

		while (node && node.parentNode && node.parentNode !== document) {
			var parent = node.parentNode;
			var self = node;

			Array.prototype.forEach.call(parent.children || [], function (sibling) {
				if (sibling === self) {
					return;
				}

				if (on) {
					// مقدار قبلی نگه داشته می‌شود تا موقع بستن، چیزی که خودِ
					// صفحه از قبل مخفی کرده بود دوباره آشکار نشود
					if (sibling.hasAttribute('aria-hidden')) {
						sibling.setAttribute('data-zig-had-hidden', sibling.getAttribute('aria-hidden'));
					}
					sibling.setAttribute('aria-hidden', 'true');
					sibling.inert = true;
				} else {
					var had = sibling.getAttribute('data-zig-had-hidden');
					if (null !== had) {
						sibling.setAttribute('aria-hidden', had);
						sibling.removeAttribute('data-zig-had-hidden');
					} else {
						sibling.removeAttribute('aria-hidden');
					}
					sibling.inert = false;
				}
			});

			node = parent;
		}
	}

	function onKeydown(e) {
		var entry = top();
		if (!entry) {
			return;
		}

		if (e.key === 'Escape') {
			e.stopPropagation();
			close(entry.modal);
			return;
		}

		if (e.key !== 'Tab') {
			return;
		}

		// حبس فوکوس: از آخرین عنصر به اولی و برعکس
		var items = focusable(entry.modal);
		if (!items.length) {
			e.preventDefault();
			return;
		}

		var first = items[0];
		var last = items[items.length - 1];
		var active = document.activeElement;

		if (e.shiftKey && (active === first || !entry.modal.contains(active))) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && active === last) {
			e.preventDefault();
			first.focus();
		}
	}

	/**
	 * @param {Element} modal
	 * @param {Object}  options  initialFocus: Element، onClose: Function
	 */
	function open(modal, options) {
		if (!modal || stack.some(function (e) { return e.modal === modal; })) {
			return;
		}

		options = options || {};

		var entry = {
			modal: modal,
			// عنصری که مودال را باز کرد، تا بعد از بستن فوکوس به آن برگردد
			returnTo: document.activeElement,
			onClose: options.onClose
		};

		stack.push(entry);

		if (stack.length === 1) {
			document.addEventListener('keydown', onKeydown, true);
		}

		modal.classList.add('is-open');
		modal.removeAttribute('aria-hidden');
		document.body.classList.add('zig-gallery-noscroll');
		setBackgroundInert(modal, true);

		var target = options.initialFocus || focusable(modal)[0] || modal;
		if (target && target.focus) {
			target.focus({ preventScroll: true });
		}
	}

	function close(modal) {
		var index = -1;
		for (var i = 0; i < stack.length; i++) {
			if (stack[i].modal === modal) {
				index = i;
				break;
			}
		}
		if (index === -1) {
			return;
		}

		var entry = stack.splice(index, 1)[0];

		modal.classList.remove('is-open');
		// مودالِ بسته باید از درخت دسترس‌پذیری بیرون بماند، وگرنه محتوایش
		// همچنان برای صفحه‌خوان خوانده می‌شود
		modal.setAttribute('aria-hidden', 'true');
		setBackgroundInert(modal, false);

		if (!stack.length) {
			document.removeEventListener('keydown', onKeydown, true);
			document.body.classList.remove('zig-gallery-noscroll');
		}

		if (entry.returnTo && entry.returnTo.focus && document.contains(entry.returnTo)) {
			entry.returnTo.focus({ preventScroll: true });
		}

		if (typeof entry.onClose === 'function') {
			entry.onClose();
		}
	}

	window.Zig3dModal = { open: open, close: close };
})();
