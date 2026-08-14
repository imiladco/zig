/**
 * گالریِ ویدئویِ محصول.
 *
 * بدونِ این اسکریپت، اولین ویدئو (همان کارتِ معرفی) کاملاً قابل‌پخش است —
 * ‎<video>‎ی HTMLِ بومی، بدونِ ‎controls‎، فقط نمایشِ posterش دیده می‌شود؛
 * فایل فقط این‌ها را رویش سوار می‌کند: سوییچِ کارت‌ها (تعویضِ src/poster/
 * duration پلیر)، دکمهٔ پخشِ سفارشیِ وسطِ پلیر، و ظاهرشدنِ خودکارِ دوبارهٔ
 * همان دکمه وقتی ویدئو pause/ended می‌شود.
 *
 * چرا یک listenerِ delegated رویِ خودِ لیست، نه یکی به‌ازایِ هر کارت: با
 * تعدادِ کمِ کارت‌ها فرقِ کارایی محسوس نیست، ولی این الگو دقیقاً همانی
 * است که در بقیهٔ ویجت‌هایِ این افزونه (تب‌هایِ نمایشِ قابلیت‌ها) هم به کار
 * رفته — یک قاعده برایِ همه‌جا.
 */
(function () {
	'use strict';

	function VideoGallery(root) {
		this.root = root;
		this.player = root.querySelector(':scope > .zig-product-video__player');
		this.video = this.player ? this.player.querySelector(':scope > .zig-product-video__video') : null;
		this.playBtn = this.player ? this.player.querySelector(':scope > .zig-product-video__play') : null;
		this.durationEl = this.player ? this.player.querySelector(':scope > .zig-product-video__duration') : null;
		this.list = root.querySelector(':scope > .zig-product-video__sidebar > .zig-product-video__list');
		this.cards = this.list ? Array.prototype.slice.call(this.list.querySelectorAll(':scope > .zig-product-video__card')) : [];

		if (!this.video || !this.cards.length) {
			return;
		}

		this.bind();
	}

	VideoGallery.prototype.bind = function () {
		var self = this;

		this.list.addEventListener('click', function (e) {
			var card = e.target.closest('.zig-product-video__card');
			if (card) {
				self.activate(card);
			}
		});

		if (this.playBtn) {
			this.playBtn.addEventListener('click', function () {
				self.video.play();
			});
		}

		this.video.addEventListener('play', function () {
			if (self.player) {
				self.player.classList.add('is-playing');
			}
		});

		/*
		 * ‎pause‎ هم موقعِ مکث دستی پیش می‌آید هم درست قبل از ‎ended‎ — پس
		 * یک listenerِ مشترک برایِ هر دو کافی است؛ دکمهٔ پخش باید در هر دو
		 * حالت دوباره ظاهر شود.
		 */
		['pause', 'ended'].forEach(function (evt) {
			self.video.addEventListener(evt, function () {
				if (self.player) {
					self.player.classList.remove('is-playing');
				}
			});
		});
	};

	/**
	 * کارتِ کلیک‌شده را فعال می‌کند: کلاسِ ‎is-active‎ روی خودِ کارت‌ها
	 * جابه‌جا می‌شود، پلیر با src/poster/duration همان آیتم به‌روز و
	 * pause/reset می‌شود — طبقِ رفتارِ خواسته‌شده، سوییچِ کارت هرگز خودکار
	 * پخش نمی‌کند.
	 */
	VideoGallery.prototype.activate = function (card) {
		if (card.classList.contains('is-active')) {
			return;
		}

		this.cards.forEach(function (c) {
			var isActive = (c === card);
			c.classList.toggle('is-active', isActive);
			c.setAttribute('aria-pressed', isActive ? 'true' : 'false');
		});

		var src = card.getAttribute('data-src') || '';
		var poster = card.getAttribute('data-poster') || '';
		var duration = card.getAttribute('data-duration') || '';

		this.video.pause();
		this.video.src = src;
		this.video.poster = poster;
		this.video.load();

		if (this.player) {
			this.player.classList.remove('is-playing');
		}

		if (this.durationEl) {
			if (duration) {
				this.durationEl.hidden = false;
				this.durationEl.textContent = duration;
			} else {
				this.durationEl.hidden = true;
			}
		}
	};

	function setup(root) {
		if (root.__zigProductVideo) {
			return;
		}
		root.__zigProductVideo = true;

		new VideoGallery(root);
	}

	function initAll(scope) {
		(scope || document).querySelectorAll('.zig-product-video').forEach(setup);
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-product-video-gallery.default',
			function ($el) {
				initAll($el && $el[0] ? $el[0] : document);
			}
		);
	}

	if ('loading' !== document.readyState) {
		initAll(document);
	} else {
		document.addEventListener('DOMContentLoaded', function () {
			initAll(document);
		});
	}
})();
