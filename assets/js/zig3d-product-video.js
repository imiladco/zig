/**
 * کنترلر کوچک گالری: یک ویدئوی واقعی، انتخاب delegated کارت‌های button،
 * و یک مسیر واحد برای همگام‌سازی رسانه، حالت جاری و Current Info. صفِ
 * سراسریِ StartupBuffer پس از window.load فقط بازهٔ آغازین هر URL را با
 * یک video موقت و reusable آماده می‌کند و مزاحم پخش صریح کاربر نمی‌شود.
 */
(function () {
	'use strict';

	var StartupBuffer = (function () {
		var TARGET_SECONDS = 10;
		var START_DELAY_MS = 1000;
		var CANDIDATE_TIMEOUT_MS = 20000;
		var queue = [];
		var known = Object.create(null);
		var ready = Object.create(null);
		var playing = [];
		var preloader = null;
		var current = null;
		var candidateTimer = null;
		var startTimer = null;
		var resumeTimer = null;
		var loadComplete = ('complete' === document.readyState);

		function saveDataEnabled() {
			return !!(navigator.connection && navigator.connection.saveData);
		}

		function register(candidates) {
			if (saveDataEnabled()) {
				return;
			}

			candidates.forEach(function (candidate) {
				if (candidate.src && !known[candidate.src]) {
					known[candidate.src] = true;
					queue.push(candidate);
				}
			});

			scheduleStart();
		}

		function scheduleStart() {
			if (!loadComplete || startTimer || current || !queue.length || playing.length) {
				return;
			}

			startTimer = window.setTimeout(function () {
				startTimer = null;
				pump();
			}, START_DELAY_MS);
		}

		function ensurePreloader() {
			if (!preloader) {
				preloader = document.createElement('video');
				preloader.preload = 'auto';
				preloader.muted = true;
				preloader.playsInline = true;
			}

			return preloader;
		}

		function bufferedFromStart(media, target) {
			for (var i = 0; i < media.buffered.length; i++) {
				if (media.buffered.start(i) <= 0.25 && media.buffered.end(i) >= target - 0.25) {
					return true;
				}
			}

			return false;
		}

		function inspectCandidate() {
			if (!current || !preloader) {
				return;
			}

			var duration = Number.isFinite(preloader.duration) ? preloader.duration : null;
			var target = null === duration ? TARGET_SECONDS : Math.min(TARGET_SECONDS, duration);

			if (target > 0 && bufferedFromStart(preloader, target)) {
				finishCandidate(true);
			}
		}

		function pump() {
			if (saveDataEnabled()) {
				queue = [];
				destroyPreloader();
				return;
			}

			if (current || playing.length || resumeTimer || !loadComplete) {
				return;
			}

			if (!queue.length) {
				destroyPreloader();
				return;
			}

			current = queue.shift();
			var media = ensurePreloader();
			media.addEventListener('progress', inspectCandidate);
			media.addEventListener('durationchange', inspectCandidate);
			media.addEventListener('loadedmetadata', inspectCandidate);
			media.addEventListener('error', failCandidate);
			media.src = current.src;
			media.load();

			candidateTimer = window.setTimeout(failCandidate, CANDIDATE_TIMEOUT_MS);
		}

		function detachCandidate() {
			if (candidateTimer) {
				window.clearTimeout(candidateTimer);
				candidateTimer = null;
			}

			if (preloader) {
				preloader.removeEventListener('progress', inspectCandidate);
				preloader.removeEventListener('durationchange', inspectCandidate);
				preloader.removeEventListener('loadedmetadata', inspectCandidate);
				preloader.removeEventListener('error', failCandidate);
				preloader.pause();
				preloader.removeAttribute('src');
				preloader.load();
			}
		}

		function finishCandidate(isReady) {
			if (isReady && current) {
				ready[current.src] = true;
			}
			detachCandidate();
			current = null;
			pump();
		}

		function failCandidate() {
			finishCandidate(false);
		}

		function yieldCurrent(requeue) {
			if (!current) {
				return;
			}

			var interrupted = current;
			detachCandidate();
			current = null;
			if (requeue) {
				queue.unshift(interrupted);
			}
		}

		function prioritize(src) {
			yieldCurrent(true);
			queue = queue.filter(function (candidate) {
				return candidate.src !== src;
			});
			if (src && !ready[src]) {
				queue.unshift({ src: src });
			}
			if (resumeTimer) {
				window.clearTimeout(resumeTimer);
			}
			resumeTimer = window.setTimeout(function () {
				resumeTimer = null;
				pump();
			}, 1200);
		}

		function playbackStarted(video) {
			if (-1 === playing.indexOf(video)) {
				playing.push(video);
			}
			yieldCurrent(true);
		}

		function playbackStopped(video) {
			playing = playing.filter(function (item) {
				return item !== video;
			});
			pump();
		}

		function destroyPreloader() {
			detachCandidate();
			preloader = null;
		}

		window.addEventListener('load', function () {
			loadComplete = true;
			scheduleStart();
		}, { once: true });

		return {
			register: register,
			prioritize: prioritize,
			playbackStarted: playbackStarted,
			playbackStopped: playbackStopped
		};
	})();

	function VideoGallery(root) {
		this.root = root;
		this.player = root.querySelector(':scope > .zig-product-video__player');
		this.video = this.player ? this.player.querySelector(':scope > .zig-product-video__video') : null;
		this.playBtn = this.player ? this.player.querySelector(':scope > .zig-product-video__play') : null;
		this.durationEl = this.player ? this.player.querySelector(':scope > .zig-product-video__duration') : null;
		this.detailsEl = root.querySelector(':scope > [data-zig-video-details]');
		this.titleEl = this.detailsEl ? this.detailsEl.querySelector('[data-zig-video-title]') : null;
		this.descEl = this.detailsEl ? this.detailsEl.querySelector('[data-zig-video-desc]') : null;
		this.list = root.querySelector(':scope > .zig-product-video__sidebar > .zig-product-video__list');
		this.cards = this.list ? Array.prototype.slice.call(this.list.querySelectorAll(':scope > .zig-product-video__card')) : [];

		if (!this.video) {
			return;
		}

		this.bind();
		StartupBuffer.register(this.candidates());
	}

	VideoGallery.prototype.candidates = function () {
		var active = this.root.querySelector('.zig-product-video__card.is-active');
		var ordered = [];
		var add = function (src) {
			if (src && !ordered.some(function (item) { return item.src === src; })) {
				ordered.push({ src: src });
			}
		};

		add(active ? active.getAttribute('data-src') : this.video.currentSrc || this.video.getAttribute('src'));
		this.cards.forEach(function (card) {
			add(card.getAttribute('data-src'));
		});

		return ordered;
	};

	VideoGallery.prototype.bind = function () {
		var self = this;

		if (this.list) {
			this.list.addEventListener('click', function (e) {
				var card = e.target.closest('.zig-product-video__card');
				if (card && self.list.contains(card)) {
					self.setActive(card);
				}
			});
		}

		if (this.playBtn) {
			this.playBtn.addEventListener('click', function () {
				StartupBuffer.playbackStarted(self.video);
				var playback = self.video.play();
				if (playback && 'function' === typeof playback.catch) {
					playback.catch(function () {
						StartupBuffer.playbackStopped(self.video);
					});
				}
			});
		}

		this.video.addEventListener('play', function () {
			StartupBuffer.playbackStarted(self.video);
			if (self.player) {
				self.player.classList.add('has-started');
			}
		});

		this.video.addEventListener('ended', function () {
			StartupBuffer.playbackStopped(self.video);
			if (self.player) {
				self.player.classList.remove('has-started');
			}
		});

		this.video.addEventListener('pause', function () {
			StartupBuffer.playbackStopped(self.video);
		});
	};

	/**
	 * کارتِ کلیک‌شده را فعال می‌کند: کلاسِ ‎is-active‎ و ‎aria-current‎
	 * رویِ کارت‌ها جابه‌جا می‌شود، پلیر با src/poster/duration همان آیتم
	 * به‌روز و pause/reset می‌شود — طبقِ رفتارِ خواسته‌شده، سوییچِ کارت
	 * هرگز خودکار پخش نمی‌کند.
	 */
	VideoGallery.prototype.setActive = function (card) {
		if (!card || card.classList.contains('is-active')) {
			return;
		}

		StartupBuffer.prioritize(card.getAttribute('data-src') || '');

		this.cards.forEach(function (c) {
			var isActive = (c === card);
			c.classList.toggle('is-active', isActive);
			if (isActive) {
				c.setAttribute('aria-current', 'true');
			} else {
				c.removeAttribute('aria-current');
			}
		});

		var src = card.getAttribute('data-src') || '';
		var poster = card.getAttribute('data-poster') || '';
		var duration = card.getAttribute('data-duration') || '';
		var titleEl = card.querySelector('.zig-product-video__title');
		var descEl = card.querySelector('.zig-product-video__description');
		var title = titleEl ? titleEl.textContent.trim() : '';
		var desc = descEl ? descEl.textContent.trim() : '';

		this.video.pause();
		this.video.src = src;
		this.video.poster = poster;
		this.video.load();

		if (this.player) {
			this.player.classList.remove('has-started');
		}

		if (this.durationEl) {
			if (duration) {
				this.durationEl.hidden = false;
				this.durationEl.textContent = duration;
			} else {
				this.durationEl.hidden = true;
			}
		}

		if (this.titleEl) {
			this.titleEl.textContent = title;
			this.titleEl.hidden = !title;
		}

		if (this.descEl) {
			this.descEl.textContent = desc;
			this.descEl.hidden = !desc;
		}

		if (this.detailsEl) {
			this.detailsEl.hidden = !title && !desc;
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
