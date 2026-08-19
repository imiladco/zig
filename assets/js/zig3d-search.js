/*
 * سرچِ ایجکسیِ محصولات.
 *
 * شش حالت، و این فایل فقط سویچِ بینِ آن‌هاست — نه چیدمان، نه رنگ، آن‌ها
 * مالِ CSS/کنترل‌هایِ المنتورند:
 *
 *   S0  بسته/پیش‌فرض        فقط فیلد
 *   S1  باز، خالی، بدونِ تاریخچه   فقط «پرطرفدار» (که خودِ سرور همیشه رندرش کرده)
 *   S2  باز، خالی، با تاریخچه      «اخیر» + «پرطرفدار»
 *   S3  باز، نتیجه دارد            محصولات (+ لینکِ «بیشتر» اگر لازم) + «پرطرفدار»
 *   S4  باز، بدونِ نتیجه           پیام + «پرطرفدار»
 *   S5  بسته، مقدار حفظ‌شده        Esc/کلیکِ بیرون بدونِ پاک‌کردنِ متن
 *
 * قراردادِ کلیک، همان‌قدر قطعی که در سرور: هر ردیفِ محصول و هر چیپ یک
 * ‎<a href>‎ واقعی است. این فایل هیچ‌وقت با کلیک روی آن‌ها ‎preventDefault‎
 * نمی‌کند — فقط linkها را می‌سازد، مرورگر خودش می‌برد.
 *
 * دو کشِ جدا از هم، عمداً:
 *
 *   • این ‎Map‎ی که این‌جاست فقط برایِ همین صفحه، همین بارگذاری است —
 *     برگشتن با دکمهٔ Backِ مرورگر به یک کوئریِ قبلی، دوباره فچ نمی‌کند.
 *   • کشِ واقعیِ سمتِ سرور (نسخه‌دار، با TTL) جایِ دیگری است — این‌جا فقط
 *     مصرف‌کننده‌اش هستیم.
 */
(function () {
	'use strict';

	var FALLBACK_STATUSES = [404, 401, 403];

	function boot(root) {
		if (root.zigSearch) {
			return;
		}

		root.zigSearch = new Search(root);
	}

	/* ======================================================================
	 * نمونه
	 * =================================================================== */

	function Search(root) {
		this.root = root;
		this.field = root.querySelector('.zig-search__field');
		this.input = root.querySelector('.zig-search__input');
		this.clearBtn = root.querySelector('.zig-search__clear');
		this.panel = root.querySelector('.zig-search__panel');

		this.recentSection = root.querySelector('.zig-search__section--recent');
		this.recentChips = root.querySelector('[data-role="recent-chips"]');
		this.clearHistoryBtn = root.querySelector('.zig-search__clear-history');

		this.productsSection = root.querySelector('.zig-search__section--products');
		this.productsList = root.querySelector('[data-role="products"]');
		this.moreLink = root.querySelector('.zig-search__more');

		this.emptySection = root.querySelector('.zig-search__section--empty');
		this.errorSection = root.querySelector('.zig-search__section--error');
		this.errorText = root.querySelector('.zig-search__error-text');
		this.backdrop = root.querySelector('.zig-search__backdrop');

		this.chevronTpl = root.querySelector('template[data-zig-icon="chevron-icon"]');
		this.recentIconTpl = root.querySelector('template[data-zig-icon="recent-icon"]');

		this.minChars = parseInt(root.getAttribute('data-min-chars'), 10) || 2;
		this.debounceMs = parseInt(root.getAttribute('data-debounce'), 10) || 300;
		this.postId = root.getAttribute('data-post-id') || '0';
		this.widgetId = root.getAttribute('data-widget-id') || '';
		this.restUrl = root.getAttribute('data-rest-url') || '';
		this.ajaxUrl = root.getAttribute('data-ajax-url') || '';
		this.ajaxAction = root.getAttribute('data-ajax-action') || 'zig3d_search';
		this.recentEnabled = '1' === root.getAttribute('data-recent-enabled');
		this.recentMax = parseInt(root.getAttribute('data-recent-max'), 10) || 4;
		this.recentExpiryMs = (parseInt(root.getAttribute('data-recent-expiry-days'), 10) || 30) * 86400000;
		this.recentKey = root.getAttribute('data-recent-storage-key') || 'zig3d_search_recent';
		this.resultsUrlTemplate = root.getAttribute('data-results-url-template') || '';
		this.shortcut = '1' === root.getAttribute('data-shortcut');

		this.cache = new window.Map();
		this.controller = null;
		this.ticket = 0;
		this.timer = null;
		this.useFallback = false;
		this.options = []; // ردیف‌ها/چیپ‌هایِ قابلِ ناوبری با کیبورد در پنلِ باز

		this.bind();
	}

	/* ------------------------------------------------------------------
	 * اتصال رویدادها
	 * ------------------------------------------------------------------ */

	Search.prototype.bind = function () {
		var self = this;

		this.input.addEventListener('input', function () {
			self.onInput();
		});

		this.input.addEventListener('focus', function () {
			self.onFocus();
		});

		this.input.addEventListener('keydown', function (event) {
			self.onKeydown(event);
		});

		this.field.addEventListener('submit', function (event) {
			// خودِ فرم مقصدی ندارد که سزاوارِ رفتن باشد؛ Enter را خودِ
			// onKeydown مدیریت می‌کند (رفتن به ردیفِ فعال یا اولین نتیجه).
			event.preventDefault();
		});

		if (this.clearBtn) {
			this.clearBtn.addEventListener('click', function () {
				self.clearInput();
			});
		}

		if (this.clearHistoryBtn) {
			this.clearHistoryBtn.addEventListener('click', function () {
				self.clearRecent();
			});
		}

		document.addEventListener('click', function (event) {
			if (!self.root.contains(event.target)) {
				self.close(true);
			}
		});

		document.addEventListener('focusin', function (event) {
			if (!self.root.contains(event.target)) {
				self.close(true);
			}
		});

		if (this.shortcut) {
			document.addEventListener('keydown', function (event) {
				var isShortcut = (event.ctrlKey || event.metaKey) && 'k' === event.key.toLowerCase();

				if (isShortcut) {
					event.preventDefault();
					self.input.focus();
					self.input.select();
				}
			});
		}
	};

	/* ------------------------------------------------------------------
	 * ورودی
	 * ------------------------------------------------------------------ */

	Search.prototype.onFocus = function () {
		var value = this.input.value.trim();

		if (value.length >= this.minChars) {
			// فوکوسِ دوباره روی متنی که قبلاً هم بود — همان نتیجه دوباره
			// بی‌درخواستِ تازه نشان داده می‌شود (S3/S4)، از رویِ کش.
			this.search(value);

			return;
		}

		this.showIdle();
	};

	Search.prototype.onInput = function () {
		var trimmed = this.input.value.trim();

		if (trimmed.length < this.minChars) {
			window.clearTimeout(this.timer);
			this.abortInFlight();
			this.showIdle();

			return;
		}

		var self = this;

		window.clearTimeout(this.timer);
		this.timer = window.setTimeout(function () {
			self.search(trimmed);
		}, this.debounceMs);
	};

	/*
	 * ضربدر در طرح «بستنِ اورلی» است، نه «خالی‌کردنِ فیلد» — پس هم متن را
	 * پاک می‌کند و هم پنل را می‌بندد و به S0 برمی‌گردد.
	 */
	Search.prototype.clearInput = function () {
		this.input.value = '';
		window.clearTimeout(this.timer);
		this.abortInFlight();
		this.close(false);
	};

	/* ------------------------------------------------------------------
	 * کیبورد — الگویِ WAI-ARIA combobox
	 * ------------------------------------------------------------------ */

	Search.prototype.onKeydown = function (event) {
		if ('Escape' === event.key) {
			// مقدار حفظ می‌شود (S5) — این دقیقاً همان چیزی است که Esc را
			// از دکمهٔ پاک‌کردن جدا می‌کند.
			this.close(true);

			return;
		}

		if (!this.isOpen()) {
			return;
		}

		if ('ArrowDown' === event.key) {
			event.preventDefault();
			this.moveActive(1);

			return;
		}

		if ('ArrowUp' === event.key) {
			event.preventDefault();
			this.moveActive(-1);

			return;
		}

		if ('Enter' === event.key) {
			event.preventDefault();

			var target = this.options[this.activeIndex] || this.options[0];
			var typed = this.input.value.trim();

			if (target) {
				this.remember(typed);
				window.location.href = target.href;

				return;
			}

			// هیچ محصولی برایِ رفتن نیست (S1/S2/S4) — Enter همان کاری را
			// می‌کند که در یک باکسِ سرچِ معمولی می‌کرد: برو صفحهٔ نتایج.
			if (typed.length >= this.minChars && this.resultsUrlTemplate) {
				this.remember(typed);
				window.location.href = this.buildResultsUrl(typed);
			}
		}
	};

	Search.prototype.moveActive = function (delta) {
		if (!this.options.length) {
			return;
		}

		var next = this.activeIndex + delta;

		if (next < 0) {
			next = this.options.length - 1;
		} else if (next >= this.options.length) {
			next = 0;
		}

		this.setActive(next);
	};

	Search.prototype.setActive = function (index) {
		for (var i = 0; i < this.options.length; i++) {
			this.options[i].el.classList.toggle('is-active', i === index);
		}

		this.activeIndex = index;

		var option = this.options[index];

		this.input.setAttribute('aria-activedescendant', option ? option.el.id : '');
	};

	/* ------------------------------------------------------------------
	 * فچ — REST اول، admin-ajax فقط برایِ شکستِ مسیر/امنیت/شبکه
	 * ------------------------------------------------------------------ */

	Search.prototype.search = function (query) {
		var self = this;

		if (this.cache.has(query)) {
			this.render(this.cache.get(query), query);

			return;
		}

		this.abortInFlight();

		var ticket = ++this.ticket;

		this.controller = 'undefined' !== typeof window.AbortController ? new window.AbortController() : null;

		this.setLoading(true);

		this.request(query, false)
			.then(function (payload) {
				if (ticket !== self.ticket) {
					return;
				}

				self.setLoading(false);
				self.cache.set(query, payload);
				self.render(payload, query);
			})
			.catch(function (error) {
				if (ticket !== self.ticket) {
					return;
				}

				self.setLoading(false);
				self.onFetchError(error, query, ticket);
			});
	};

	Search.prototype.setLoading = function (on) {
		this.root.classList.toggle('is-loading', Boolean(on));
		this.input.setAttribute('aria-busy', on ? 'true' : 'false');
	};

	/**
	 * یک تلاشِ فچ — یا REST یا admin-ajax، بسته به ‎useFallback‎.
	 *
	 * خطایِ ‎status‎دار (که ‎request()‎ می‌سازد) این‌جا تصمیم می‌گیرد آیا
	 * سزاوارِ سوییچ به درِ دوم است. فقط شکستِ مسیر/امنیت/شبکه — هیچ‌وقت
	 * ‎5xx‎/‎400‎، که خطایِ واقعیِ سروری‌اند و باید همان‌طور که هستند بالا
	 * بروند، نه پشتِ یک «بگذار دوباره امتحان کنم» پنهان شوند.
	 */
	Search.prototype.onFetchError = function (error, query, ticket) {
		var self = this;

		/*
		 * لغوِ عمدی خطا نیست. بدونِ این تشخیص، هر بار که کاربر یک
		 * کاراکتر دیگر تایپ می‌کند (یا فیلد را پاک می‌کند، یا Esc
		 * می‌زند) ‎AbortError‎ی بدونِ ‎status‎ بالا می‌آید و — چون
		 * «بدونِ status» را «خطایِ شبکه» می‌خواندیم — بی‌دلیل کلاینت را
		 * برایِ همیشه رویِ مسیرِ admin-ajax قفل می‌کرد.
		 */
		if (error && 'AbortError' === error.name) {
			return;
		}

		var status = error && error.status;
		var isFallbackWorthy = !this.useFallback && (
			!status || FALLBACK_STATUSES.indexOf(status) > -1
		);

		if (isFallbackWorthy) {
			this.useFallback = true;

			this.request(query, true)
				.then(function (payload) {
					if (ticket !== self.ticket) {
						return;
					}

					self.setLoading(false);
					self.cache.set(query, payload);
					self.render(payload, query);
				})
				.catch(function (fallbackError) {
					if (ticket !== self.ticket) {
						return;
					}

					self.setLoading(false);

					if (!fallbackError || 'AbortError' !== fallbackError.name) {
						self.showError(fallbackError);
					}
				});

			return;
		}

		this.showError(error);
	};

	Search.prototype.request = function (query, useAjax) {
		var url;
		var options = { credentials: 'same-origin' };

		if (this.controller) {
			options.signal = this.controller.signal;
		}

		if (useAjax) {
			var body = new window.FormData();

			body.append('action', this.ajaxAction);
			body.append('q', query);
			body.append('post_id', this.postId);
			body.append('widget_id', this.widgetId);

			url = this.ajaxUrl;
			options.method = 'POST';
			options.body = body;
		} else {
			var params = new window.URLSearchParams();

			params.set('q', query);
			params.set('post_id', this.postId);
			params.set('widget_id', this.widgetId);

			url = this.restUrl + '?' + params.toString();
		}

		return window.fetch(url, options).then(function (response) {
			if (!response.ok) {
				var err = new Error(String(response.status));
				err.status = response.status;

				throw err;
			}

			return response.json();
		}).then(function (payload) {
			var body = useAjax ? (payload && payload.data) : payload;

			if (!body || !window.Array.isArray(body.results)) {
				var shapeErr = new Error('shape');
				shapeErr.status = null;

				throw shapeErr;
			}

			return body;
		});
	};

	Search.prototype.abortInFlight = function () {
		if (this.controller) {
			this.controller.abort();
			this.controller = null;
		}
	};

	/* ------------------------------------------------------------------
	 * حالت‌ها
	 * ------------------------------------------------------------------ */

	Search.prototype.isOpen = function () {
		return !this.panel.hidden;
	};

	/** S1/S2 — پنل باز، بدونِ نتیجه/بدونِ خطا، فقط تاریخچه (اگر باشد) + پرطرفدار */
	Search.prototype.showIdle = function () {
		this.root.classList.remove('is-error');
		this.hideSection(this.errorSection);
		this.hideSection(this.productsSection);
		this.hideSection(this.emptySection);

		var chips = this.recentEnabled ? this.renderRecent() : 0;

		if (chips > 0) {
			this.showSection(this.recentSection);
		} else {
			this.hideSection(this.recentSection);
		}

		this.open();
	};

	/** S4 — بدونِ نتیجه */
	Search.prototype.showEmpty = function () {
		this.hideSection(this.recentSection);
		this.hideSection(this.productsSection);
		this.showSection(this.emptySection);
		this.open();
	};

	/**
	 * خطایِ فنی — بخشِ خودش را دارد، نه «نتیجه‌ای پیدا نشد».
	 *
	 * یکی‌کردنِ این دو یعنی کاربری که شبکه‌اش قطع شده خیال می‌کند محصولی
	 * وجود ندارد. ‎429‎ هم پیامِ آرامِ خودش را می‌گیرد: آن یک خرابی نیست،
	 * فقط «کمی تندتر از حد» است.
	 */
	Search.prototype.showError = function (error) {
		this.root.classList.add('is-error');
		this.hideSection(this.recentSection);
		this.hideSection(this.productsSection);
		this.hideSection(this.emptySection);

		if (this.errorText) {
			var rateLimited = error && 429 === error.status;
			var message = rateLimited
				? this.errorText.getAttribute('data-rate-limit-message')
				: this.errorText.getAttribute('data-message');

			this.errorText.textContent = message || this.errorText.textContent;
		}

		this.showSection(this.errorSection);
		this.open();
	};

	Search.prototype.render = function (payload, query) {
		this.root.classList.remove('is-error');
		this.hideSection(this.errorSection);

		if (!payload.results.length) {
			this.showEmpty();

			return;
		}

		this.hideSection(this.recentSection);
		this.hideSection(this.emptySection);
		this.renderProducts(payload.results, Boolean(payload.has_more), query);
		this.showSection(this.productsSection);
		this.open();
	};

	Search.prototype.open = function () {
		this.panel.hidden = false;
		this.clearBtn.hidden = false;

		if (this.backdrop) {
			this.backdrop.hidden = false;
		}

		this.input.setAttribute('aria-expanded', 'true');
		this.root.classList.add('is-open');
	};

	/**
	 * @param {boolean} preserveValue بستنِ S5 (Esc/کلیکِ بیرون) در برابرِ بازگشتِ کاملِ S0
	 */
	Search.prototype.close = function (preserveValue) {
		if (this.panel.hidden) {
			return;
		}

		/*
		 * بستن باید درخواستِ در پرواز را هم ببندد، وگرنه S5 می‌شکند:
		 * کاربر Esc می‌زند، پنل بسته می‌شود، و چند لحظه بعد پاسخِ همان
		 * درخواست می‌رسد و ‎render()‎ دوباره ‎open()‎ صدا می‌زند — پنل
		 * خودبه‌خود باز می‌شود، انگار Esc اصلاً زده نشده.
		 *
		 * هم ‎abort‎ لازم است هم بالابردنِ بلیت: اولی درخواست را قطع
		 * می‌کند، دومی پاسخی را که شاید همین حالا در راه است بی‌اعتبار
		 * می‌کند.
		 */
		this.abortInFlight();
		++this.ticket;
		this.setLoading(false);

		this.panel.hidden = true;
		// ضربدر با پنل می‌آید و با پنل می‌رود — حتی در S5 که مقدارِ
		// تایپ‌شده در فیلد می‌ماند، طرح ضربدری نشان نمی‌دهد.
		this.clearBtn.hidden = true;
		this.input.setAttribute('aria-expanded', 'false');
		this.input.setAttribute('aria-activedescendant', '');
		this.root.classList.remove('is-open');

		if (this.backdrop) {
			this.backdrop.hidden = true;
		}

		this.options = [];
		this.activeIndex = -1;

		if (!preserveValue) {
			this.input.value = '';
		}
	};

	Search.prototype.showSection = function (section) {
		if (section) {
			section.hidden = false;
		}
	};

	Search.prototype.hideSection = function (section) {
		if (section) {
			section.hidden = true;
		}
	};

	/* ------------------------------------------------------------------
	 * رندرِ محصولات — همیشه لینکِ واقعی به پرمالینک
	 * ------------------------------------------------------------------ */

	Search.prototype.renderProducts = function (results, hasMore, query) {
		this.productsList.textContent = '';
		this.options = [];

		for (var i = 0; i < results.length; i++) {
			var item = results[i];
			var row = this.buildProductRow(item, i);

			this.productsList.appendChild(row);
			this.options.push({ el: row, href: item.permalink || '#' });
		}

		if (this.moreLink) {
			if (hasMore && this.resultsUrlTemplate) {
				this.moreLink.href = this.buildResultsUrl(query);
				this.moreLink.hidden = false;
				this.moreLink.onclick = (function (self, q) {
					return function () {
						self.remember(q);
					};
				})(this, query);
			} else {
				this.moreLink.hidden = true;
			}
		}

		this.setActive(-1);
	};

	Search.prototype.buildProductRow = function (item, index) {
		var row = document.createElement('a');

		row.className = 'zig-search__product';
		row.id = this.widgetId + '-product-' + index;
		row.setAttribute('role', 'option');
		row.href = item.permalink || '#';

		var self = this;

		// انتخابِ یک نتیجه یعنی این جست‌وجو به مقصد رسید — همان چیزی که
		// ارزشِ ماندن در تاریخچه را دارد. ‎localStorage‎ همگام است، پس
		// قبل از پیمایش نوشته می‌شود.
		row.addEventListener('click', function () {
			self.remember(self.input.value.trim());
		});

		/*
		 * جعبهٔ تصویر همیشه ساخته می‌شود، حتی بدونِ تصویر: در طرح، مربعِ
		 * خاکستری بخشی از ریتمِ ردیف است و نبودنش عنوان را به لبه
		 * می‌چسباند — یعنی محصولِ بی‌عکس ردیفی با چیدمانِ متفاوت می‌گرفت.
		 */
		if (item.thumbnail && item.thumbnail.url) {
			var img = document.createElement('img');

			img.className = 'zig-search__product-image';
			img.src = item.thumbnail.url;
			img.alt = item.thumbnail.alt || '';
			img.loading = 'lazy';
			img.decoding = 'async';
			row.appendChild(img);
		} else {
			var placeholder = document.createElement('span');

			placeholder.className = 'zig-search__product-image';
			placeholder.setAttribute('aria-hidden', 'true');
			row.appendChild(placeholder);
		}

		var body = document.createElement('span');

		body.className = 'zig-search__product-body';

		/*
		 * ‎<bdi>‎ نه تزئین است نه احتیاطِ اضافه: «میلینگ ماشین Elosdent E52»
		 * ترکیبِ فارسی و لاتین است و بدونِ ایزوله، الگوریتمِ دوجهتهٔ مرورگر
		 * تکهٔ لاتین را نسبت به متنِ اطرافش جابه‌جا می‌کند — عنوان درست
		 * ذخیره شده ولی غلط دیده می‌شود.
		 */
		var title = document.createElement('bdi');

		title.className = 'zig-search__product-title';
		title.textContent = item.title || '';
		body.appendChild(title);

		/*
		 * جداکننده یک دایرهٔ ۵ پیکسلی است، نه نویسهٔ «•» — پس به‌جای یک
		 * رشتهٔ به‌هم‌چسبیده، هر تکه ‎<span>‎ی خودش را می‌گیرد تا بشود
		 * جداگانه استایلش داد (و ترتیبِ راست‌به‌چپ هم صریح بماند: دسته
		 * سمتِ راست، برند سمتِ چپ، دقیقاً مثلِ طرح).
		 */
		var parts = [item.category, item.brand].filter(Boolean);

		if (parts.length) {
			var meta = document.createElement('span');

			meta.className = 'zig-search__product-meta';

			for (var p = 0; p < parts.length; p++) {
				if (p > 0) {
					var dot = document.createElement('span');

					dot.className = 'zig-search__product-meta-dot';
					dot.setAttribute('aria-hidden', 'true');
					meta.appendChild(dot);
				}

				var part = document.createElement('bdi');

				part.textContent = parts[p];
				meta.appendChild(part);
			}

			body.appendChild(meta);
		}

		row.appendChild(body);

		var chevron = document.createElement('span');

		chevron.className = 'zig-search__product-chevron';
		chevron.setAttribute('aria-hidden', 'true');
		this.cloneIconInto(chevron, this.chevronTpl);
		row.appendChild(chevron);

		return row;
	};

	Search.prototype.cloneIconInto = function (target, tpl) {
		if (tpl && tpl.content) {
			target.appendChild(tpl.content.cloneNode(true));
		}
	};

	Search.prototype.buildResultsUrl = function (query) {
		return this.resultsUrlTemplate.replace('zzzZIGQUERYzzz', window.encodeURIComponent(query));
	};

	/* ------------------------------------------------------------------
	 * جستجوهایِ اخیر — localStorage، فقط مرورگرِ خودِ کاربر
	 * ------------------------------------------------------------------ */

	Search.prototype.readRecent = function () {
		try {
			var raw = window.localStorage.getItem(this.recentKey);
			var list = raw ? JSON.parse(raw) : [];

			if (!window.Array.isArray(list)) {
				return [];
			}

			var now = Date.now();
			var alive = [];

			for (var i = 0; i < list.length; i++) {
				var entry = list[i];

				if (entry && 'string' === typeof entry.q && now - (entry.t || 0) < this.recentExpiryMs) {
					alive.push(entry);
				}
			}

			return alive;
		} catch (e) {
			// حالتِ خصوصی/localStorage خاموش: تاریخچه صرفاً نیست، نه خطا
			return [];
		}
	};

	Search.prototype.writeRecent = function (list) {
		try {
			window.localStorage.setItem(this.recentKey, JSON.stringify(list));
		} catch (e) {
			// جای نوشتن نیست (کوتا/حالتِ خصوصی) — بی‌صدا نادیده گرفته می‌شود
		}
	};

	/**
	 * تاریخچه فقط با یک intentِ واقعی نوشته می‌شود — Enter، انتخابِ یک
	 * نتیجه، یا «مشاهدهٔ نتایجِ بیشتر».
	 *
	 * قبلاً بعدِ *هر* پاسخِ موفق صدا زده می‌شد، یعنی تایپِ «می» → «میل» →
	 * «میلی» سه ردیفِ نیم‌کاره در تاریخچه می‌گذاشت و چیزی را که کاربر
	 * واقعاً جست‌وجو کرده بود بیرون می‌راند.
	 */
	Search.prototype.remember = function (query) {
		if (!this.recentEnabled || !query || query.length < this.minChars) {
			return;
		}

		var list = this.readRecent();

		list = list.filter(function (entry) {
			return entry.q !== query;
		});

		list.unshift({ q: query, t: Date.now() });
		list = list.slice(0, this.recentMax);

		this.writeRecent(list);
	};

	Search.prototype.clearRecent = function () {
		this.writeRecent([]);

		if (this.isOpen() && this.input.value.trim().length < this.minChars) {
			this.showIdle();
		}
	};

	/** @return {number} تعدادِ چیپِ رندرشده */
	Search.prototype.renderRecent = function () {
		if (!this.recentChips) {
			return 0;
		}

		var list = this.readRecent();

		this.recentChips.textContent = '';

		for (var i = 0; i < list.length; i++) {
			this.recentChips.appendChild(this.buildRecentChip(list[i].q));
		}

		return list.length;
	};

	Search.prototype.buildRecentChip = function (query) {
		var chip = document.createElement('a');

		chip.className = 'zig-search__chip zig-search__chip--recent';
		chip.href = this.resultsUrlTemplate ? this.buildResultsUrl(query) : '#';

		var label = document.createElement('bdi');

		label.textContent = query;
		chip.appendChild(label);

		// آیکون بعدِ متن — همان ترتیبی که چیپِ پرطرفدارِ سمتِ سرور دارد
		this.cloneIconInto(chip, this.recentIconTpl);

		return chip;
	};

	/* ======================================================================
	 * راه‌اندازی
	 * =================================================================== */

	function scan(scope) {
		var roots = (scope || document).querySelectorAll('[data-zig-search]');

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

	window.addEventListener('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
			return;
		}

		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-search.default',
			function ($scope) {
				scan($scope && $scope[0] ? $scope[0] : null);
			}
		);
	});
})();
