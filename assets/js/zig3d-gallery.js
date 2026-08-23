/**
 * مودال گالری محصول زیگ.
 *
 * پورتِ مستقیمِ ‎assets/js/gallery-modal.js‎ از افزونهٔ
 * almasara-elementor-widgets — همان منطق، فقط با کلاس‌های ‎zig-gallery*‎
 * به‌جای ‎amw-pg*‎ و ‎window.Zig3dModal‎ به‌جای ‎window.AlmasaraModal‎:
 *
 * - تصاویر کامل فقط بعد از باز شدن مودال و به‌صورت ایجکسی (REST) لود می‌شوند
 * - هر تصویر فقط لحظه نمایش دانلود می‌شود و تصاویر مجاور از قبل preload می‌شوند
 * - ناوبری با کلیک، کیبورد (Esc / فلش‌ها) و سوایپ لمسی
 */
(function () {
	'use strict';

	function setup(root) {
		if (root.__zigGal) {
			return;
		}
		root.__zigGal = true;

		// نشانگر پیشرفت اسکرول نوار گالری موبایل
		var scroller = root.querySelector('.zig-gallery__strip');
		var fill = root.querySelector('.zig-gallery__counter-fill');
		if (scroller && fill) {
			var updateProgress = function () {
				var max = scroller.scrollWidth - scroller.clientWidth;
				// در RTL مقدار scrollLeft منفی است؛ قدر مطلق نسبت را درست می‌کند
				var ratio = max > 0 ? Math.min(1, Math.abs(scroller.scrollLeft) / max) : 0;
				fill.style.width = (ratio * 100).toFixed(1) + '%';
			};
			scroller.addEventListener('scroll', updateProgress, { passive: true });
			window.addEventListener('resize', updateProgress, { passive: true });
			updateProgress();
		}

		/*
		 * فلش‌هایِ ناوبریِ تصویرِ شاخص — مستقل از مودال (پیش از return زیر
		 * می‌آید تا وقتی مودال خاموش است هم کار کند). کلیکِ خودِ تامبنیل‌ها
		 * دست‌نخورده می‌ماند و فقط مودال را باز می‌کند؛ این فلش‌ها با
		 * ‎data-large‎ی از پیش در HTML، بدونِ هیچ درخواستِ شبکه‌ای، خودِ
		 * تصویرِ شاخص را عوض می‌کنند و تامبنیلِ متناظر را (اگر دیده‌شدنی
		 * باشد) ‎is-active‎ می‌کنند.
		 */
		var mainEl = root.querySelector('.zig-gallery__main');
		var mainImg = mainEl ? mainEl.querySelector('img') : null;
		var navPrev = root.querySelector('.zig-gallery__nav--prev');
		var navNext = root.querySelector('.zig-gallery__nav--next');
		var navItems = root.querySelectorAll('.zig-gallery__main[data-large], .zig-gallery__thumb[data-large]');

		if (mainImg && navItems.length > 1 && (navPrev || navNext)) {
			var mainIndex = 0;

			var showAt = function (index) {
				mainIndex = (index + navItems.length) % navItems.length;
				var el = navItems[mainIndex];

				mainImg.src = el.dataset.large;
				mainImg.alt = el.dataset.alt || '';

				Array.prototype.forEach.call(navItems, function (item) {
					item.classList.toggle('is-active', item === el);
				});
			};

			if (navPrev) {
				navPrev.addEventListener('click', function (e) {
					e.stopPropagation();
					showAt(mainIndex - 1);
				});
			}
			if (navNext) {
				navNext.addEventListener('click', function (e) {
					e.stopPropagation();
					showAt(mainIndex + 1);
				});
			}
		}

		// تصویرِ شاخصی که خودش ‎<button>‎ نیست (چون فلش داخلش است) با اینتر/اسپیس هم باز شود
		if (mainEl && 'BUTTON' !== mainEl.tagName && mainEl.hasAttribute('role')) {
			mainEl.addEventListener('keydown', function (e) {
				if ('Enter' === e.key || ' ' === e.key) {
					e.preventDefault();
					mainEl.click();
				}
			});
		}

		var modal = root.querySelector('.zig-gallery-modal');
		var endpoint = root.dataset.endpoint;
		if (!modal || !endpoint) {
			return;
		}

		var imgEl = modal.querySelector('.zig-gallery-modal__img');
		var spinner = modal.querySelector('.zig-gallery-modal__spinner');
		var strip = modal.querySelector('.zig-gallery-modal__strip');
		var closeBtn = modal.querySelector('.zig-gallery-modal__close');
		var prevBtn = modal.querySelector('.zig-gallery-modal__nav--prev');
		var nextBtn = modal.querySelector('.zig-gallery-modal__nav--next');

		var images = null;
		var fetching = null;
		var current = 0;
		var isOpen = false;
		var isRtl = document.documentElement.dir === 'rtl';

		function fetchImages() {
			if (images) {
				return Promise.resolve(images);
			}
			if (!fetching) {
				fetching = fetch(endpoint)
					.then(function (res) {
						if (!res.ok) {
							throw new Error('HTTP ' + res.status);
						}
						return res.json();
					})
					.then(function (data) {
						images = Array.isArray(data) ? data : [];
						buildStrip();
						return images;
					})
					.catch(function () {
						fetching = null;
						images = null;
						return [];
					});
			}
			return fetching;
		}

		function buildStrip() {
			strip.innerHTML = '';
			images.forEach(function (item, i) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.setAttribute('aria-label', String(i + 1) + ' / ' + String(images.length));
				var thumb = document.createElement('img');
				thumb.src = item.thumb;
				thumb.alt = item.alt || '';
				thumb.loading = 'lazy';
				btn.appendChild(thumb);
				btn.addEventListener('click', function () {
					show(i);
				});
				strip.appendChild(btn);
			});
		}

		function preload(index) {
			if (!images || !images.length) {
				return;
			}
			var item = images[(index + images.length) % images.length];
			if (item && !item.__preloaded) {
				item.__preloaded = true;
				var im = new Image();
				im.src = item.full;
			}
		}

		function show(index) {
			if (!images || !images.length) {
				return;
			}
			current = (index + images.length) % images.length;
			var item = images[current];

			spinner.hidden = false;
			imgEl.classList.add('is-loading');

			var loader = new Image();
			loader.onload = function () {
				imgEl.src = item.full;
				imgEl.alt = item.alt || '';
				spinner.hidden = true;
				imgEl.classList.remove('is-loading');
			};
			loader.onerror = function () {
				spinner.hidden = true;
				imgEl.classList.remove('is-loading');
			};
			loader.src = item.full;

			// تامبنیل فعال + اسکرول به دید
			Array.prototype.forEach.call(strip.children, function (btn, i) {
				btn.classList.toggle('is-active', i === current);
			});
			var activeBtn = strip.children[current];
			if (activeBtn && activeBtn.scrollIntoView) {
				activeBtn.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
			}

			// preload تصاویر مجاور
			preload(current + 1);
			preload(current - 1);
		}

		function open(index) {
			isOpen = true;

			// یک reflow کوچک تا transition درست بازی کند بعد افزودن کلاس
			modal.offsetHeight;

			// حبس فوکوس، خنثی‌کردن پس‌زمینه، بستن با Escape و برگرداندن فوکوس
			// به عنصر بازکننده، همه بر عهدهٔ کنترلر مشترک است
			if (window.Zig3dModal) {
				window.Zig3dModal.open(modal, {
					initialFocus: closeBtn,
					onClose: function () { isOpen = false; }
				});
			} else {
				modal.classList.add('is-open');
				document.body.classList.add('zig-gallery-noscroll');
				closeBtn.focus({ preventScroll: true });
			}

			fetchImages().then(function (list) {
				if (list.length) {
					show(index);
				}
			});
		}

		function close() {
			isOpen = false;

			if (window.Zig3dModal) {
				window.Zig3dModal.close(modal);
				return;
			}

			modal.classList.remove('is-open');
			document.body.classList.remove('zig-gallery-noscroll');
		}

		function next() {
			show(current + 1);
		}

		function prev() {
			show(current - 1);
		}

		// تریگرها: تصویر شاخص و تامبنیل‌ها
		root.querySelectorAll('.zig-gallery__main[data-index], .zig-gallery__thumb[data-index]').forEach(function (el) {
			el.addEventListener('click', function () {
				open(parseInt(el.dataset.index, 10) || 0);
			});
		});

		closeBtn.addEventListener('click', close);
		nextBtn.addEventListener('click', next);
		prevBtn.addEventListener('click', prev);

		// کلیک روی فضای خالی مودال = بستن
		modal.addEventListener('click', function (e) {
			if (e.target === modal) {
				close();
			}
		});

		// کیبورد
		document.addEventListener('keydown', function (e) {
			if (!isOpen) {
				return;
			}
			if (e.key === 'Escape') {
				close();
			} else if (e.key === 'ArrowLeft') {
				isRtl ? next() : prev();
			} else if (e.key === 'ArrowRight') {
				isRtl ? prev() : next();
			}
		});

		// سوایپ لمسی
		var touchX = null;
		modal.addEventListener('touchstart', function (e) {
			touchX = e.changedTouches[0].clientX;
		}, { passive: true });
		modal.addEventListener('touchend', function (e) {
			if (touchX === null) {
				return;
			}
			var delta = e.changedTouches[0].clientX - touchX;
			touchX = null;
			if (Math.abs(delta) < 40) {
				return;
			}
			// سوایپ به چپ در RTL یعنی تصویر بعدی
			if (delta < 0) {
				isRtl ? next() : prev();
			} else {
				isRtl ? prev() : next();
			}
		}, { passive: true });
	}

	function initAll(scope) {
		(scope || document).querySelectorAll('.zig-gallery').forEach(setup);
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-product-gallery.default',
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
