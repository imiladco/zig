/**
 * فهرست مطالب (zig3d-toc).
 *
 * برخلافِ بقیهٔ اسکریپت‌های این افزونه، اینجا تزئینی نیست: بدونِ این فایل
 * فهرست کاملاً خالی می‌ماند. دلیلش خودِ ویژگی است — سرتیترهایی که باید
 * فهرست شوند متعلق به ویجت‌های دیگرِ همان صفحه‌اند، چیزی که فقط مرورگر،
 * بعدِ رندرِ کاملِ صفحه، می‌تواند ببیند. بدونِ jQuery؛ فقط APIِ بومیِ مرورگر.
 */
(function () {
	'use strict';

	var PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

	function toPersianDigits(value) {
		return String(value).replace(/[0-9]/g, function (digit) {
			return PERSIAN_DIGITS[digit];
		});
	}

	/** اسلاگ ساده و یکتا برای شناسهٔ سرتیتر، از روی متنش */
	function slugify(text, used) {
		var base = text
			.trim()
			.toLowerCase()
			.replace(/\s+/g, '-')
			.replace(/[^-\w؀-ۿ]+/g, '')
			.replace(/-+/g, '-')
			.replace(/^-|-$/g, '');

		if ('' === base) {
			base = 'section';
		}

		var slug = base;
		var i = 2;

		while (used[slug]) {
			slug = base + '-' + i;
			i += 1;
		}

		used[slug] = true;

		return slug;
	}

	function prefersReducedMotion() {
		return (
			window.matchMedia &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches
		);
	}

	function buildItem(heading, index, options) {
		var li = document.createElement('li');
		li.className = 'zig-toc__item-wrap';

		var link = document.createElement('a');
		link.className = 'zig-toc__item';
		link.href = '#' + heading.id;

		var label = document.createElement('span');
		label.className = 'zig-toc__label';
		label.textContent = heading.textContent.trim();
		link.appendChild(label);

		if (options.numbering) {
			var number = index + 1;
			var text = options.pad && number < 10 ? '0' + number : String(number);

			if (options.persianDigits) {
				text = toPersianDigits(text);
			}

			var indexEl = document.createElement('span');
			indexEl.className = 'zig-toc__index';
			indexEl.setAttribute('aria-hidden', 'true');
			indexEl.textContent = text;
			link.appendChild(indexEl);
		}

		li.appendChild(link);

		return { li: li, link: link, heading: heading };
	}

	function scrollToHeading(heading, offset, smooth) {
		var top = heading.getBoundingClientRect().top + window.pageYOffset - offset;

		window.scrollTo({
			top: Math.max(0, top),
			behavior: smooth && !prefersReducedMotion() ? 'smooth' : 'auto',
		});
	}

	function initScrollSpy(entries, offset) {
		var active = null;

		function setActive(next) {
			if (next === active) {
				return;
			}

			if (active) {
				active.link.classList.remove('zig-toc__item--active');
				active.link.removeAttribute('aria-current');
			}

			if (next) {
				next.link.classList.add('zig-toc__item--active');
				next.link.setAttribute('aria-current', 'true');
			}

			active = next;
		}

		var ticking = false;
		var threshold = offset + 24;

		function recalc() {
			ticking = false;

			var current = null;

			for (var i = 0; i < entries.length; i += 1) {
				var rect = entries[i].heading.getBoundingClientRect();

				if (rect.top <= threshold) {
					current = entries[i];
				}
			}

			if (!current) {
				current = entries[0];
			}

			setActive(current);
		}

		function onScroll() {
			if (ticking) {
				return;
			}

			ticking = true;
			window.requestAnimationFrame(recalc);
		}

		recalc();
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll);
	}

	function setup(root) {
		if (root.__zigToc) {
			return;
		}
		root.__zigToc = true;

		var tags = (root.getAttribute('data-tags') || '')
			.split(',')
			.map(function (tag) {
				return tag.trim().toLowerCase();
			})
			.filter(Boolean);

		var list = root.querySelector('.zig-toc__list');
		var empty = root.querySelector('.zig-toc__empty');
		var isEditor = '1' === root.getAttribute('data-editor');

		if (!tags.length || !list) {
			root.style.display = 'none';
			return;
		}

		var scopeSelector = root.getAttribute('data-scope') || '';
		var excludeSelector = root.getAttribute('data-exclude') || '';
		var minCount = parseInt(root.getAttribute('data-min'), 10);
		if (isNaN(minCount) || minCount < 0) {
			minCount = 0;
		}
		var offset = parseInt(root.getAttribute('data-offset'), 10) || 0;
		var smooth = 'yes' === root.getAttribute('data-smooth');
		var spy = 'yes' === root.getAttribute('data-spy');
		var numbering = 'yes' === root.getAttribute('data-numbering');
		var persianDigits = 'persian' === root.getAttribute('data-digits');
		var pad = 'yes' === root.getAttribute('data-pad');

		var scope = document;

		if (scopeSelector) {
			var found = document.querySelector(scopeSelector);

			if (found) {
				scope = found;
			}
		}

		var headings = Array.prototype.slice
			.call(scope.querySelectorAll(tags.join(',')))
			.filter(function (heading) {
				if (root.contains(heading)) {
					return false;
				}

				if (excludeSelector && heading.closest(excludeSelector)) {
					return false;
				}

				return '' !== heading.textContent.trim();
			});

		if (headings.length < minCount) {
			if (isEditor && empty) {
				empty.hidden = false;
			} else {
				root.style.display = 'none';
			}

			return;
		}

		if (empty) {
			empty.hidden = true;
		}

		var used = {};
		var fragment = document.createDocumentFragment();
		var entries = [];

		headings.forEach(function (heading, index) {
			if (!heading.id) {
				heading.id = 'zig-toc-' + slugify(heading.textContent, used);
			} else {
				used[heading.id] = true;
			}

			if (offset) {
				heading.style.scrollMarginTop = offset + 'px';
			}

			var entry = buildItem(heading, index, {
				numbering: numbering,
				persianDigits: persianDigits,
				pad: pad,
			});

			entry.link.addEventListener('click', function (event) {
				event.preventDefault();
				scrollToHeading(heading, offset, smooth);

				if (window.history && window.history.pushState) {
					window.history.pushState(null, '', '#' + heading.id);
				}
			});

			fragment.appendChild(entry.li);
			entries.push(entry);
		});

		list.appendChild(fragment);

		if (spy) {
			initScrollSpy(entries, offset);
		}
	}

	function initAll(scope) {
		(scope || document).querySelectorAll('[data-zig-toc]').forEach(setup);
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/zig3d-toc.default',
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
