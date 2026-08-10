/*
 * سنجه‌های مرورگری آرشیو.
 *
 * چیزی که این‌ها می‌سنجند در هیچ تست واحدی نمی‌گنجد: کلیک، فوکوس،
 * تاریخچه، مسابقهٔ دو درخواست، و رفتار وقتی شبکه قطع می‌شود. همه با
 * مرورگر واقعی اجرا می‌شوند.
 *
 * اجرا:
 *   node tests/integration/browser-checks.mjs http://localhost:8080/product-category/cnc/
 */

import { chromium } from 'playwright';

const BASE = process.argv[2] || 'http://localhost:8080/product-category/cnc/';
const EXEC = process.env.ZIG_CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

const results = [];

function check(name, ok, detail = '') {
	results.push({ name, ok });
	console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ' — ' + detail : ''}`);
}

function group(title) {
	console.log(`── ${title}`);
}

/**
 * هر بخش جدا، تا یک شکست بقیه را نبرد.
 *
 * بدون این، اولین ‎timeout‎ کل اجرا را قطع می‌کرد و هجده بخشِ بعدی هرگز
 * دیده نمی‌شدند — یعنی یک باگ، نوزده سنجهٔ ناشناخته می‌ساخت.
 */
async function section(title, fn) {
	group(title);

	try {
		await fn();
	} catch (e) {
		check(`${title} — بخش تا آخر نرفت`, false, e.message.split('\n')[0].slice(0, 120));
	}
}

const $ = {
	root: '[data-zig-archive]',
	cell: '.zig-archive__cell',
	item: '.zig-facet__item',
	selected: '.zig-facet__item.is-selected',
	count: '.zig-archive__count',
	error: '.zig-archive__error',
	retry: '.zig-archive__retry',
	page: '.zig-page',
	pill: '.zig-sorts__pill',
	clear: '.zig-filters__clear',
	empty: '.zig-archive__empty',
};

const fa = (s) => (s || '').replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
const countOf = async (p) => parseInt(fa((await p.locator($.count).first().textContent()) || '').match(/\d+/)?.[0] || '0', 10);
const state = (p) => p.locator($.root).getAttribute('data-zig-state');

/**
 * صبر تا وقتی درخواست *شروع* شود، تمام شود، و DOM بنشیند.
 *
 * صبرکردن فقط برای ناپدیدشدن ‎aria-busy‎ کافی نیست و این را همین تست یاد
 * داد: کلیک فیلتر ۲۵۰ میلی‌ثانیه دیبونس دارد، پس در لحظهٔ بررسی هنوز هیچ
 * درخواستی شروع نشده و ‎aria-busy‎ *اصلاً* نیامده. آن‌وقت تست بی‌درنگ رد
 * می‌شود و هرچه بعدش می‌سنجد، وضعیتِ قبل از کلیک است.
 */
async function settle(p, { started = true } = {}) {
	if (started) {
		await p.waitForFunction(
			() => document.querySelector('[data-zig-archive]').hasAttribute('aria-busy'),
			null,
			{ timeout: 5000 }
		).catch(() => {});
	}

	await p.waitForFunction(() => !document.querySelector('[data-zig-archive]').hasAttribute('aria-busy'), null, { timeout: 15000 });
	await p.waitForTimeout(200);
}

const run = async () => {
	const browser = await chromium.launch({ executablePath: EXEC, args: ['--no-sandbox'] });
	const ctx = await browser.newContext();
	const page = await ctx.newPage();

	/*
	 * فقط خطاهای *ما*.
	 *
	 * قالب و ووکامرس و المنتور خطاهای خودشان را دارند (اینجا مثلاً
	 * ‎elementorFrontendConfig is not defined‎، چون هارنس تست ویجت را بعد
	 * از صفِ اسکریپت‌ها چاپ می‌کند). شمردن آن‌ها یعنی این سنجه هیچ‌وقت سبز
	 * نشود و بعد از دو بار، کسی دیگر نگاهش نکند.
	 */
	const errors = [];
	const mine = (t) => /zig/i.test(t) || /\bfetch\b/i.test(t);
	const noted = (t) => { if (mine(t)) { errors.push(t); } };
	/*
	 * فقط بارگذاری *واقعی* صفحه شمرده می‌شود.
	 *
	 * ‎framenavigated‎ روی ‎pushState‎ هم شلیک می‌کند — یعنی دقیقاً روی همان
	 * کاری که این ویجت به‌جای پیمایش انجام می‌دهد. شمردنش، ادعای «بدون
	 * پیمایش» را همیشه رد می‌کرد.
	 */
	const loads = [];
	page.on('load', () => loads.push(page.url()));

	page.on('pageerror', (e) => noted(e.message));
	page.on('console', (m) => m.type() === 'error' && noted(m.text()));

	/* ================================================================== */
	await section('۱ پایه', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		check('ویجت رندر می‌شود', (await page.locator($.root).count()) === 1);
		check('جاوااسکریپت سوار می‌شود', await page.evaluate((s) => !!document.querySelector(s).zigArchive, $.root));
		check('کارت‌ها به تعداد صفحهٔ آرشیو', (await page.locator($.cell).count()) === 16, String(await page.locator($.cell).count()));
		check('وضعیت اولیه ok', (await state(page)) === 'ok');


	});
	await section('۲ کلیک فیلتر بدون پیمایش', async () => {

		const before = page.url();

		/*
		 * شمارندهٔ پیمایش، *بعد* از هر goto صفر می‌شود.
		 *
		 * خودِ goto هم یک framenavigated می‌سازد؛ بدون صفرکردن، هر بخش
		 * پیمایش‌های بخش‌های قبلی را هم می‌شمرد و ادعای «بدون پیمایش کامل»
		 * بی‌معنا می‌شد.
		 */
		loads.length = 0;

		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await settle(page);

		check('آدرس عوض شد', page.url() !== before, page.url().split('?')[1] || '');
		check('ولی پیمایش کامل نشد', loads.length === 0, `${loads.length} load`);
		check('شمارش تازه شد', (await countOf(page)) === 11, String(await countOf(page)));
		check('گزینه انتخاب‌شده علامت خورد', (await page.locator($.selected).count()) === 1);
		check('هیچ خطای جاوااسکریپتی', errors.length === 0, errors.slice(0, 2).join(' | '));


	});
	await section('۳ دو کلیک سریع — مسابقهٔ href کهنه', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await page.locator('[data-zig-toggle="filter_brand|vhf"]').click();
		await settle(page);

		const q = new URL(page.url()).searchParams.get('filter_brand') || '';
		check('هر دو برند در آدرس‌اند', q.includes('up3d') && q.includes('vhf'), q);
		check('و اپراتور صریح است', new URL(page.url()).searchParams.get('query_type_brand') === 'or');
		check('نتیجه اجتماع است', (await countOf(page)) === 21, String(await countOf(page)));
		check('هر دو تیک خورده‌اند', (await page.locator($.selected).count()) === 2);


	});
	await section('۴ ترتیب', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		await page.locator('[data-zig-sort="price"]').click();
		await settle(page);

		check('orderby در آدرس نشست', new URL(page.url()).searchParams.get('orderby') === 'price');
		check('پیل فعال جابه‌جا شد', (await page.locator('.zig-sorts__pill.is-active').getAttribute('data-zig-sort')) === 'price');

		const prices = await page.locator('.zig-price__amount').allTextContents();
		const nums = prices.map((t) => parseInt(fa(t).replace(/\D/g, ''), 10)).filter(Boolean);
		check('واقعاً از ارزان به گران', nums.every((n, i) => i === 0 || nums[i - 1] <= n), nums.slice(0, 3).join(' , '));


	});
	await section('۵ صفحه‌بندی و تاریخچه', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		const firstTitle = await page.locator('.zig-card__title').first().textContent();

		loads.length = 0;
		await page.locator('.zig-page:not(.zig-page--next)[data-zig-goto="2"]').click();
		await settle(page);

		check('صفحهٔ ۲ در آدرس', new URL(page.url()).searchParams.get('paged') === '2');
		check('محتوا عوض شد', (await page.locator('.zig-card__title').first().textContent()) !== firstTitle);
		check('aria-current روی صفحهٔ جاری', (await page.locator('.zig-page.is-current').textContent()) === '۲');

		await page.goBack();
		await settle(page);

		check('back به صفحهٔ ۱ برمی‌گردد', !new URL(page.url()).searchParams.get('paged'));
		check('و محتوایش درست است', (await page.locator('.zig-card__title').first().textContent()) === firstTitle);
		check('بدون پیمایش کامل', loads.length === 0, `${loads.length} load`);


	});
	await section('۶ فیلتر، صفحه را از اول شروع می‌کند', async () => {

		await page.goto(BASE + '?paged=2', { waitUntil: 'domcontentloaded' });
		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await settle(page);

		check('paged افتاد', !new URL(page.url()).searchParams.get('paged'), page.url().split('?')[1] || '');


	});
	await section('۷ نتیجهٔ خالی، نه خطای فنی', async () => {
		/*
		 * از راه آدرس، نه کلیک: با این کاتالوگ هیچ مسیرِ کلیکی به صفر
		 * نمی‌رسد — چون شمارشِ خودحذف‌کن دقیقاً همین را تضمین می‌کند و
		 * گزینه‌ای که به صفر می‌رساند، غیرفعال رندر می‌شود. خودِ آن هم
		 * پایین سنجیده می‌شود.
		 */
		await page.goto(BASE + '?filter_brand=up3d&filter_axis=3-axis&filter_material=pmma', { waitUntil: 'domcontentloaded' });

		check('حالت filtered_empty', (await state(page)) === 'filtered_empty', await state(page));
		check('بلوک «چیزی پیدا نشد» داخل گرید', (await page.locator($.empty).count()) === 1);
		check('آلرت تلاش مجدد بالا نیامد', await page.locator($.error).isHidden());
		check('فیلترها هنوز قابل برداشتن‌اند', (await page.locator($.selected).count()) === 3);

		await page.locator($.clear).click();
		await settle(page);
		check('پاک‌کردن همه برمی‌گرداند', (await countOf(page)) === 31, String(await countOf(page)));
	});

	await section('۷ب گزینه‌ای که به بن‌بست می‌رسد، غیرفعال است', async () => {
		await page.goto(BASE + '?filter_brand=up3d&filter_axis=3-axis', { waitUntil: 'domcontentloaded' });

		const disabled = page.locator('.zig-facet__item.is-disabled');
		check('گزینهٔ صفر در DOM می‌ماند', (await disabled.count()) === 1, `${await page.locator($.item).count()} گزینه`);
		check('ولی لینک نیست', (await disabled.locator('a').count()) === 0);
		check('و aria-disabled دارد', (await disabled.locator('[aria-disabled="true"]').count()) === 1);
		check('شمارشش صفر است', (await disabled.textContent()).includes('۰'));

		/* شمارش خودحذف‌کن: گروهِ انتخاب‌شده، گزینه‌های دیگرش صفر نمی‌شوند */
		const axis = await page.locator('.zig-facet__item:has([data-zig-toggle="filter_axis|5-axis"])').textContent();
		check('گروه انتخاب‌شده خودش را از قید حذف می‌کند', !axis.includes('۰'), axis.trim().slice(0, 20));
	});

	await section('۸ خطای فنی — تنها مسیر آلرت', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		await ctx.route('**/admin-ajax.php', (route) => route.abort('failed'));

		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await page.waitForSelector('.zig-archive__error:not([hidden])', { timeout: 10000 }).catch(() => {});

		check('آلرت بالا آمد', await page.locator($.error).isVisible());
		check('نقش alert دارد', (await page.locator($.error).getAttribute('role')) === 'alert');
		check('گرید قبلی دست‌نخورده ماند', (await page.locator($.cell).count()) === 16);

		await ctx.unroute('**/admin-ajax.php');
		await page.locator($.retry).click();
		await settle(page);

		check('تلاش مجدد کار می‌کند', (await countOf(page)) === 11, String(await countOf(page)));
		check('و آلرت رفت', await page.locator($.error).isHidden());


	});
	await section('۹ پاسخ بدشکل', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		await ctx.route('**/admin-ajax.php', (route) =>
			route.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"state":"ok"' })
		);

		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await page.waitForSelector('.zig-archive__error:not([hidden])', { timeout: 10000 }).catch(() => {});

		check('JSON بدشکل = خطای فنی', await page.locator($.error).isVisible());
		check('گرید حفظ شد', (await page.locator($.cell).count()) === 16);

		await ctx.unroute('**/admin-ajax.php');


	});
	await section('۱۰ نسخهٔ قرارداد', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		await ctx.route('**/admin-ajax.php', async (route) => {
			const res = await route.fetch();
			const body = await res.json();
			body.data.contract = 999;
			route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
		});

		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await page.waitForSelector('.zig-archive__error:not([hidden])', { timeout: 10000 }).catch(() => {});

		check('قرارداد ناهمخوان = خطای فنی', await page.locator($.error).isVisible());
		check('نه یک رابط نیمه‌کاره', (await page.locator($.cell).count()) === 16);

		await ctx.unroute('**/admin-ajax.php');


	});
	await section('۱۱ پاسخ کهنه', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });

		let n = 0;
		await ctx.route('**/admin-ajax.php', async (route) => {
			n++;
			// اولی عمداً کند می‌شود تا بعد از دومی برسد
			if (n === 1) { await new Promise((r) => setTimeout(r, 2500)); }
			route.continue();
		});

		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await page.waitForTimeout(400);
		await page.locator('[data-zig-toggle="filter_axis|5-axis"]').click();
		await page.waitForTimeout(4000);

		const finalQ = new URL(page.url()).searchParams;
		check('نتیجهٔ نهایی مالِ آخرین کلیک است', finalQ.has('filter_axis'), page.url().split('?')[1] || '');
		check('پاسخ کهنه آن را پس نزد', (await state(page)) !== null);

		await ctx.unroute('**/admin-ajax.php');


	});
	await section('۱۲ فوکوس', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		await page.locator('[data-zig-toggle="filter_brand|up3d"]').focus();
		await page.keyboard.press('Enter');
		await settle(page);

		const active = await page.evaluate(() => document.activeElement.tagName + ':' + (document.activeElement.getAttribute('data-zig-part') || document.activeElement.className));
		check('فوکوس به body نپرید', !active.startsWith('BODY'), active);


	});
	await section('۱۳ بدون جاوااسکریپت', async () => {

		const noJs = await browser.newContext({ javaScriptEnabled: false });
		const np = await noJs.newPage();
		await np.goto(BASE, { waitUntil: 'domcontentloaded' });

		check('گرید بدون JS رندر می‌شود', (await np.locator($.cell).count()) === 16);
		check('لینک فیلتر href واقعی دارد', (await np.locator('[data-zig-toggle="filter_brand|up3d"]').getAttribute('href')).includes('filter_brand=up3d'));

		await np.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await np.waitForLoadState('domcontentloaded');

		check('و کلیک واقعاً پیمایش می‌کند', np.url().includes('filter_brand=up3d'), np.url().split('?')[1] || '');
		check('نتیجه هم درست است', (await countOf(np)) === 11, String(await countOf(np)));
		await noJs.close();


	});
	await section('۱۴ آدرس بی‌معنا در مرورگر', async () => {

		const ghost = await page.goto(BASE + '?filter_ghost=x', { waitUntil: 'domcontentloaded' });
		check('۴۰۴ می‌گیرد', ghost.status() === 404, String(ghost.status()));
		check('ولی آرشیو رندر می‌شود', (await page.locator($.cell).count()) === 16);
		check('وضعیت invalid', (await state(page)) === 'invalid', await state(page));


	});
	await section('۱۵ ذخیره و بازگردانی موقعیت', async () => {

		await page.goto(BASE + '?paged=2', { waitUntil: 'domcontentloaded' });
		await page.evaluate(() => window.scrollTo(0, 800));
		await page.waitForTimeout(200);
		const link = await page.locator('.zig-card__title a').first().getAttribute('href');
		await page.goto(link, { waitUntil: 'domcontentloaded' });
		await page.goBack({ waitUntil: 'domcontentloaded' });
		await page.waitForTimeout(600);

		check('به صفحهٔ ۲ برگشت', new URL(page.url()).searchParams.get('paged') === '2');
		check('و موقعیت اسکرول بازگشت', (await page.evaluate(() => window.scrollY)) > 300, String(await page.evaluate(() => window.scrollY)));


	});
	await section('۱۶ دو ویجت روی یک صفحه', async () => {

		await page.goto('http://localhost:8080/zig-double/', { waitUntil: 'domcontentloaded' });
		const roots = await page.locator($.root).count();

		if (roots >= 2) {
			const owners = await page.evaluate((s) => Array.from(document.querySelectorAll(s)).map((r) => !!r.zigArchive?.owner), $.root);
			check('فقط یکی مالک تاریخچه است', owners.filter(Boolean).length === 1, JSON.stringify(owners));
		} else {
			check('دو ویجت روی صفحه', false, `فقط ${roots} پیدا شد — سناریو ساخته نشد`);
		}


	});
	await section('۱۷ دسترس‌پذیری', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		const roleAttrs = await page.evaluate(() =>
			Array.from(document.querySelectorAll('.zig-facet__item a')).map((a) => ({
				role: a.getAttribute('role'),
				pressed: a.getAttribute('aria-pressed'),
				sr: !!a.querySelector('.zig-sr'),
			}))
		);
		check('لینک فیلتر role جعلی ندارد', roleAttrs.every((a) => !a.role));
		check('و aria-pressed ندارد', roleAttrs.every((a) => !a.pressed));
		check('ولی متن پنهانِ عمل دارد', roleAttrs.every((a) => a.sr));
		check('شمارش aria-live دارد', (await page.locator($.count).getAttribute('aria-live')) === 'polite');
		check('چک‌باکس تصویری از صفحه‌خوان پنهان است', (await page.locator('.zig-facet__box').first().getAttribute('aria-hidden')) === 'true');


	});
	await section('۱۸ چیدمان', async () => {

		await page.setViewportSize({ width: 1280, height: 900 });
		await page.goto(BASE, { waitUntil: 'domcontentloaded' });

		const cols = await page.evaluate((s) => getComputedStyle(document.querySelector(s)).gridTemplateColumns.split(' ').length, '.zig-archive__grid');
		check('گرید سه‌ستونی روی دسکتاپ', cols === 3, String(cols));

		const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
		check('اسکرول افقی ندارد', overflow <= 0, String(overflow));

		const dir = await page.evaluate(() => getComputedStyle(document.documentElement).direction);
		check('جهت صفحه خوانده شد', !!dir, dir);


	});
	await section('۱۹ حالت بارگذاری', async () => {

		await page.goto(BASE, { waitUntil: 'domcontentloaded' });
		await ctx.route('**/admin-ajax.php', async (route) => {
			await new Promise((r) => setTimeout(r, 900));
			route.continue();
		});

		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();

		/*
		 * انتظارِ *شرطی*، نه یک عدد ثابت.
		 *
		 * کلیک فیلتر ۲۵۰ میلی‌ثانیه دیبونس دارد؛ یک ‎waitForTimeout(400)‎
		 * فقط ۱۵۰ میلی‌ثانیه حاشیه می‌گذارد و روی ماشینِ شلوغ همان را هم
		 * از دست می‌دهد. آن‌وقت تست قرمز می‌شود بدون اینکه چیزی خراب باشد —
		 * که بدترین نوع تست است.
		 */
		const busy = await page
			.waitForFunction(() => document.querySelector('[data-zig-archive]').getAttribute('aria-busy') === 'true', null, { timeout: 8000 })
			.then(() => true)
			.catch(() => false);

		check('aria-busy روی ریشه', busy);
		await settle(page);
		check('و بعد برداشته می‌شود', (await page.locator($.root).getAttribute('aria-busy')) === null);
		await ctx.unroute('**/admin-ajax.php');


	});
	await section('۲۰ برداشتن فیلتر', async () => {

		await page.goto(BASE + '?filter_brand=up3d', { waitUntil: 'domcontentloaded' });
		check('با یک فیلتر شروع می‌شود', (await countOf(page)) === 11);
		await page.locator('[data-zig-toggle="filter_brand|up3d"]').click();
		await settle(page);
		check('کلیک دوباره برش می‌دارد', (await countOf(page)) === 31, String(await countOf(page)));
		check('و آدرس تمیز شد', !new URL(page.url()).searchParams.has('filter_brand'), page.url().split('?')[1] || '');


	});
	await section('۲۱ گزینهٔ صفر', async () => {

		await page.goto(BASE + '?filter_brand=up3d', { waitUntil: 'domcontentloaded' });
		const disabled = await page.locator('.zig-facet__item.is-disabled').count();
		const total = await page.locator($.item).count();
		check('گزینه‌ها حذف نمی‌شوند', total >= 7, `${total} گزینه، ${disabled} غیرفعال`);
		check('انتخاب‌شده هیچ‌وقت غیرفعال نیست', (await page.locator('.zig-facet__item.is-selected.is-disabled').count()) === 0);


	});
	check('در کل هیچ خطای جاوااسکریپتی رخ نداد', errors.length === 0, errors.slice(0, 3).join(' | '));

	await browser.close();

	const bad = results.filter((r) => !r.ok);
	console.log(`\n${results.length} سنجه، ${bad.length} ناموفق`);
	if (bad.length) { console.log(bad.map((r) => '  ✗ ' + r.name).join('\n')); }
	process.exit(bad.length ? 1 : 0);
};

run().catch((e) => { console.error('ERR', e.message); process.exit(2); });
