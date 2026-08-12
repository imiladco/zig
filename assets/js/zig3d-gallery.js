/*
 * پورتِ مستقیمِ رفتارِ گالریِ محصولِ الماس‌آرا (شورت‌کدِ product_gallery_modal2).
 *
 * منطق عمداً همان است — یک شمارندهٔ ‎index‎، یک تابعِ ‎show()‎ که با
 * ‎style.display‎ تصویرِ فعال را عوض می‌کند، و پنج دسته شنوندهٔ کلیک. هیچ
 * چیزِ تازه‌ای (IntersectionObserver، انیمیشن، …) اضافه نشده؛ فقط
 * سلکتورها به کلاس‌های ‎zig-gallery*‎ پورت شده‌اند. طراحی و رفتارهای
 * بیشتر (کلیک روی خودِ تصویرِ اصلی، افکتِ «+N» و…) در دورِ اصلاحاتِ
 * بعدی می‌آید.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var modal = document.querySelector('.zig-gallery-modal');

		if (!modal) {
			return;
		}

		var images       = modal.querySelectorAll('.zig-gallery-modal__image');
		var thumbnails   = document.querySelectorAll('.zig-gallery__thumb');
		var footerThumbs = modal.querySelectorAll('.zig-gallery-modal__footer-thumb');
		var closeBtn     = modal.querySelector('.zig-gallery-modal__close');
		var prevBtn      = modal.querySelector('.zig-gallery-modal__prev');
		var nextBtn      = modal.querySelector('.zig-gallery-modal__next');
		var currentIndex = 0;

		function showModalImage(index) {
			images.forEach(function (img, i) {
				img.style.display = i === index ? 'block' : 'none';
			});
		}

		thumbnails.forEach(function (thumb, index) {
			thumb.addEventListener('click', function () {
				currentIndex = index;
				showModalImage(currentIndex);
				modal.style.display = 'flex';
			});
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				modal.style.display = 'none';
			});
		}

		if (prevBtn) {
			prevBtn.addEventListener('click', function () {
				currentIndex = currentIndex > 0 ? currentIndex - 1 : images.length - 1;
				showModalImage(currentIndex);
			});
		}

		if (nextBtn) {
			nextBtn.addEventListener('click', function () {
				currentIndex = currentIndex < images.length - 1 ? currentIndex + 1 : 0;
				showModalImage(currentIndex);
			});
		}

		footerThumbs.forEach(function (thumb, index) {
			thumb.addEventListener('click', function () {
				currentIndex = index;
				showModalImage(currentIndex);
			});
		});
	});
})();
