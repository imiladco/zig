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

	var elements = null;
	var activeTrigger = null;

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
					'<form class="zig-consultation-modal__form" novalidate>' +
						'<div class="zig-consultation-modal__fields">' +
							'<div class="zig-consultation-modal__field">' +
								'<input type="text" id="zig-consultation-name" name="name" autocomplete="name" placeholder="نام و نام خانوادگی" aria-label="نام و نام خانوادگی" required>' +
								'<span class="zig-consultation-modal__field-icon" aria-hidden="true">' +
									'<svg width="18" height="18" viewBox="0 0 18 18" fill="none">' +
										'<circle cx="9" cy="5.75" r="3" stroke="currentColor" stroke-width="1.3"/>' +
										'<path d="M3.5 15.25C3.5 12.2124 5.96243 9.75 9 9.75C12.0376 9.75 14.5 12.2124 14.5 15.25" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>' +
									'</svg>' +
								'</span>' +
							'</div>' +
							'<div class="zig-consultation-modal__field">' +
								'<input type="tel" id="zig-consultation-phone" name="phone" autocomplete="tel" inputmode="tel" placeholder="شماره موبایل" aria-label="شماره موبایل" required>' +
								'<span class="zig-consultation-modal__field-icon" aria-hidden="true">' +
									'<svg width="18" height="18" viewBox="0 0 18 18" fill="none">' +
										'<rect x="5.24" y="1.5" width="7.5" height="15" rx="2" stroke="currentColor" stroke-width="1.2"/>' +
										'<circle cx="9" cy="13.5" r="0.75" fill="currentColor"/>' +
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
						'<span class="zig-consultation-modal__success-icon" aria-hidden="true">' +
							'<svg width="64" height="64" viewBox="0 0 64 64" fill="none">' +
								'<circle cx="32" cy="32" r="32" fill="#42A64B"/>' +
								'<path d="M20.5 32.5L28 40L43.5 23.5" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>' +
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
		 * فیلدِ شماره فقط حقِ وجودِ شماره دارد — رقم و علامتِ ‎+‎ی ابتدایی،
		 * نه هیچ کاراکترِ دیگری. عمداً محدودیتی رویِ طول یا پیشوند
		 * (‎09‎/‎+98‎/‎0098‎) گذاشته نشده تا هر فرمتِ رایجِ ایرانی همچنان
		 * قابلِ تایپ بماند.
		 */
		elements.phone.addEventListener('input', function () {
			var value = elements.phone.value;
			var plus = 0 === value.indexOf('+') ? '+' : '';

			elements.phone.value = plus + value.replace(/[^0-9]/g, '');
		});

		elements.name.addEventListener('input', function () { clearInvalid(elements.name); });
		elements.phone.addEventListener('input', function () { clearInvalid(elements.phone); });
	}

	/**
	 * نام و شماره اجباری‌اند — قبل از هر درخواستِ آژاکس، همین‌جا سنجیده
	 * می‌شوند تا فیلدِ خالی/بی‌فایده اصلاً به سرور نرسد. بازخورد فقط رنگِ
	 * قرمزِ حاشیهٔ همان فیلد است، نه حبابِ پیش‌فرضِ مرورگر (به همین دلیل
	 * فرم ‎novalidate‎ دارد).
	 */
	function validate() {
		var invalid = [];

		if ('' === elements.name.value.trim()) {
			invalid.push(elements.name);
		}

		if ('' === elements.phone.value.trim()) {
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
