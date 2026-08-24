/**
 * فرمِ درخواستِ مشاوره — یک مودالِ مشترک برایِ هر دکمه‌ای که
 * ‎data-zig-consultation‎ دارد (ویجتِ «دکمه»، دکمهٔ فرعیِ «کانفیگ محصول»، …).
 *
 * مودال یک‌بار، تنبل، و کاملاً سمتِ کلاینت ساخته می‌شود — نه در PHP —
 * چون هر چند دکمه‌ای که در صفحه باشند، دقیقاً همین یک مودال را باز
 * می‌کنند؛ ساختنش در رندرِ هر ویجت یعنی چند مودالِ یکسانِ تکراری در DOM.
 *
 * از ‎window.Zig3dModal‎ (‎zig3d-modal.js‎) برایِ باز/بستن، حبسِ فوکوس، و
 * inertکردنِ پس‌زمینه استفاده می‌کند — همان مکانیزمی که گالری استفاده
 * می‌کند؛ اینجا دوباره ساخته نمی‌شود.
 */
(function () {
	'use strict';

	var SELECTOR = '[data-zig-consultation]';
	var ACTION = 'zig3d_consultation';

	/*
	 * ‎09‎ + کدِ اپراتورِ معتبر (‎0[1-5]‎/‎1[0-9]‎/‎2[0-2]‎/‎3[0-9]‎/‎9[0-9]‎) +
	 * ۷ رقمِ آخر — کاملترین الگویِ رایجِ اعتبارسنجیِ شماره‌یِ موبایلِ
	 * ایرانی (همان که در اکثرِ کتابخانه‌ها/گیست‌هایِ فارسی برایِ همین کار
	 * به‌کار می‌رود)، نه فقط «۱۱ رقم باشد».
	 */
	var IR_MOBILE_RE = /^09(0[1-5]|1[0-9]|2[0-2]|3[0-9]|9[0-9])\d{7}$/;

	var elements = null;
	var activeTrigger = null;

	/**
	 * فرمت‌هایِ رایجِ ورودیِ شماره‌یِ موبایلِ ایرانی —‎+98912...‎،
	 * ‎0098912...‎، ‎98912...‎ی بدونِ ‎+‎، یا حتیِ بدونِ صفرِ ابتدایی
	 * (‎912...‎) — همه به ساختارِ یکتایِ ‎09xxxxxxxxx‎ برمی‌گردند. رشته‌ای
	 * که هیچ‌کدام از این‌ها نباشد (یا هنوز نصفه‌کاره است) دست‌نخورده
	 * برمی‌گردد؛ اعتبارسنجیِ نهایی با ‎IR_MOBILE_RE‎ی بالا انجام می‌شود.
	 */
	function normalizeIranianMobile(raw) {
		var hadPlus = 0 === String(raw).indexOf('+');
		var digits = String(raw).replace(/[^0-9]/g, '');

		if (hadPlus && 0 === digits.indexOf('98')) {
			return '0' + digits.slice(2);
		}

		if (0 === digits.indexOf('0098')) {
			return '0' + digits.slice(4);
		}

		if (12 === digits.length && 0 === digits.indexOf('98')) {
			return '0' + digits.slice(2);
		}

		if (10 === digits.length && 0 === digits.indexOf('9')) {
			return '0' + digits;
		}

		return digits;
	}

	/* ======================================================================
	 * ساختِ مودال
	 * =================================================================== */

	function buildModal() {
		var wrap = document.createElement('div');

		wrap.className = 'zig-consultation-modal';
		wrap.setAttribute('aria-hidden', 'true');

		wrap.innerHTML =
			'<div class="zig-consultation-modal__backdrop"></div>' +
			'<div class="zig-consultation-modal__stage" role="dialog" aria-modal="true" aria-labelledby="zig-consultation-title">' +
				'<button type="button" class="zig-consultation-modal__close" aria-label="بستن">' +
					'<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">' +
						'<path d="M1 1L13 13M13 1L1 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>' +
					'</svg>' +
				'</button>' +
				'<div class="zig-consultation-modal__body">' +
					'<h2 id="zig-consultation-title" class="zig-consultation-modal__title">ثبت درخواست مشاوره</h2>' +
					'<p class="zig-consultation-modal__subtitle">اطلاعات شما فقط برایِ تماسِ کارشناسانِ زیگ استفاده می‌شود.</p>' +
					'<form class="zig-consultation-modal__form" novalidate>' +
						'<div class="zig-consultation-modal__fields">' +
							'<div class="zig-consultation-modal__field">' +
								'<input type="text" id="zig-consultation-name" name="name" autocomplete="name" placeholder="نام و نام خانوادگی" aria-label="نام و نام خانوادگی" required>' +
								'<span class="zig-consultation-modal__field-icon" aria-hidden="true">' +
									'<svg width="18" height="18" viewBox="0 0 18 18" fill="none">' +
										'<path d="M14.2506 15.75V14.25C14.2506 13.4544 13.9345 12.6913 13.3718 12.1287C12.8091 11.5661 12.046 11.25 11.2503 11.25H6.74974C5.954 11.25 5.19085 11.5661 4.62818 12.1287C4.06551 12.6913 3.7494 13.4544 3.7494 14.25V15.75M12.0003 5.25C12.0003 6.90685 10.657 8.25 9 8.25C7.34296 8.25 5.99966 6.90685 5.99966 5.25C5.99966 3.59315 7.34296 2.25 9 2.25C10.657 2.25 12.0003 3.59315 12.0003 5.25Z" stroke="currentColor" stroke-linecap="round"/>' +
									'</svg>' +
								'</span>' +
							'</div>' +
							'<div class="zig-consultation-modal__field">' +
								'<input type="tel" id="zig-consultation-phone" name="phone" autocomplete="tel" inputmode="tel" placeholder="شماره موبایل" aria-label="شماره موبایل" required>' +
								'<span class="zig-consultation-modal__field-icon" aria-hidden="true">' +
									'<svg width="18" height="18" viewBox="0 0 18 18" fill="none">' +
										'<rect x="3.74005" y="1.25" width="10.5199" height="15.5" rx="3.5" stroke="currentColor"/>' +
										'<circle cx="9" cy="14.0412" r="1" stroke="currentColor"/>' +
										'<line x1="7.99977" y1="2.49616" x2="10.0002" y2="2.49616" stroke="currentColor" stroke-linecap="round"/>' +
									'</svg>' +
								'</span>' +
							'</div>' +
							'<div class="zig-consultation-modal__field zig-consultation-modal__field--textarea">' +
								'<textarea id="zig-consultation-message" name="message" rows="4" placeholder="توضیحات ( دلخواه )" aria-label="توضیحات (اختیاری)"></textarea>' +
							'</div>' +
							'<div class="zig-consultation-modal__overlay" hidden>' +
								'<span class="zig-consultation-modal__spinner" aria-hidden="true"></span>' +
								'<span>در حال ثبت درخواست...</span>' +
							'</div>' +
						'</div>' +
						'<p class="zig-consultation-modal__error" hidden></p>' +
						/*
						 * هانی‌پات: پرشدنش یعنی ربات، نه کاربر — کاربرِ واقعی
						 * هرگز فیلدی را که نه دیده می‌شود نه با Tab بهش
						 * می‌رسد پر نمی‌کند.
						 */
						'<div class="zig-consultation-modal__honeypot" aria-hidden="true">' +
							'<label for="zig-consultation-website">وبسایت</label>' +
							'<input type="text" id="zig-consultation-website" name="website" tabindex="-1" autocomplete="off">' +
						'</div>' +
						'<button type="submit" class="zig-consultation-modal__submit">ثبت درخواست</button>' +
					'</form>' +
					'<div class="zig-consultation-modal__success" hidden>' +
						/*
						 * چاشنیِ جشن — همان گروهِ تیک/نقطه‌هایِ سبزِ خروجی‌گرفته‌شده
						 * از خودِ فایلِ فیگما (نودِ ۲۵۴:۲۲۷۹۹)، عیناً، نه بازسازیِ
						 * دستی.
						 */
						'<svg class="zig-consultation-modal__confetti" aria-hidden="true" viewBox="0 0 476 113" fill="none">' +
							'<ellipse opacity="0.1" cx="100.59" cy="61.4268" rx="17.8537" ry="17.8061" fill="#7CC282"/>' +
							'<ellipse opacity="0.1" cx="320.086" cy="106.465" rx="6.30131" ry="6.28449" fill="#7CC282"/>' +
							'<ellipse opacity="0.3" cx="324.287" cy="43.6203" rx="6.30131" ry="6.28449" fill="#7CC282"/>' +
							'<ellipse opacity="0.3" cx="441.911" cy="-6.65559" rx="8.40175" ry="8.37932" fill="#7CC282"/>' +
							'<ellipse opacity="0.3" cx="262.324" cy="105.418" rx="3.15065" ry="3.14225" fill="#7CC282"/>' +
							'<ellipse opacity="0.1" cx="188.809" cy="34.1938" rx="7.35153" ry="7.33191" fill="#7CC282"/>' +
							'<ellipse opacity="0.3" cx="27.0751" cy="65.6151" rx="3.15065" ry="3.14225" fill="#7CC282"/>' +
							'<path opacity="0.1" d="M194.282 77.7958L170.256 101.022L160.234 91.3339C159.32 90.4507 157.843 90.4507 156.935 91.3339C156.027 92.217 156.021 93.6449 156.935 94.5228L168.604 105.803C169.517 106.686 170.994 106.686 171.902 105.803L197.575 80.9847C198.489 80.1015 198.489 78.6736 197.575 77.7958C196.673 76.9179 195.19 76.9179 194.282 77.7958Z" fill="#7CC282"/>' +
							'<path opacity="0.8" d="M406.514 48.0925L395.702 58.0466L391.192 53.8945C390.781 53.516 390.117 53.516 389.708 53.8945C389.299 54.273 389.297 54.8849 389.708 55.2612L394.959 60.0954C395.37 60.4739 396.035 60.4739 396.443 60.0954L407.996 49.4591C408.407 49.0806 408.407 48.4687 407.996 48.0925C407.59 47.7162 406.923 47.7162 406.514 48.0925Z" fill="#7CC282"/>' +
							'<path opacity="0.8" d="M47.3358 16.2577L37.7049 25.1245L33.6877 21.426C33.3215 21.0888 32.7294 21.0888 32.3654 21.426C32.0014 21.7631 31.9992 22.3082 32.3654 22.6433L37.0427 26.9495C37.4089 27.2867 38.0009 27.2867 38.3649 26.9495L48.6559 17.4751C49.0221 17.1379 49.0221 16.5928 48.6559 16.2577C48.2941 15.9226 47.6998 15.9226 47.3358 16.2577Z" fill="#7CC282"/>' +
						'</svg>' +
						'<span class="zig-consultation-modal__success-icon" aria-hidden="true">' +
							'<svg width="64" height="64" viewBox="0 0 64 64" fill="none">' +
								'<path d="M58.1818 29.3411V32.0175C58.1784 37.1957 56.6396 42.2565 53.7599 46.5601C50.8803 50.8637 46.7892 54.2167 42.0039 56.1953C37.2186 58.1738 31.9543 58.6889 26.8762 57.6756C21.7982 56.6622 17.1346 54.1658 13.4753 50.5021C9.81603 46.8383 7.32533 42.1718 6.31816 37.0925C5.31098 32.0133 5.83271 26.7495 7.81709 21.9666C9.80147 17.1838 13.1595 13.0968 17.4666 10.2224C21.7737 7.34806 26.8364 5.8154 32.0145 5.81827C35.6721 5.8062 39.2902 6.57486 42.6269 8.07284C42.9752 8.23746 43.3527 8.33125 43.7376 8.34881C44.1224 8.36637 44.5068 8.30733 44.8686 8.17511C45.2304 8.04289 45.5623 7.84015 45.8452 7.57861C46.128 7.31708 46.3561 7.00197 46.5162 6.65159C46.6762 6.30121 46.7651 5.92252 46.7777 5.53751C46.7902 5.1525 46.7262 4.76884 46.5893 4.40878C46.4524 4.04872 46.2453 3.71943 45.9801 3.44004C45.7149 3.16064 45.3968 2.93671 45.0444 2.78121C40.9487 0.936951 36.5063 -0.011255 32.0145 0.000100804C25.6858 -0.00277462 19.4984 1.87104 14.2345 5.38459C8.97069 8.89814 4.86675 13.8936 2.44191 19.7394C0.0170798 25.5852 -0.619779 32.0188 0.611656 38.2266C1.84309 44.4343 4.88757 50.1376 9.36024 54.6151C13.8329 59.0926 19.5329 62.1434 25.7393 63.3816C31.9458 64.6198 38.3801 63.9898 44.2285 61.5714C50.0769 59.1529 55.0769 55.0546 58.5962 49.7946C62.1155 44.5346 63.996 38.3492 64 32.0204V29.3411C64 28.5696 63.6935 27.8297 63.1479 27.2841C62.6023 26.7385 61.8624 26.432 61.0909 26.432C60.3194 26.432 59.5795 26.7385 59.0339 27.2841C58.4883 27.8297 58.1818 28.5696 58.1818 29.3411ZM59.0343 6.68812L32 33.7426L25.3265 27.075C25.0597 26.7908 24.7384 26.5632 24.3819 26.4056C24.0254 26.248 23.6408 26.1637 23.251 26.1576C22.8613 26.1516 22.4743 26.224 22.1131 26.3704C21.7518 26.5168 21.4238 26.7344 21.1482 27.0101C20.8727 27.2858 20.6554 27.6141 20.5092 27.9755C20.363 28.3368 20.2909 28.7239 20.2972 29.1136C20.3035 29.5034 20.3881 29.8879 20.546 30.2443C20.7038 30.6007 20.9317 30.9218 21.2161 31.1884L29.9433 39.9157C30.2135 40.1862 30.5343 40.4007 30.8874 40.5471C31.2406 40.6935 31.6191 40.7689 32.0014 40.7689C32.3837 40.7689 32.7624 40.6935 33.1156 40.5471C33.4687 40.4007 33.7895 40.1862 34.0596 39.9157L63.1506 10.7957C63.6801 10.2467 63.9729 9.51164 63.9657 8.74889C63.9586 7.98613 63.652 7.25672 63.1122 6.71773C62.5725 6.17874 61.8427 5.87332 61.0799 5.86723C60.3171 5.86115 59.5826 6.15488 59.0343 6.68519V6.68812Z" fill="#42A64B"/>' +
							'</svg>' +
						'</span>' +
						'<p class="zig-consultation-modal__success-title">درخواست شما ثبت شد!</p>' +
						'<p class="zig-consultation-modal__success-text">کارشناسان گروه زیگ پس از بررسی با شما تماس خواهند گرفت.</p>' +
						'<button type="button" class="zig-consultation-modal__success-close">بستن</button>' +
					'</div>' +
				'</div>' +
			'</div>';

		document.body.appendChild(wrap);

		return {
			root: wrap,
			form: wrap.querySelector('.zig-consultation-modal__form'),
			overlay: wrap.querySelector('.zig-consultation-modal__overlay'),
			submit: wrap.querySelector('.zig-consultation-modal__submit'),
			error: wrap.querySelector('.zig-consultation-modal__error'),
			success: wrap.querySelector('.zig-consultation-modal__success'),
			name: wrap.querySelector('#zig-consultation-name'),
			phone: wrap.querySelector('#zig-consultation-phone'),
			message: wrap.querySelector('#zig-consultation-message'),
			website: wrap.querySelector('#zig-consultation-website'),
			closeButtons: wrap.querySelectorAll('.zig-consultation-modal__close, .zig-consultation-modal__success-close')
		};
	}

	/* ======================================================================
	 * باز/بستن
	 * =================================================================== */

	function resetForm() {
		elements.root.classList.remove('zig-consultation-modal--success');
		elements.form.hidden = false;
		elements.success.hidden = true;
		elements.overlay.hidden = true;
		elements.error.hidden = true;
		elements.error.textContent = '';
		elements.submit.disabled = false;
		elements.submit.classList.remove('is-loading');
		elements.form.reset();
		clearInvalid(elements.name);
		clearInvalid(elements.phone);
	}

	function bind() {
		var i;

		for (i = 0; i < elements.closeButtons.length; i++) {
			elements.closeButtons[i].addEventListener('click', closeModal);
		}

		elements.root.querySelector('.zig-consultation-modal__backdrop').addEventListener('click', closeModal);

		elements.form.addEventListener('submit', function (event) {
			event.preventDefault();

			if (validate()) {
				submit();
			}
		});

		/*
		 * حینِ تایپ فقط رقم و علامتِ ‎+‎ی ابتدایی مجازند — نه هیچ کاراکترِ
		 * دیگری، و نه هنوز نرمال‌سازیِ پیشوند (چون تا کاربر کارش تمام
		 * نشده، رشته‌ای مثل ‎«98»‎ یا ‎«9»‎ی نصفه‌کاره معلوم نیست قرار است
		 * به کجا برسد).
		 */
		elements.phone.addEventListener('input', function () {
			var value = elements.phone.value;
			var plus = 0 === value.indexOf('+') ? '+' : '';

			elements.phone.value = plus + value.replace(/[^0-9]/g, '');
		});

		/*
		 * با خروج از فیلد، هر فرمتِ رایجِ شماره‌یِ موبایلِ ایرانی —
		 * ‎+98912...‎، ‎0098912...‎، ‎98912...‎، یا حتیِ بدونِ صفرِ ابتدایی
		 * (‎912...‎) — یکدست به ساختارِ ‎09xxxxxxxxx‎ برمی‌گردد، تا مشتری
		 * ببیند شماره‌اش به فرمتِ درست تبدیل شده.
		 */
		elements.phone.addEventListener('blur', function () {
			elements.phone.value = normalizeIranianMobile(elements.phone.value);
		});

		elements.name.addEventListener('input', function () { clearInvalid(elements.name); });
		elements.phone.addEventListener('input', function () { clearInvalid(elements.phone); });
	}

	/**
	 * نام اجباری است؛ شماره هم اجباری است و هم باید بعدِ نرمال‌سازی با
	 * الگویِ شماره‌یِ موبایلِ ایرانی جور باشد (‎09‎ + کدِ اپراتورِ معتبر +
	 * ۷ رقم) — همان چیزی که فیلدِ خالی/بی‌فرمت اصلاً به سرور نرسد. بازخورد
	 * فقط رنگِ قرمزِ حاشیهٔ همان فیلد است، نه حبابِ پیش‌فرضِ مرورگر (به
	 * همین دلیل فرم ‎novalidate‎ دارد).
	 */
	function validate() {
		var invalid = [];

		if ('' === elements.name.value.trim()) {
			invalid.push(elements.name);
		}

		elements.phone.value = normalizeIranianMobile(elements.phone.value);

		if (!IR_MOBILE_RE.test(elements.phone.value)) {
			invalid.push(elements.phone);
		}

		var i;

		for (i = 0; i < invalid.length; i++) {
			markInvalid(invalid[i]);
		}

		if (invalid.length) {
			invalid[0].focus();
		}

		return 0 === invalid.length;
	}

	function markInvalid(field) {
		field.closest('.zig-consultation-modal__field').classList.add('zig-consultation-modal__field--invalid');
		field.setAttribute('aria-invalid', 'true');
	}

	function clearInvalid(field) {
		field.closest('.zig-consultation-modal__field').classList.remove('zig-consultation-modal__field--invalid');
		field.removeAttribute('aria-invalid');
	}

	function openModal(trigger) {
		if (!elements) {
			elements = buildModal();
			bind();
		}

		activeTrigger = trigger;
		resetForm();

		if (window.Zig3dModal) {
			window.Zig3dModal.open(elements.root, { initialFocus: elements.name });
		} else {
			elements.root.classList.add('is-open');
			elements.root.removeAttribute('aria-hidden');
			elements.name.focus();
		}
	}

	function closeModal() {
		if (!elements) {
			return;
		}

		if (window.Zig3dModal) {
			window.Zig3dModal.close(elements.root);
		} else {
			elements.root.classList.remove('is-open');
			elements.root.setAttribute('aria-hidden', 'true');
		}
	}

	/* ======================================================================
	 * ارسال
	 * =================================================================== */

	function showError(message) {
		elements.overlay.hidden = true;
		elements.submit.disabled = false;
		elements.submit.classList.remove('is-loading');
		elements.error.textContent = message;
		elements.error.hidden = false;
	}

	function submit() {
		if (!activeTrigger) {
			return;
		}

		var endpoint = activeTrigger.getAttribute('data-zig-consultation-endpoint') || '';
		var nonce = activeTrigger.getAttribute('data-zig-consultation-nonce') || '';

		if ('' === endpoint) {
			return;
		}

		elements.error.hidden = true;
		elements.overlay.hidden = false;
		elements.submit.disabled = true;
		elements.submit.classList.add('is-loading');

		var body = new URLSearchParams();

		body.set('action', ACTION);
		body.set('nonce', nonce);
		body.set('name', elements.name.value);
		body.set('phone', elements.phone.value);
		body.set('message', elements.message.value);
		body.set('website', elements.website.value);
		body.set('source_url', window.location.href);
		body.set('source_title', document.title);
		body.set('product_id', activeTrigger.getAttribute('data-zig-consultation-product-id') || '');
		body.set('product_name', activeTrigger.getAttribute('data-zig-consultation-product-name') || '');

		fetch(endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (response) {
			return response.json().then(function (json) {
				return { ok: response.ok, json: json };
			});
		}).then(function (result) {
			if (!result.ok || !result.json || !result.json.success) {
				showError('ثبتِ درخواست انجام نشد. لطفاً دوباره تلاش کنید.');

				return;
			}

			elements.overlay.hidden = true;
			elements.submit.disabled = false;
			elements.submit.classList.remove('is-loading');
			elements.form.hidden = true;
			elements.success.hidden = false;
			elements.root.classList.add('zig-consultation-modal--success');
		}).catch(function () {
			showError('مشکلی در اتصال پیش آمد. لطفاً دوباره تلاش کنید.');
		});
	}

	/* ======================================================================
	 * راه‌اندازی
	 * =================================================================== */

	document.addEventListener('click', function (event) {
		var trigger = event.target.closest ? event.target.closest(SELECTOR) : null;

		if (!trigger) {
			return;
		}

		event.preventDefault();
		openModal(trigger);
	});
}());
