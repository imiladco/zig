(function () {
	'use strict';

	function select(root, index) {
		var thumbs = Array.prototype.slice.call(root.querySelectorAll('[data-gallery-index]'));
		if (!thumbs.length) { return; }
		index = (index + thumbs.length) % thumbs.length;
		var thumb = thumbs[index];
		var image = root.querySelector('.zig-software-gallery__main-image');
		if (!image) { return; }
		image.classList.add('is-changing');
		image.src = thumb.dataset.src || '';
		if (thumb.dataset.srcset) { image.srcset = thumb.dataset.srcset; } else { image.removeAttribute('srcset'); }
		if (thumb.dataset.sizes) { image.sizes = thumb.dataset.sizes; } else { image.removeAttribute('sizes'); }
		image.alt = thumb.dataset.alt || '';
		thumbs.forEach(function (item) { item.removeAttribute('aria-current'); });
		thumb.setAttribute('aria-current', 'true');
		root.dataset.currentIndex = String(index);
		window.requestAnimationFrame(function () { image.classList.remove('is-changing'); });
	}

	document.addEventListener('click', function (event) {
		var control = event.target.closest('[data-gallery-index], [data-gallery-previous], [data-gallery-next]');
		if (!control) { return; }
		var root = control.closest('[data-zig-software-gallery]');
		if (!root) { return; }
		if (control.hasAttribute('data-gallery-index')) {
			select(root, parseInt(control.dataset.galleryIndex, 10) || 0);
			return;
		}
		var current = parseInt(root.dataset.currentIndex || '0', 10) || 0;
		select(root, current + (control.hasAttribute('data-gallery-next') ? 1 : -1));
	});
}());
