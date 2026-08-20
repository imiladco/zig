/*
 * نوارِ متایِ پست — فقط دکمهٔ لایک کاری از جنسِ اسکریپت لازم دارد.
 *
 * زمانِ مطالعه و شمارشِ دیدگاه کاملاً از PHP رندر می‌شوند (بدونِ این
 * فایل هم درست‌اند). این‌جا فقط سوییچِ لایک است: کلیک → یک POST → عوضِ
 * شمارش/کلاس/aria بر اساسِ پاسخِ سرور، نه حدسِ خوش‌بینانهٔ سمتِ کلاینت —
 * چون منبعِ واقعیِ حقیقت (لیستِ توکن‌ها) رویِ سرور است.
 *
 * REST اول، admin-ajax فقط برایِ شکستِ مسیر/امنیت/شبکه — همان معماریِ
 * ‎zig3d-search.js‎.
 */
(function () {
	'use strict';

	var FALLBACK_STATUSES = [404, 401, 403];

	function boot(root) {
		if (root.zigPostMeta) {
			return;
		}

		root.zigPostMeta = new PostMeta(root);
	}

	function PostMeta(root) {
		this.root = root;
		this.button = root.querySelector('.zig-post-meta__like');
		this.restUrl = root.getAttribute('data-rest-url') || '';
		this.ajaxUrl = root.getAttribute('data-ajax-url') || '';
		this.ajaxAction = root.getAttribute('data-ajax-action') || 'zig3d_like_toggle';
		this.useFallback = false;
		this.busy = false;

		if (!this.button) {
			return;
		}

		this.count = this.button.querySelector('.zig-post-meta__count');
		this.postId = this.button.getAttribute('data-post-id') || '0';
		this.labelOff = this.button.getAttribute('data-label-off') || '';
		this.labelOn = this.button.getAttribute('data-label-on') || '';

		this.button.addEventListener('click', this.onClick.bind(this));
	}

	PostMeta.prototype.onClick = function () {
		if (this.busy || !this.postId || '0' === this.postId) {
			return;
		}

		this.busy = true;
		this.button.setAttribute('aria-busy', 'true');

		this.request(false)
			.then(this.applyResult.bind(this))
			.catch(this.onError.bind(this));
	};

	PostMeta.prototype.onError = function (error) {
		var self = this;

		var status = error && error.status;
		var isFallbackWorthy = !this.useFallback && (!status || FALLBACK_STATUSES.indexOf(status) > -1);

		if (isFallbackWorthy) {
			this.useFallback = true;

			this.request(true)
				.then(function (result) {
					self.applyResult(result);
				})
				.catch(function () {
					self.finish();
				});

			return;
		}

		this.finish();
	};

	/*
	 * یک تلاشِ فچ — یا REST یا admin-ajax، بسته به ‎useAjax‎. هیچ نانسی
	 * فرستاده نمی‌شود (نگاه کنید به داک‌بلاکِ ‎Likes_Endpoint‎ برایِ دلیلش)؛
	 * فقط کوکیِ خودِ مرورگر با ‎credentials: same-origin‎ همراه می‌رود تا
	 * سرور توکنِ ناشناسِ همین بازدیدکننده را بشناسد.
	 */
	PostMeta.prototype.request = function (useAjax) {
		var url;
		var options = { method: 'POST', credentials: 'same-origin' };

		if (useAjax) {
			var body = new window.FormData();

			body.append('action', this.ajaxAction);
			body.append('post_id', this.postId);

			url = this.ajaxUrl;
			options.body = body;
		} else {
			url = this.restUrl.replace(/\/$/, '') + '/' + encodeURIComponent(this.postId);
		}

		return window.fetch(url, options).then(function (response) {
			if (!response.ok) {
				var err = new Error(String(response.status));
				err.status = response.status;

				throw err;
			}

			return response.json();
		}).then(function (payload) {
			// خروجیِ درِ admin-ajax در ‎data‎ پیچیده می‌شود، خروجیِ REST نه.
			return payload && 'object' === typeof payload.data ? payload.data : payload;
		});
	};

	PostMeta.prototype.applyResult = function (result) {
		if (result && 'boolean' === typeof result.liked) {
			this.button.classList.toggle('is-liked', result.liked);
			this.button.setAttribute('aria-pressed', result.liked ? 'true' : 'false');

			var label = result.liked ? this.labelOn : this.labelOff;

			if (label) {
				this.button.setAttribute('aria-label', label);
			} else {
				this.button.removeAttribute('aria-label');
			}
		}

		if (this.count && result && 'number' === typeof result.count) {
			this.count.textContent = String(result.count);
		}

		this.finish();
	};

	PostMeta.prototype.finish = function () {
		this.busy = false;
		this.button.removeAttribute('aria-busy');
	};

	/* ======================================================================
	 * راه‌اندازی
	 * =================================================================== */

	function scan(scope) {
		var roots = (scope || document).querySelectorAll('[data-zig-post-meta]');

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
			'frontend/element_ready/zig3d-post-meta.default',
			function ($scope) {
				scan($scope && $scope[0] ? $scope[0] : null);
			}
		);
	});
})();
