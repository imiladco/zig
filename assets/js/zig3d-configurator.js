/**
 * کانفیگ‌گر محصول — دراپ‌داون‌های آبشاری، بدون آژاکس.
 *
 * تعدادِ ترکیب‌هایِ یک محصول (کانفیگ × متریال × …) کوچک است، پس کلِ دادهٔ
 * واریانت‌ها همان اول در HTML تعبیه می‌شود (تگِ JSONِ کنارِ خودِ ویجت) و
 * سوییچ بینِ ترکیب‌ها کاملاً سمتِ کلاینت است — نه یک درخواستِ شبکه به
 * ازایِ هر تغییرِ دراپ‌داون.
 *
 * منطقِ آبشاری همان چیزی است که خودِ ووکامرس در دراپ‌داونِ واریانت پیاده
 * می‌کند: با هر تغییر، گزینه‌هایی که با انتخاب‌هایِ فعلیِ بقیهٔ کشوها هیچ
 * واریانتی نمی‌سازند غیرفعال می‌شوند؛ کشویی که مقدارش دیگر معتبر نیست به
 * حالتِ «انتخاب کنید» برمی‌گردد، نه اینکه بی‌صدا غلط بماند.
 *
 * قیمت، بجِ موجودی و متنِ زمانِ به‌روزرسانی برایِ هر واریانت از سرور
 * می‌آیند — متن‌هایِ آمادهٔ نمایش، نه عددِ خام. این فایل فقط جابه‌جایشان
 * می‌کند، دوباره فرمت نمی‌سازد؛ منطقِ نمایش باید یک‌جا بماند وگرنه سمتِ
 * سرور و سمتِ کلاینت دیر یا زود از هم واگرا می‌شوند.
 *
 * قراردادِ کلیک: این فایل با هیچ دکمه‌ای کاری ندارد. تنها ردی که برایِ
 * رفتارِ بعدیِ دو دکمهٔ پایین می‌گذارد ‎data-variation-id‎ رویِ ریشه است —
 * شناسهٔ واریانتِ کامل‌شده، یا رشتهٔ خالی وقتی هنوز کامل نیست.
 */
(function () {
	'use strict';

	function boot(root) {
		if (root.zigConfigurator) {
			return;
		}

		var script = root.querySelector('.zig-configurator__data');

		if (!script) {
			return;
		}

		var payload;

		try {
			payload = JSON.parse(script.textContent || '{}');
		} catch (e) {
			return;
		}

		if (!payload || !payload.rows || !payload.rows.length) {
			return;
		}

		root.zigConfigurator = new Configurator(root, payload);
	}

	/* ======================================================================
	 * نمونه
	 * =================================================================== */

	function Configurator(root, payload) {
		this.root = root;
		this.keys = payload.keys || [];
		this.rows = payload.rows || [];
		this.fallback = payload['default'] || null;

		this.selects = [];

		var nodes = root.querySelectorAll('.zig-configurator__select');
		var i;

		for (i = 0; i < nodes.length; i++) {
			this.selects.push(nodes[i]);
		}

		this.amount = root.querySelector('.zig-configurator__amount');
		this.stockWrap = root.querySelector('.zig-configurator__stock');
		this.stockLabel = root.querySelector('.zig-configurator__stock-label');
		this.updatedWrap = root.querySelector('.zig-configurator__updated');
		this.updatedValue = root.querySelector('.zig-configurator__updated-value');

		/*
		 * فقط وقتی رویِ دکمهٔ اصلی چاپ شده که الگویِ پیامِ واتساپ به
		 * ‎[متغیرهای انتخابی]‎ نیاز دارد (‎render_button()‎ سمتِ PHP). نبودش
		 * یعنی این محصول متغیر نیست یا الگو از این توکن استفاده نمی‌کند —
		 * در هر دو حالت چیزی برایِ روزآمدسازی نیست.
		 */
		this.whatsappBtn = root.querySelector('.zig-configurator__btn--primary[data-zig-wa-template]');

		this.bind();
		this.refresh();
	}

	Configurator.prototype.bind = function () {
		var self = this;
		var i;

		for (i = 0; i < this.selects.length; i++) {
			this.selects[i].addEventListener('change', function () {
				self.refresh();
			});
		}
	};

	/** انتخاب‌هایِ فعلی: شیءِ کلید → مقدار (رشتهٔ خالی یعنی هنوز انتخاب نشده) */
	Configurator.prototype.selection = function () {
		var out = {};
		var i;

		for (i = 0; i < this.selects.length; i++) {
			out[this.selects[i].getAttribute('data-key')] = this.selects[i].value;
		}

		return out;
	};

	/**
	 * آیا این ردیفِ واریانت با انتخاب‌هایِ داده‌شده جور است؟
	 *
	 * مقدارِ خالیِ خودِ ردیف برایِ یک کلید یعنی «هر مقداری» — همان معنایِ
	 * اتریبیوتِ خالی در خودِ ووکامرس. کلیدی که هنوز انتخاب نشده (مقدارش
	 * خالی است) نادیده گرفته می‌شود، نه اینکه عدمِ تطبیق حساب شود.
	 */
	Configurator.prototype.matches = function (row, selection) {
		var i;
		var key;
		var wanted;
		var have;

		for (i = 0; i < this.keys.length; i++) {
			key = this.keys[i];
			wanted = selection[key];

			if (!wanted) {
				continue;
			}

			have = row.attrs[key];

			if (have && have !== wanted) {
				return false;
			}
		}

		return true;
	};

	/**
	 * یک دور کامل: هر انتخابِ نامعتبر را بازنشانی می‌کند، گزینه‌هایِ هر
	 * کشو را روزآمد می‌کند، و اگر انتخابی واقعاً عوض شد یک‌بارِ دیگر از
	 * اول اجرا می‌شود — سقفش تعدادِ کشوهاست تا هیچ‌وقت گیر نکند.
	 */
	Configurator.prototype.refresh = function () {
		var pass;

		for (pass = 0; pass <= this.keys.length; pass++) {
			if (!this.reconcile()) {
				break;
			}
		}

		this.apply();
	};

	/** @return {boolean} آیا کشویی بازنشانی شد؟ */
	Configurator.prototype.reconcile = function () {
		var selection = this.selection();
		var changed = false;
		var i;

		for (i = 0; i < this.selects.length; i++) {
			if (this.reconcileSelect(this.selects[i], selection)) {
				changed = true;
			}
		}

		return changed;
	};

	/** روزآمدسازیِ یک کشو؛ @return {boolean} آیا مقدارش بازنشانی شد؟ */
	Configurator.prototype.reconcileSelect = function (select, selection) {
		var key = select.getAttribute('data-key');
		var options = select.querySelectorAll('option[value]:not([value=""])');
		var currentValid = '' === select.value;
		var j;
		var option;
		var value;
		var valid;
		var probe;
		var k;

		for (j = 0; j < options.length; j++) {
			option = options[j];
			value = option.value;

			probe = {};

			for (k in selection) {
				if (Object.prototype.hasOwnProperty.call(selection, k)) {
					probe[k] = selection[k];
				}
			}

			probe[key] = value;

			valid = this.anyMatch(probe);
			option.disabled = !valid;

			if (value === select.value && valid) {
				currentValid = true;
			}
		}

		if (!currentValid && '' !== select.value) {
			select.value = '';

			return true;
		}

		return false;
	};

	Configurator.prototype.anyMatch = function (selection) {
		var i;

		for (i = 0; i < this.rows.length; i++) {
			if (this.matches(this.rows[i], selection)) {
				return true;
			}
		}

		return false;
	};

	/** ردیفِ کاملاً منطبق با انتخابِ فعلی، یا ‎null‎ اگر هنوز کامل نیست */
	Configurator.prototype.matched = function () {
		var selection = this.selection();
		var i;

		for (i = 0; i < this.keys.length; i++) {
			if (!selection[this.keys[i]]) {
				return null;
			}
		}

		for (i = 0; i < this.rows.length; i++) {
			if (this.matches(this.rows[i], selection)) {
				return this.rows[i];
			}
		}

		return null;
	};

	Configurator.prototype.apply = function () {
		var row = this.matched() || this.fallback;

		this.root.setAttribute('data-variation-id', row && row.id ? String(row.id) : '');
		this.applyWhatsapp();

		if (!row) {
			return;
		}

		if (this.amount) {
			this.amount.textContent = row.amount || '';
		}

		if (this.stockWrap) {
			this.stockWrap.className = this.stockWrap.className.replace(/\s*\bzig-configurator__stock--\S+/g, '');
			this.stockWrap.className += ' zig-configurator__stock--' + (row.bucket || 'instock');
			this.stockWrap.hidden = !row.stock;
		}

		if (this.stockLabel) {
			this.stockLabel.textContent = row.stock || '';
		}

		if (this.updatedWrap) {
			this.updatedWrap.hidden = !row.updated;
		}

		if (this.updatedValue) {
			this.updatedValue.textContent = row.updated || '';
		}
	};

	/**
	 * روزآمدسازیِ ‎href‎ی دکمهٔ واتساپ با انتخابِ فعلیِ کشوها.
	 *
	 * الگو و شمارهٔ واتساپ سمتِ PHP آماده شده‌اند (‎data-zig-wa-base‎/
	 * ‎data-zig-wa-template‎)؛ اینجا فقط توکنِ ‎[متغیرهای انتخابی]‎ با
	 * برچسبِ اتریبیوت/گزینهٔ همان چیزی که در ‎<label>‎/‎<option>‎ چاپ شده
	 * جایگزین می‌شود — دقیقاً همان دو منبعی که خودِ سرور برایِ ساختنِ
	 * کشوها استفاده کرده، پس چیزی دوباره فرمت نمی‌شود. کشوهایی که هنوز
	 * انتخابی ندارند بی‌صدا از قلم می‌افتند.
	 */
	Configurator.prototype.applyWhatsapp = function () {
		if (!this.whatsappBtn) {
			return;
		}

		var template = this.whatsappBtn.getAttribute('data-zig-wa-template') || '';
		var base = this.whatsappBtn.getAttribute('data-zig-wa-base') || '';
		var lines = [];
		var i;
		var select;
		var field;
		var label;
		var option;

		for (i = 0; i < this.selects.length; i++) {
			select = this.selects[i];

			if (!select.value) {
				continue;
			}

			field = select.closest('.zig-configurator__field');
			label = field ? field.querySelector('.zig-configurator__label') : null;
			option = select.options[select.selectedIndex];

			if (!label || !option) {
				continue;
			}

			lines.push(label.textContent + ': ' + option.textContent);
		}

		this.whatsappBtn.setAttribute('href', base + zigWhatsappEncode(zigWhatsappMessage(template, lines)));
	};

	/**
	 * همتایِ سمتِ کلاینتِ ‎Product_Configurator::whatsapp_message()‎ی PHP —
	 * دو منطق باید عیناً یکی بمانند، وگرنه پیامِ اولیه (رندرِ سرور) و پیامِ
	 * بعد از اولین تغییرِ کشو (این تابع) از هم واگرا می‌شوند.
	 */
	var ZIG_WA_VARIANTS_TOKEN = '[متغیرهای انتخابی]';

	function zigWhatsappMessage(template, lines) {
		var block = lines.join('\n');
		var rows = template.split('\n');
		var out = [];
		var i;

		for (i = 0; i < rows.length; i++) {
			if (ZIG_WA_VARIANTS_TOKEN === rows[i].trim()) {
				if ('' !== block) {
					out.push(block);
				}

				continue;
			}

			out.push(rows[i].split(ZIG_WA_VARIANTS_TOKEN).join(block));
		}

		return out.join('\n');
	}

	/**
	 * همتایِ ‎rawurlencode()‎ی PHP: بر خلافِ ‎encodeURIComponent‎ی خودِ
	 * جاوااسکریپت، ‎! ' ( ) *‎ را هم اینکود می‌کند — تا آدرسِ ساخته‌شدهٔ
	 * اینجا با آدرسِ اولیهٔ رندرشدهٔ سرور، بایت‌به‌بایت یکی بماند.
	 */
	function zigWhatsappEncode(text) {
		return encodeURIComponent(text).replace(/[!'()*]/g, function (char) {
			return '%' + char.charCodeAt(0).toString(16).toUpperCase();
		});
	}

	/* ======================================================================
	 * راه‌اندازی
	 * =================================================================== */

	function init(scope) {
		var context = scope || document;
		var roots = context.querySelectorAll('.zig-configurator');
		var i;

		/*
		 * ‎querySelectorAll‎ فقط فرزندان را می‌گردد، نه خودِ عنصر. هوکِ
		 * ادیتورِ المنتور دامنه را دقیقاً همان ریشهٔ ویجت می‌دهد — که
		 * معمولاً خودش همین کلاس را دارد — پس این حالت هم باید بررسی شود.
		 */
		if (context.classList && context.classList.contains('zig-configurator')) {
			boot(context);
		}

		for (i = 0; i < roots.length; i++) {
			boot(roots[i]);
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', function () {
			init();
		});
	} else {
		init();
	}

	/* پیش‌نمایشِ زندهٔ ادیتورِ المنتور: هر بار ویجت رویِ کنواس دوباره ساخته می‌شود */
	document.addEventListener('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !elementorFrontend.hooks) {
			return;
		}

		elementorFrontend.hooks.addAction('frontend/element_ready/zig3d-product-configurator.default', function ($scope) {
			var el = $scope && $scope[0] ? $scope[0] : $scope;

			if (el && el.querySelectorAll) {
				init(el);
			}
		});
	});
}());
