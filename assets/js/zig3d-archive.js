/*
 * آرشیو محصولات زیگ.
 *
 * این فایل هیچ چیزی *نمی‌سازد*. هرچه لازم است سرور رندر کرده: هر گزینهٔ
 * فیلتر، هر پیل ترتیب و هر شمارهٔ صفحه یک <a href> واقعی است که به آدرس
 * همان حالت اشاره می‌کند. کاری که اینجا می‌شود فقط این است که به‌جای
 * پیمایش، همان آدرس را با fetch بگیریم و قطعه‌ها را جا بیندازیم.
 *
 * نتیجه‌اش این است که اگر این فایل اصلاً لود نشود — خطای شبکه، افزونهٔ
 * مسدودکننده، اسکریپت دیگری که پیش از این ترکیده — صفحه هنوز کامل کار
 * می‌کند. فقط کندتر.
 *
 * یک قاعدهٔ دیگر هم در کل فایل برقرار است و از قرارداد سرور می‌آید:
 *
 *     if (!response.ok) → آلرت تلاش مجدد، بدون دست‌زدن به گرید
 *     else              → data.state رابط را می‌چرخاند، بدون هیچ آلرتی
 *
 * «هیچ محصولی با این فیلتر نیست» خطای فنی نیست. اگر با تایم‌اوت و ۵۰۰ و
 * نانسِ منقضی یک مسیر داشته باشد، کاربری که یک برند نایاب انتخاب کرده یک
 * آلرت قرمز با دکمهٔ «تلاش مجدد» می‌بیند و هرچه بزندش همان برمی‌گردد.
 */
(function () {
	'use strict';

	var STORE = 'zig-archive:';

	/**
	 * نسخهٔ قرارداد پاسخ.
	 *
	 * فایل JS با شمارهٔ نسخهٔ افزونه کش می‌شود، پس «اسکریپت قدیمی با سرور
	 * جدید» فقط در بازهٔ کوتاهِ استقرار پیش می‌آید — کافی برای اینکه یک
	 * کاربر نتیجهٔ عجیب ببیند و هیچ‌کس نفهمد چرا. با این عدد، همان حالت به
	 * مسیر «خطای فنی» می‌رود: گرید سر جایش می‌ماند و دکمهٔ تلاش مجدد بالا
	 * می‌آید، که بعد از رفرش خودش درست می‌شود.
	 */
	var CONTRACT = 1;

	/*
	 * مالکِ تاریخچه: اولین ویجت آرشیوِ صفحه.
	 *
	 * آدرس نوار یکی است و نمی‌تواند هم‌زمان وضعیت دو ویجت را بگوید. اگر هر
	 * دو pushState کنند، تاریخچه‌ای می‌سازند که هیچ‌کدام صاحبش نیستند و back
	 * کاربر را به حالتی می‌برد که هیچ‌وقت وجود نداشته. بقیه آژاکسشان کامل
	 * کار می‌کند، فقط آدرس را دست نمی‌زنند.
	 */
	var owned = false;

	function boot(root) {
		if (root.zigArchive) {
			return;
		}

		root.zigArchive = new Archive(root);
	}

	/* ======================================================================
	 * نمونه
	 * =================================================================== */

	function Archive(root) {
		this.root = root;
		this.main = root.querySelector('.zig-archive__main') || root;
		this.error = root.querySelector('.zig-archive__error');
		this.retry = root.querySelector('.zig-archive__retry');

		this.endpoint = root.getAttribute('data-zig-endpoint') || '';
		this.nonce = root.getAttribute('data-zig-nonce') || '';
		this.postId = root.getAttribute('data-zig-post') || '';
		this.widgetId = root.getAttribute('data-zig-widget') || '';
		this.termId = root.getAttribute('data-zig-term') || '0';

		this.debounce = parseInt(root.getAttribute('data-zig-debounce'), 10) || 0;
		this.scrollMax = parseInt(root.getAttribute('data-zig-scroll-max'), 10) || 0;
		this.restore = '1' === root.getAttribute('data-zig-restore');

		this.page = parseInt(root.getAttribute('data-zig-page'), 10) || 1;
		this.pages = parseInt(root.getAttribute('data-zig-pages'), 10) || 1;

		/*
		 * وضعیتِ کاری، جدا از href لینک‌ها.
		 *
		 * href هر لینک عکسِ لحظه‌ای رندری است که آن را ساخته. اگر کاربر دو
		 * فیلتر را سریع پشت سر هم بزند، href دومی هنوز اولی را نمی‌شناسد —
		 * و رفتن به آن یعنی فیلتر اول بی‌صدا برداشته شود. کاربر دو تیک
		 * می‌زند و یکی می‌گیرد، بدون هیچ خطایی.
		 *
		 * پس هر کلیک دلتای خودش را (data-zig-toggle / -sort / -goto) روی
		 * همین شیء اعمال می‌کند، و بعد از هر پاسخ، همین شیء با آدرسی که
		 * سرور برگردانده دوباره همگام می‌شود. یعنی کلاینت فقط بین دو پاسخ
		 * حدس می‌زند و حقیقت همیشه مالِ سرور می‌ماند.
		 */
		this.params = new window.URLSearchParams(window.location.search);

		this.owner = !owned;
		owned = true;

		/* شمارندهٔ درخواست: پاسخ کهنه نباید پاسخ تازه را پس بزند */
		this.ticket = 0;
		this.timer = null;
		this.lastFailed = null;
		this.autoLoaded = 0;

		if (!this.endpoint || !this.nonce || !window.fetch) {
			return;
		}

		this.bind();
		this.restoreState();
	}

	/* ======================================================================
	 * شنیدن
	 * =================================================================== */

	/*
	 * یک شنونده روی ریشه، نه یکی روی هر لینک.
	 *
	 * قطعه‌ها بعد از هر درخواست جایگزین می‌شوند و شنوندهٔ چسبیده به لینکِ
	 * قدیمی با خودِ لینک دور ریخته می‌شود. با واگذاری رویداد، لینکِ تازه
	 * هم بدون هیچ کار اضافه‌ای کار می‌کند.
	 */
	Archive.prototype.bind = function () {
		var self = this;

		this.root.addEventListener('click', function (event) {
			if (event.defaultPrevented || 0 !== event.button || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			var link = event.target.closest('a[href]');

			if (!link || !self.root.contains(link)) {
				return;
			}

			var kind = self.kindOf(link);

			if (!kind) {
				return;
			}

			event.preventDefault();
			self.go(self.applyDelta(link, kind), kind, link);
		});

		if (this.retry) {
			this.retry.addEventListener('click', function () {
				if (self.lastFailed) {
					self.go(self.lastFailed.query, self.lastFailed.kind, null, true);
				}
			});
		}

		/*
		 * فقط مالکِ تاریخچه به back گوش می‌دهد.
		 *
		 * با دو ویجت روی یک صفحه، هر دو popstate می‌گیرند و هر دو
		 * pushState می‌کنند؛ نتیجه‌اش یک تاریخچه است که هیچ‌کدام صاحبش
		 * نیستند و back کاربر را به حالتی می‌برد که هیچ‌وقت وجود نداشته.
		 */
		if (this.owner) {
			window.addEventListener('popstate', function () {
				self.params = new window.URLSearchParams(window.location.search);
				self.go(self.params.toString(), 'history', null, true);
			});
		}

		if (this.restore) {
			window.addEventListener('pagehide', function () {
				self.save();
			});
		}

		this.watchScroll();
	};

	/**
	 * این لینک مالِ ماست یا نه — و اگر هست، از چه جنسی.
	 *
	 * جنس مهم است چون رفتار بعد از پاسخ فرق می‌کند: فیلتر باید تدریجی و
	 * با تأخیر برود، صفحه‌بندی باید فوراً برود، و کلیک روی خودِ محصول اصلاً
	 * نباید گرفته شود.
	 */
	Archive.prototype.kindOf = function (link) {
		if (link.hasAttribute('data-zig-toggle') || link.hasAttribute('data-zig-clear')) {
			return 'filter';
		}

		if (link.hasAttribute('data-zig-sort')) {
			return 'sort';
		}

		if (link.hasAttribute('data-zig-goto')) {
			return 'page';
		}

		return '';
	};

	/**
	 * اعمال دلتای یک کلیک روی وضعیت کاری، و برگرداندن رشتهٔ پرس‌وجوی حاصل.
	 *
	 * قرارداد آدرس اینجا بازنویسی نمی‌شود — فقط سه عمل ساده انجام می‌شود:
	 * افزودن/برداشتن یک اسلاگ از یک فهرست کاماجدا، گذاشتن orderby، و
	 * گذاشتن paged. هرچه ظریف‌تر است (query_type_*، حذف پارامتر پیش‌فرض،
	 * ترتیب کلیدها) مالِ سرور می‌ماند و با آدرسی که در پاسخ برمی‌گردد به
	 * همین‌جا برمی‌گردد.
	 */
	Archive.prototype.applyDelta = function (link, kind) {
		if (link.hasAttribute('data-zig-clear')) {
			this.clearFilters();
		} else if ('filter' === kind) {
			var delta = (link.getAttribute('data-zig-toggle') || '').split('|');

			if (2 === delta.length && delta[0]) {
				this.toggle(delta[0], delta[1]);
			}
		} else if ('sort' === kind) {
			var sort = link.getAttribute('data-zig-sort') || '';

			if (sort) {
				this.params.set('orderby', sort);
			} else {
				this.params.delete('orderby');
			}
		}

		/*
		 * هر تغییر فیلتر یا ترتیب، صفحه‌بندی را از اول شروع می‌کند — همان
		 * قاعده‌ای که Query_State::toggle() هم دارد. ماندن روی صفحهٔ ۷ بعد
		 * از باریک‌کردن نتیجه به دو صفحه، یعنی صفحهٔ خالی.
		 */
		if ('page' === kind) {
			this.params.set('paged', link.getAttribute('data-zig-goto') || '1');
		} else {
			this.params.delete('paged');
		}

		return this.params.toString();
	};

	Archive.prototype.toggle = function (param, slug) {
		var terms = (this.params.get(param) || '').split(',').filter(Boolean);
		var at = terms.indexOf(slug);

		if (at < 0) {
			terms.push(slug);
		} else {
			terms.splice(at, 1);
		}

		if (terms.length) {
			this.params.set(param, terms.join(','));
		} else {
			this.params.delete(param);
			this.params.delete(param.replace(/^filter_/, 'query_type_'));
		}
	};

	Archive.prototype.clearFilters = function () {
		var doomed = [];

		this.params.forEach(function (value, key) {
			if (0 === key.indexOf('filter_') || 0 === key.indexOf('query_type_')) {
				doomed.push(key);
			}
		});

		for (var i = 0; i < doomed.length; i++) {
			this.params.delete(doomed[i]);
		}
	};

	/* ======================================================================
	 * رفتن به یک حالت
	 * =================================================================== */

	Archive.prototype.go = function (query, kind, link, immediate) {
		var self = this;

		/*
		 * فیلتر تأخیر می‌گیرد، بقیه نه.
		 *
		 * کاربری که سه برند را پشت سر هم تیک می‌زند، سه درخواست نمی‌خواهد؛
		 * یکی می‌خواهد با هر سه. ولی کلیک روی «صفحهٔ ۲» یک تصمیم تمام‌شده
		 * است و تأخیرش فقط حس کندی می‌دهد.
		 */
		if ('filter' === kind && this.debounce > 0 && !immediate) {
			if (link) {
				this.preview(link);
			}

			window.clearTimeout(this.timer);

			this.timer = window.setTimeout(function () {
				self.fetch(query, kind);
			}, this.debounce);

			return;
		}

		this.fetch(query, kind);
	};

	/**
	 * بازخورد فوری روی خودِ گزینه، قبل از رسیدن پاسخ.
	 *
	 * بدون این، بین کلیک و پاسخ هیچ اتفاقی نمی‌افتد و کاربر دوباره کلیک
	 * می‌کند — که یعنی فیلتر را روشن و دوباره خاموش کرده.
	 *
	 * فقط ظاهر عوض می‌شود، نه وضعیت واقعی: پاسخ سرور سایدبار را کامل
	 * جایگزین می‌کند و حقیقت همان است.
	 */
	Archive.prototype.preview = function (link) {
		var item = link.closest('.zig-facet__item');

		if (item) {
			item.classList.toggle('is-selected');
		}
	};

	Archive.prototype.fetch = function (query, kind) {
		var self = this;
		var ticket = ++this.ticket;

		this.busy(true);

		var body = new window.FormData();

		body.append('action', 'zig3d_archive');
		body.append('nonce', this.nonce);
		body.append('post_id', this.postId);
		body.append('widget_id', this.widgetId);
		body.append('term_id', this.termId);
		body.append('query', query);
		body.append('contract', String(CONTRACT));

		window.fetch(this.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (response) {
			/*
			 * تنها تمایز. هر چیزی که ۲xx نباشد — و هر چیزی که اصلاً
			 * نرسد — خطای فنی است و از همین‌جا به مسیر آلرت می‌رود.
			 */
			if (!response.ok) {
				throw new Error(String(response.status));
			}

			return response.json();
		}).then(function (payload) {
			if (ticket !== self.ticket) {
				/*
				 * پاسخ کهنه. کاربر بین این درخواست و حالا چیز دیگری
				 * کلیک کرده و نشاندن این نتیجه یعنی برگرداندنش به
				 * حالتی که خودش رد کرده.
				 */
				return;
			}

			/*
			 * ‎response.ok‎ فقط می‌گوید چیزی رسید. اینکه آن چیز *پاکتِ ما*
			 * باشد، سنجش جداگانه‌ای است: پاسخ ۲۰۰ از یک کش، یک صفحهٔ
			 * لاگین، یا سروری که وسط استقرار است، همه ‎ok‎ هستند.
			 *
			 * هر کدام که نخواند، خطای فنی است — یعنی گرید دست‌نخورده
			 * می‌ماند و تلاش مجدد بالا می‌آید، نه اینکه بی‌صدا هیچ اتفاقی
			 * نیفتد.
			 */
			if (!payload || true !== payload.success || !payload.data) {
				throw new Error('shape');
			}

			if (CONTRACT !== payload.data.contract) {
				throw new Error('contract');
			}

			if ('string' !== typeof payload.data.state) {
				throw new Error('state');
			}

			self.apply(payload.data, kind);
		}).catch(function () {
			if (ticket !== self.ticket) {
				return;
			}

			self.fail(query, kind);
		});
	};

	/* ======================================================================
	 * نشاندن پاسخ
	 * =================================================================== */

	Archive.prototype.apply = function (data, kind) {
		this.hideError();
		this.busy(false);

		this.swap('grid', data.grid);
		this.swap('pagination', data.pagination);
		this.swap('facets', data.facets);
		this.swap('count', data.count);

		this.page = data.page || 1;
		this.pages = data.pages || 0;

		this.root.setAttribute('data-zig-state', data.state || 'ok');
		this.root.setAttribute('data-zig-page', String(this.page));
		this.root.setAttribute('data-zig-pages', String(Math.max(1, this.pages)));

		/*
		 * آدرس از سرور می‌آید، نه از لینکی که کلیک شده.
		 *
		 * سرور همان چیزی را برمی‌گرداند که اگر کاربر رفرش کند دقیقاً همین
		 * صفحه را می‌دهد — با query_type صریح و بدون پارامتر بی‌اثر. ساختن
		 * آن در کلاینت یعنی قرارداد آدرس دو جا تعریف شود.
		 */
		if (data.url) {
			/*
			 * همگام‌سازی وضعیت کاری با حقیقت.
			 *
			 * حدس‌های کلاینت بین دو پاسخ اینجا پاک می‌شوند: query_type_*
			 * صریح، پارامتر بی‌اثرِ برداشته‌شده، ترتیب قطعی کلیدها. یعنی
			 * قرارداد آدرس یک تعریف بیشتر ندارد و آن هم در PHP است.
			 */
			try {
				this.params = new window.URLSearchParams(new window.URL(data.url, window.location.href).search);
			} catch (error) {
				/* آدرس بدشکل: وضعیت کاری دست‌نخورده می‌ماند */
			}

			if (this.owner && 'history' !== kind) {
				window.history.pushState({ zig: true }, '', data.url);
			}
		}

		this.focusAfter(kind);
	};

	/**
	 * جایگزینی یک قطعه.
	 *
	 * کلیدِ نبوده یعنی «دست نزن» — نه «خالی کن». یک کلیک صفحه‌بندی سایدبار
	 * را عوض نمی‌کند و جایگزین‌کردنش فقط فوکوس را می‌پراند.
	 */
	Archive.prototype.swap = function (name, html) {
		if ('string' !== typeof html) {
			return;
		}

		var slot = this.root.querySelector('[data-zig-part="' + name + '"]');

		if (!slot) {
			return;
		}

		slot.innerHTML = html;

		this.reinit(slot);
	};

	/**
	 * بیدارکردن دوبارهٔ ویجت‌های المنتور داخل قطعهٔ تازه.
	 *
	 * کارت می‌تواند یک قالب المنتور یا آیتم جت‌انجین باشد (منبع کارت =
	 * «قالب»)، و حالت «چیزی پیدا نشد» هم می‌تواند قالب باشد. آن قالب‌ها
	 * ویجت‌هایی دارند که هندلر جاوااسکریپت لازم دارند — اسلایدر، آکاردئون،
	 * شمارنده.
	 *
	 * ‎innerHTML‎ فقط مارک‌آپ را می‌گذارد؛ هندلرهای المنتور هیچ‌وقت روی آن
	 * اجرا نمی‌شوند. نتیجه‌اش این است که کارت‌ها *بار اول* درست کار می‌کنند
	 * و بعد از اولین فیلتر، مرده به نظر می‌رسند — و چون هیچ خطایی نمی‌دهد،
	 * معمولاً به «قالب خراب است» تعبیر می‌شود.
	 */
	Archive.prototype.reinit = function (scope) {
		var frontend = window.elementorFrontend;

		if (!frontend || !frontend.elementsHandler || !window.jQuery) {
			return;
		}

		var elements = scope.querySelectorAll('.elementor-element');

		for (var i = 0; i < elements.length; i++) {
			try {
				frontend.elementsHandler.runReadyTrigger(window.jQuery(elements[i]));
			} catch (error) {
				/* هندلر یک ویجت نباید بقیهٔ گرید را با خودش ببرد */
			}
		}
	};

	/*
	 * aria-busy روی ریشه: هم CSS به آن وصل است و هم صفحه‌خوان می‌فهمدش.
	 * یک کلاسِ فقط-تزئینی همان کار را برای چشم می‌کرد و برای گوش هیچ.
	 */
	Archive.prototype.busy = function (on) {
		if (on) {
			this.root.setAttribute('aria-busy', 'true');
		} else {
			this.root.removeAttribute('aria-busy');
		}
	};

	/* ======================================================================
	 * فوکوس
	 * =================================================================== */

	/**
	 * بعد از جایگزینی، فوکوس کجا برود.
	 *
	 * این تنها بخشی است که بدون آن، ویجت برای کاربر کیبورد *بدتر* از حالت
	 * بدون جاوااسکریپت می‌شود: در پیمایش واقعی، مرورگر فوکوس را به بالای
	 * سند می‌برد و کاربر می‌داند کجاست. اینجا اگر کاری نکنیم، عنصری که
	 * فوکوس داشت از DOM حذف شده و فوکوس به <body> می‌افتد — یعنی کاربر با
	 * Tab باید از اول کل صفحه را رد کند.
	 *
	 * فیلتر استثناست: سایدبار جایگزین شده ولی کاربر همان‌جا مانده و
	 * می‌خواهد فیلتر بعدی را بزند. آنجا فوکوس به گزینهٔ هم‌نام برمی‌گردد.
	 */
	Archive.prototype.focusAfter = function (kind) {
		if ('history' === kind) {
			return;
		}

		var target = this.root.querySelector('[data-zig-part="grid"]');

		if (!target) {
			return;
		}

		/*
		 * tabindex="-1" تا عنصری که ذاتاً فوکوس‌پذیر نیست بتواند فوکوس
		 * بگیرد، ولی از ترتیب Tab بیرون بماند.
		 */
		target.setAttribute('tabindex', '-1');
		target.focus({ preventScroll: true });

		if ('page' === kind) {
			target.scrollIntoView({ block: 'start', behavior: this.motion() });
		}
	};

	/** حرکت، مگر اینکه کاربر گفته باشد نه */
	Archive.prototype.motion = function () {
		var query = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

		return query && query.matches ? 'auto' : 'smooth';
	};

	/* ======================================================================
	 * خطای فنی
	 * =================================================================== */

	Archive.prototype.fail = function (query, kind) {
		this.busy(false);
		this.lastFailed = { query: query, kind: kind };

		if (this.error) {
			this.error.hidden = false;
		}

		/*
		 * گرید دست‌نخورده می‌ماند.
		 *
		 * خالی‌کردنش یعنی کاربر هم نتیجه‌اش را از دست بدهد و هم خطا را
		 * ببیند — دو باخت برای یک مشکل که ممکن است یک قطعی یک‌ثانیه‌ای
		 * شبکه باشد.
		 */
	};

	Archive.prototype.hideError = function () {
		this.lastFailed = null;

		if (this.error) {
			this.error.hidden = true;
		}
	};

	/* ======================================================================
	 * اسکرول خودکار
	 * =================================================================== */

	/**
	 * چند صفحه خودکار، بعد صفحه‌بندی صریح.
	 *
	 * اسکرول بی‌پایان دو چیز را خراب می‌کند: فوتر هیچ‌وقت در دسترس نیست، و
	 * کاربر نمی‌تواند به «صفحهٔ ۷» برگردد چون صفحه‌ای وجود ندارد. سقف
	 * گذاشتن هر دو را حل می‌کند و راحتی چند صفحهٔ اول را هم نگه می‌دارد.
	 *
	 * صفحه‌بندی صریح همیشه در DOM هست — حتی وقتی این روشن است — تا بدون
	 * جاوااسکریپت و برای خزنده راهی به صفحهٔ بعد بماند.
	 */
	Archive.prototype.watchScroll = function () {
		if (this.scrollMax < 1 || !window.IntersectionObserver) {
			return;
		}

		var self = this;

		this.sentinel = document.createElement('div');
		this.sentinel.setAttribute('aria-hidden', 'true');
		this.main.appendChild(this.sentinel);

		this.observer = new window.IntersectionObserver(function (entries) {
			if (!entries[0] || !entries[0].isIntersecting) {
				return;
			}

			self.autoNext();
		}, { rootMargin: '200px' });

		this.observer.observe(this.sentinel);
	};

	Archive.prototype.autoNext = function () {
		if (this.autoLoaded >= this.scrollMax || this.page >= this.pages || this.root.hasAttribute('aria-busy')) {
			return;
		}

		var next = this.root.querySelector('.zig-page--next');

		if (!next) {
			return;
		}

		this.autoLoaded++;
		this.go(this.applyDelta(next, 'page'), 'page', null, true);
	};

	/* ======================================================================
	 * برگشتن از صفحهٔ محصول
	 * =================================================================== */

	/*
	 * کاربر صفحهٔ ۴ را باز کرده، روی محصول کلیک کرده، و back زده. بدون
	 * این، به صفحهٔ ۴ برمی‌گردد ولی سرِ فهرست — یعنی باید دوباره تا همان
	 * محصول اسکرول کند.
	 *
	 * sessionStorage و نه localStorage: این وضعیتِ همین تبِ همین جلسه است
	 * و ماندنش تا هفتهٔ بعد فقط سردرگمی است.
	 */
	Archive.prototype.save = function () {
		try {
			window.sessionStorage.setItem(STORE + this.key(), String(window.scrollY));
		} catch (error) {
			/* حالت خصوصی مرورگر یا سهمیهٔ پر — بازگردانی یک راحتی است، نه یک قابلیت */
		}
	};

	Archive.prototype.restoreState = function () {
		if (!this.restore) {
			return;
		}

		var saved = null;

		try {
			saved = window.sessionStorage.getItem(STORE + this.key());
			window.sessionStorage.removeItem(STORE + this.key());
		} catch (error) {
			return;
		}

		if (null === saved) {
			return;
		}

		var top = parseInt(saved, 10);

		if (top > 0) {
			window.scrollTo({ top: top, behavior: 'auto' });
		}
	};

	/** کلید ذخیره: همین ویجت روی همین آدرس */
	Archive.prototype.key = function () {
		return this.widgetId + '|' + window.location.pathname + window.location.search;
	};

	/* ======================================================================
	 * راه‌اندازی
	 * =================================================================== */

	function scan(scope) {
		var roots = (scope || document).querySelectorAll('[data-zig-archive]');

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
	 * بدون این، طراح بعد از هر تغییر تنظیمات یک ویجت مرده می‌بیند و فکر
	 * می‌کند چیزی خراب است.
	 */
	window.addEventListener('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
			return;
		}

		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-product-archive.default',
			function ($scope) {
				scan($scope && $scope[0] ? $scope[0] : null);
			}
		);
	});
})();
