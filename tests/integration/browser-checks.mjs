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
 * بازکردن گروهی که این گزینه در آن است، و برگرداندن لوکیتورِ خودِ گزینه.
 *
 * گروه‌ها بسته شروع می‌شوند — همان رفتاری که دیزاین می‌خواهد: فقط گروهی
 * که انتخابی دارد باز است. پس تست هم باید مثل کاربر اول بازش کند، وگرنه
 * روی عنصری کلیک می‌کند که دیده نمی‌شود.
 *
 * و ‎.zig-facet__item‎ در سلکتور لازم است: چیپ‌های «فیلترهای اعمال‌شده»
 * همان ‎data-zig-toggle‎ را دارند، پس بدون دامنه، دو عنصر می‌خورد.
 */
async function option(p, toggle) {
	const item = p.locator(`.zig-facet__item [data-zig-toggle="${toggle}"]`);
	const group = p.locator('.zig-facet', { has: p.locator(`[data-zig-toggle="${toggle}"]`) }).first();

	if (!(await group.evaluate((el) => el.hasAttribute('open')).catch(() => true))) {
		await group.locator('summary').click();
	}

	return item;
}

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

/**
 * رفتن به یک آدرس، و صبر تا وقتی جاوااسکریپت واقعاً سوار شده باشد.
 *
 * ‎domcontentloaded‎ تنها کافی نیست و این را یک قرمزیِ گذرا یاد داد: روی
 * درخواستِ کندِ اول، تست روی لینکِ صفحه‌بندی کلیک می‌کرد در حالی که هندلر
 * هنوز وصل نشده بود. آن‌وقت مرورگر کارِ *درست* را می‌کرد — لینک واقعی است
 * و پیمایش می‌کند — ولی سنجهٔ «بدون پیمایش کامل» قرمز می‌شد.
 *
 * یعنی تست، ارتقای تدریجی را به‌عنوان باگ گزارش می‌کرد. صبر تا سوارشدن،
 * همان چیزی است که کاربر واقعی هم دارد.
 */
async function visit(p, url) {
	await p.goto(url, { waitUntil: 'domcontentloaded' });
	await p.waitForFunction((s) => !!document.querySelector(s)?.zigArchive, $.root, { timeout: 15000 });

	/*
	 * و صبر تا رویداد ‎load‎ خودِ سند.
	 *
	 * سوارشدن جاوااسکریپت روی ‎DOMContentLoaded‎ اتفاق می‌افتد، ولی
	 * ‎load‎ منتظر تصویرها هم می‌ماند. بدون این خط، بخشی که بلافاصله
	 * ‎loads.length = 0‎ می‌کرد گاهی همان ‎load‎ِ عقب‌مانده را می‌شمرد و
	 * ادعای «بدون پیمایش کامل» را قرمز می‌کرد — بی‌آنکه چیزی خراب باشد.
	 *
	 * گذرا بود و تا وقتی دادهٔ نمونه تصویر نداشت اصلاً پیدا نمی‌شد.
	 */
	await p.waitForLoadState('load');
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

		await visit(page, BASE);
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

		await (await option(page, 'filter_brand|up3d')).click();
		await settle(page);

		check('آدرس عوض شد', page.url() !== before, page.url().split('?')[1] || '');
		check('ولی پیمایش کامل نشد', loads.length === 0, `${loads.length} load`);
		check('شمارش تازه شد', (await countOf(page)) === 11, String(await countOf(page)));
		check('گزینه انتخاب‌شده علامت خورد', (await page.locator($.selected).count()) === 1);
		check('هیچ خطای جاوااسکریپتی', errors.length === 0, errors.slice(0, 2).join(' | '));


	});
	await section('۳ دو کلیک سریع — مسابقهٔ href کهنه', async () => {

		await visit(page, BASE);
		await (await option(page, 'filter_brand|up3d')).click();
		await (await option(page, 'filter_brand|vhf')).click();
		await settle(page);

		const q = new URL(page.url()).searchParams.get('filter_brand') || '';
		check('هر دو برند در آدرس‌اند', q.includes('up3d') && q.includes('vhf'), q);
		check('و اپراتور صریح است', new URL(page.url()).searchParams.get('query_type_brand') === 'or');
		check('نتیجه اجتماع است', (await countOf(page)) === 21, String(await countOf(page)));
		check('هر دو تیک خورده‌اند', (await page.locator($.selected).count()) === 2);


	});
	await section('۴ ترتیب', async () => {

		await visit(page, BASE);
		await page.locator('[data-zig-sort="price"]').click();
		await settle(page);

		check('orderby در آدرس نشست', new URL(page.url()).searchParams.get('orderby') === 'price');
		check('پیل فعال جابه‌جا شد', (await page.locator('.zig-sorts__pill.is-active').getAttribute('data-zig-sort')) === 'price');

		const prices = await page.locator('.zig-price__amount').allTextContents();
		const nums = prices.map((t) => parseInt(fa(t).replace(/\D/g, ''), 10)).filter(Boolean);
		check('واقعاً از ارزان به گران', nums.every((n, i) => i === 0 || nums[i - 1] <= n), nums.slice(0, 3).join(' , '));


	});
	await section('۵ صفحه‌بندی و تاریخچه', async () => {

		await visit(page, BASE);
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

		await visit(page, BASE + '?paged=2');
		await (await option(page, 'filter_brand|up3d')).click();
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
		await visit(page, BASE + '?filter_brand=up3d&filter_axis=3-axis&filter_material=pmma');

		check('حالت filtered_empty', (await state(page)) === 'filtered_empty', await state(page));
		check('بلوک «چیزی پیدا نشد» داخل گرید', (await page.locator($.empty).count()) === 1);
		check('آلرت تلاش مجدد بالا نیامد', await page.locator($.error).isHidden());
		check('فیلترها هنوز قابل برداشتن‌اند', (await page.locator($.selected).count()) === 3);

		await page.locator($.clear).click();
		await settle(page);
		check('پاک‌کردن همه برمی‌گرداند', (await countOf(page)) === 31, String(await countOf(page)));
	});

	await section('۷ب گزینه‌ای که به بن‌بست می‌رسد، غیرفعال است', async () => {
		await visit(page, BASE + '?filter_brand=up3d&filter_axis=3-axis');

		const disabled = page.locator('.zig-facet__item.is-disabled');
		const zeros = await disabled.count();

		check('گزینهٔ صفر در DOM می‌ماند', zeros >= 1, `${await page.locator($.item).count()} گزینه، ${zeros} صفر`);
		check('ولی لینک نیست', (await disabled.locator('a').count()) === 0);
		check('و aria-disabled دارد', (await disabled.locator('[aria-disabled="true"]').count()) === zeros);
		check('شمارشش صفر است', (await disabled.first().textContent()).includes('۰'));

		/* شمارش خودحذف‌کن: گروهِ انتخاب‌شده، گزینه‌های دیگرش صفر نمی‌شوند */
		const axis = await page.locator('.zig-facet__item:has([data-zig-toggle="filter_axis|5-axis"])').first().textContent();
		check('گروه انتخاب‌شده خودش را از قید حذف می‌کند', !axis.includes('۰'), axis.trim().slice(0, 20));
	});

	await section('۷پ چیپ‌های فیلترهای اعمال‌شده', async () => {
		await visit(page, BASE + '?filter_product_brand=up3d&filter_axis=5-axis');

		const chips = page.locator('.zig-filters__chip');
		check('برای هر فیلتر فعال یک چیپ', (await chips.count()) === 2, String(await chips.count()));

		const texts = await page.locator('.zig-filters__chip-text').allTextContents();
		check('برچسب چیپ از ترم می‌آید نه اسلاگ', !texts.join(' ').includes('5-axis'), texts.join(' , '));

		const badge = (await page.locator('.zig-filters__badge').textContent()).trim();
		check('شمارنده با تعداد فیلترها می‌خواند', badge.includes('۲'), badge);

		/*
		 * چیپ‌ها *همهٔ* فیلترهای فعال را نشان می‌دهند، حتی وقتی گروهشان
		 * بسته است — همان چیزی که کل این ردیف برایش هست.
		 */
		check('گروه بی‌انتخاب بسته می‌ماند', (await page.locator('.zig-facet:not([open])').count()) >= 1);
		check('و گروه دارای انتخاب باز است', (await page.locator('.zig-facet[open]').count()) >= 1);

		await chips.first().click();
		await settle(page);

		check('کلیک روی چیپ همان فیلتر را برمی‌دارد', (await page.locator('.zig-filters__chip').count()) === 1);
		check('و یکی از دو پارامتر از آدرس رفت', new URL(page.url()).searchParams.size === 1, page.url().split('?')[1] || '');

		await page.locator('.zig-filters__clear').click();
		await settle(page);
		check('حذف همه، همه را می‌برد', (await page.locator('.zig-filters__chip').count()) === 0);
	});

	await section('۸ خطای فنی — تنها مسیر آلرت', async () => {

		await visit(page, BASE);
		await ctx.route('**/admin-ajax.php', (route) => route.abort('failed'));

		await (await option(page, 'filter_brand|up3d')).click();
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

		await visit(page, BASE);
		await ctx.route('**/admin-ajax.php', (route) =>
			route.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"state":"ok"' })
		);

		await (await option(page, 'filter_brand|up3d')).click();
		await page.waitForSelector('.zig-archive__error:not([hidden])', { timeout: 10000 }).catch(() => {});

		check('JSON بدشکل = خطای فنی', await page.locator($.error).isVisible());
		check('گرید حفظ شد', (await page.locator($.cell).count()) === 16);

		await ctx.unroute('**/admin-ajax.php');


	});
	await section('۱۰ نسخهٔ قرارداد', async () => {

		await visit(page, BASE);
		await ctx.route('**/admin-ajax.php', async (route) => {
			const res = await route.fetch();
			const body = await res.json();
			body.data.contract = 999;
			route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
		});

		await (await option(page, 'filter_brand|up3d')).click();
		await page.waitForSelector('.zig-archive__error:not([hidden])', { timeout: 10000 }).catch(() => {});

		check('قرارداد ناهمخوان = خطای فنی', await page.locator($.error).isVisible());
		check('نه یک رابط نیمه‌کاره', (await page.locator($.cell).count()) === 16);

		await ctx.unroute('**/admin-ajax.php');


	});
	await section('۱۱ پاسخ کهنه', async () => {

		await visit(page, BASE);

		let n = 0;
		await ctx.route('**/admin-ajax.php', async (route) => {
			n++;
			// اولی عمداً کند می‌شود تا بعد از دومی برسد
			if (n === 1) { await new Promise((r) => setTimeout(r, 2500)); }
			route.continue();
		});

		await (await option(page, 'filter_brand|up3d')).click();
		await page.waitForTimeout(400);
		await (await option(page, 'filter_axis|5-axis')).click();
		await page.waitForTimeout(4000);

		const finalQ = new URL(page.url()).searchParams;
		check('نتیجهٔ نهایی مالِ آخرین کلیک است', finalQ.has('filter_axis'), page.url().split('?')[1] || '');
		check('پاسخ کهنه آن را پس نزد', (await state(page)) !== null);

		await ctx.unroute('**/admin-ajax.php');


	});
	await section('۱۲ فوکوس', async () => {

		await visit(page, BASE);
		await (await option(page, 'filter_brand|up3d')).focus();
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
		check('لینک فیلتر href واقعی دارد', (await np.locator('.zig-facet__item [data-zig-toggle="filter_brand|up3d"]').getAttribute('href')).includes('filter_brand=up3d'));

		/*
		 * بدون جاوااسکریپت هم گروه باید باز شود — و می‌شود، چون
		 * ‎<details>‎ رفتار خودِ مرورگر است نه چیزی که ما ساخته‌ایم. ولی
		 * باید *همان* گروهی باز شود که این گزینه در آن است، نه اولی.
		 */
		await (await option(np, 'filter_brand|up3d')).click();
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

		await visit(page, BASE + '?paged=2');
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

		await visit(page, 'http://localhost:8080/zig-double/');
		const roots = await page.locator($.root).count();

		if (roots >= 2) {
			const owners = await page.evaluate((s) => Array.from(document.querySelectorAll(s)).map((r) => !!r.zigArchive?.owner), $.root);
			check('فقط یکی مالک تاریخچه است', owners.filter(Boolean).length === 1, JSON.stringify(owners));
		} else {
			check('دو ویجت روی صفحه', false, `فقط ${roots} پیدا شد — سناریو ساخته نشد`);
		}


	});
	await section('۱۶ب ساختار کارت', async () => {
		await visit(page, BASE);

		const card = page.locator('.zig-card').first();

		for (const part of ['__media', '__body', '__meta', '__text', '__foot']) {
			check(`ناحیهٔ ${part} هست`, (await card.locator('.zig-card' + part).count()) >= 1);
		}

		check('بدنه داخل کارت است', (await card.locator('.zig-card__body .zig-card__text').count()) === 1);
		check('برند و موجودی در یک ردیف‌اند', (await card.locator('.zig-card__body > .zig-card__meta').count()) === 1);
		/*
		 * ‎:scope‎ لازم است: ‎card.locator('.zig-card > …')‎ داخل زیردرختِ
		 * خودِ کارت دنبال یک ‎.zig-card‎ دیگر می‌گردد، نه خودش.
		 */
		check('پا بیرون از بدنه است', (await card.locator(':scope > .zig-card__foot').count()) === 1);

		/* خط جداکننده، border-top خودِ پاست نه یک <hr> */
		const border = await card.locator('.zig-card__foot').evaluate((el) => getComputedStyle(el).borderTopWidth);
		check('خط جداکننده روی پا نشسته', parseFloat(border) > 0, border);
		check('و <hr> جدایی در کار نیست', (await card.locator('hr').count()) === 0);

		/* پا به کف می‌چسبد: بدنه کشیده می‌شود */
		const grow = await card.locator('.zig-card__body').evaluate((el) => getComputedStyle(el).flexGrow);
		check('بدنه کشیده می‌شود تا پا به کف بچسبد', parseFloat(grow) > 0, grow);

		const cta = await card.locator('.zig-card__cta').evaluate((el) => el.getBoundingClientRect().width);
		const foot = await card.locator('.zig-card__foot').evaluate((el) => el.getBoundingClientRect().width);
		check('دکمه تمام‌عرض است', Math.abs(cta - foot) < 2, `${Math.round(cta)} / ${Math.round(foot)}`);

		/* ویژگی‌ها یک نوارند با جداکنندهٔ پنهان از صفحه‌خوان */
		const feats = page.locator('.zig-card__features').first();

		if (await feats.count()) {
			const seps = await feats.locator('.zig-card__sep').count();
			const items = await feats.locator('.zig-card__feature').count();
			check('بین هر دو ویژگی یک جداکننده', seps === Math.max(0, items - 1), `${items} ویژگی، ${seps} جداکننده`);
			check('جداکننده از صفحه‌خوان پنهان است', (await feats.locator('.zig-card__sep[aria-hidden="true"]').count()) === seps);
		}

		/* ترتیب دیداری از متغیر می‌آید، پس DOM دست‌نخورده می‌ماند */
		const order = await card.locator('.zig-card__media').evaluate((el) => getComputedStyle(el).order);
		check('ترتیب تصویر از متغیر می‌آید', order !== '' && order !== 'auto', order);
	});

	await section('۱۷ دسترس‌پذیری', async () => {

		await visit(page, BASE);
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
		await visit(page, BASE);

		const cols = await page.evaluate((s) => getComputedStyle(document.querySelector(s)).gridTemplateColumns.split(' ').length, '.zig-archive__grid');
		check('گرید سه‌ستونی روی دسکتاپ', cols === 3, String(cols));

		const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
		check('اسکرول افقی ندارد', overflow <= 0, String(overflow));

		const dir = await page.evaluate(() => getComputedStyle(document.documentElement).direction);
		check('جهت صفحه خوانده شد', !!dir, dir);


	});
	await section('۱۹ حالت بارگذاری', async () => {

		await visit(page, BASE);
		await ctx.route('**/admin-ajax.php', async (route) => {
			await new Promise((r) => setTimeout(r, 900));
			route.continue();
		});

		await (await option(page, 'filter_brand|up3d')).click();

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

		await visit(page, BASE + '?filter_brand=up3d');
		check('با یک فیلتر شروع می‌شود', (await countOf(page)) === 11);
		await (await option(page, 'filter_brand|up3d')).click();
		await settle(page);
		check('کلیک دوباره برش می‌دارد', (await countOf(page)) === 31, String(await countOf(page)));
		check('و آدرس تمیز شد', !new URL(page.url()).searchParams.has('filter_brand'), page.url().split('?')[1] || '');


	});
	await section('۲۱ گزینهٔ صفر', async () => {

		await visit(page, BASE + '?filter_brand=up3d');
		const disabled = await page.locator('.zig-facet__item.is-disabled').count();
		const total = await page.locator($.item).count();
		check('گزینه‌ها حذف نمی‌شوند', total >= 7, `${total} گزینه، ${disabled} غیرفعال`);
		check('انتخاب‌شده هیچ‌وقت غیرفعال نیست', (await page.locator('.zig-facet__item.is-selected.is-disabled').count()) === 0);


	});
	await section('۲۲ سقف ارتفاع و اسکرول پنل', async () => {

		/*
		 * دو ناحیهٔ اسکرولِ تودرتو، و آنچه قابلِ تحملشان می‌کند.
		 *
		 * بدون ‎overscroll-behavior: contain‎، کاربری که داخل فهرست برندها
		 * اسکرول می‌کند و به ته می‌رسد، ناگهان کل صفحه را زیر دستش
		 * می‌بیند. این چیزی نیست که در اسکرین‌شات دیده شود یا در تست واحد
		 * معنا داشته باشد — فقط مرورگر می‌داند.
		 */
		await visit(page, BASE);

		const box = await page.evaluate(() => {
			const card = document.querySelector('.zig-filters__card');
			const groups = document.querySelector('.zig-filters__groups');
			const list = document.querySelector('.zig-facet .zig-facet__list');
			const cs = getComputedStyle(card);
			const gs = getComputedStyle(groups);
			const ls = getComputedStyle(list);

			return {
				cardMax: parseFloat(cs.maxHeight),
				cardX: cs.overflowX,
				groupsY: gs.overflowY,
				groupsChain: gs.overscrollBehavior,
				listMax: parseFloat(ls.maxHeight),
				listY: ls.overflowY,
				listChain: ls.overscrollBehavior,
				viewport: window.innerHeight,
			};
		});

		check('پنل سقف ارتفاع دارد', box.cardMax > 0 && box.cardMax <= box.viewport, `${box.cardMax} از ${box.viewport}`);
		check('ناحیهٔ اسکرول خودِ گروه‌هاست', box.groupsY === 'auto', box.groupsY);
		check('و کارت افقی هیچ‌وقت اسکرول نمی‌گیرد', box.cardX === 'hidden');
		check('اسکرولش به صفحه سرایت نمی‌کند', box.groupsChain === 'contain');

		check('فهرست هر گروه هم سقف دارد', box.listMax > 0, String(box.listMax));
		check('و اسکرول خودش را', box.listY === 'auto');
		check('بدون سرایت به پنل', box.listChain === 'contain');

		/*
		 * ظاهرِ نوار اسکرول از دو مسیر می‌آید و هر دو باید سر جایشان باشند:
		 * خاصیت‌های استاندارد (‎scrollbar-width/-color‎) که کروم و فایرفاکسِ
		 * امروزی می‌خوانند، و شبه‌عنصرهای وبکیتی برای موتورهای قدیمی‌تر.
		 *
		 * این محیط نوار را به‌صورت روکشی می‌کشد، پس در اسکرین‌شات دیده
		 * نمی‌شود؛ تنها راه سنجیدنش پرسیدن از CSSOM است.
		 */
		const bar = await page.evaluate(() => {
			const card = getComputedStyle(document.querySelector('.zig-filters__groups'));
			const list = getComputedStyle(document.querySelector('.zig-facet__list'));
			const rules = [...document.styleSheets]
				.flatMap((s) => { try { return [...s.cssRules]; } catch { return []; } })
				.filter((r) => r.selectorText && /webkit-scrollbar/.test(r.selectorText));

			return {
				cardWidth: card.scrollbarWidth,
				listWidth: list.scrollbarWidth,
				cardColor: card.scrollbarColor,
				thumbRule: rules.some((r) => /scrollbar-thumb\b/.test(r.selectorText) && /border-radius/.test(r.style.cssText)),
				clipRule: rules.some((r) => /padding-box/.test(r.style.cssText)),
			};
		});

		check('نوار در هر دو ناحیه باریک است', bar.cardWidth === 'thin' && bar.listWidth === 'thin', `${bar.cardWidth} / ${bar.listWidth}`);
		check('و رنگش تعیین شده', /rgba?\(/.test(bar.cardColor), bar.cardColor);
		check('تیغه در مسیر وبکیتی گِرد است', bar.thumbRule);
		check('و با کادرِ شفاف از لبه فاصله می‌گیرد', bar.clipRule);


	});
	await section('۲۳ شیشهٔ سربرگ روی تکهٔ بنفش', async () => {

		/*
		 * این افکت از سه چیز ساخته می‌شود و *هر سه* لازم‌اند:
		 *
		 *   • تکهٔ بنفش پشتِ سربرگ باشد،
		 *   • کارت پس‌زمینه نداشته باشد تا جلویش را نگیرد،
		 *   • سربرگ نیمه‌شفاف باشد تا بک‌دراپ کامپوزیت شود.
		 *
		 * اگر هرکدام برود، خروجی باز هم *سالم* به نظر می‌رسد — فقط یک
		 * سربرگ سفیدِ ساده — و هیچ خطایی هم نمی‌دهد. تنها راه گرفتنش،
		 * پرسیدن از خودِ مرورگر است.
		 */
		await visit(page, BASE);

		const glass = await page.evaluate(() => {
			const outer = document.querySelector('.zig-archive__filters');
			const card = document.querySelector('.zig-filters__card');
			const head = document.querySelector('.zig-filters__head');
			const cap = getComputedStyle(outer, '::before');
			const o = outer.getBoundingClientRect();
			const c = card.getBoundingClientRect();
			const h = head.getBoundingClientRect();
			const hs = getComputedStyle(head);

			return {
				capH: parseFloat(cap.height) || 0,
				capPos: cap.position,
				cardBg: getComputedStyle(card).backgroundColor,
				blur: hs.backdropFilter || hs.webkitBackdropFilter,
				headAlpha: parseFloat((hs.backgroundColor.match(/[\d.]+\)$/) || ['1)'])[0]),
				insetStart: Math.round(c.left - o.left),
				insetEnd: Math.round(o.right - c.right),
				insetTop: Math.round(c.top - o.top),
				overlap: Math.round(o.top + (parseFloat(cap.height) || 0) - h.top),
			};
		});

		check('تکهٔ بنفش هست و مطلق است', glass.capH > 0 && glass.capPos === 'absolute', `${glass.capH}px ${glass.capPos}`);
		check('کارت پس‌زمینه ندارد تا جلوی بنفش را نگیرد', /rgba\(0, 0, 0, 0\)|transparent/.test(glass.cardBg), glass.cardBg);
		check('سربرگ واقعاً بلور دارد', /blur\(\s*[1-9]/.test(glass.blur), glass.blur || 'none');
		check('و نیمه‌شفاف است، وگرنه بلور دیده نمی‌شود', glass.headAlpha < 1, String(glass.headAlpha));
		check('بنفش پشتِ سربرگ می‌افتد', glass.overlap > 0, `${glass.overlap}px همپوشانی`);

		// اعداد از خودِ فیگما: ۱۶ دو طرف، ۲۴ از بالا
		check('تورفتگی دو طرف قرینه است', glass.insetStart === glass.insetEnd, `${glass.insetStart} / ${glass.insetEnd}`);
		check('و کارت از بالا پایین آمده', glass.insetTop > 0, `${glass.insetTop}px`);

		/*
		 * سربرگ و ردیفِ فیلترهای اعمال‌شده باید *واقعاً* سر جایشان بمانند.
		 *
		 * سنجیدنِ ‎position‎ کافی نیست: چیزی که اهمیت دارد این است که آن‌ها
		 * بیرونِ ناحیهٔ اسکرول باشند. پس گروه‌ها را اسکرول می‌کنیم و
		 * می‌بینیم سربرگ اصلاً تکان می‌خورد یا نه.
		 */
		const stuck = await page.evaluate(() => {
			const groups = document.querySelector('.zig-filters__groups');
			const head = document.querySelector('.zig-filters__head');
			document.querySelectorAll('.zig-facet').forEach((d) => d.setAttribute('open', ''));

			const was = head.getBoundingClientRect().top;
			groups.scrollTop = 240;

			return {
				scrolled: groups.scrollTop,
				moved: Math.round(head.getBoundingClientRect().top - was),
			};
		});

		check('گروه‌ها واقعاً اسکرول خوردند', stuck.scrolled > 0, `${stuck.scrolled}px`);
		check('ولی سربرگ اصلاً تکان نخورد', stuck.moved === 0, `${stuck.moved}px جابه‌جایی`);


	});
	await section('۲۴ شکستِ درخواست، وضعیت را دروغ نکند', async () => {

		/*
		 * باگی که کاربر گزارشش کرد و روی محیط سالم بازتولید نمی‌شد، چون
		 * فقط وقتی پیدا می‌شود که یک درخواست *شکست بخورد*:
		 *
		 *   کاربر روی فیلترِ فعال می‌زند تا برش دارد ← ‎params‎ همان‌جا
		 *   حذفش می‌کند و تیک برداشته می‌شود ← درخواست شکست می‌خورد ←
		 *   گرید و آدرس هنوز *با* فیلترند ولی کلاینت فکر می‌کند بدون
		 *   فیلتر ← کلیک بعدی روی همان گزینه دوباره اضافه‌اش می‌کند.
		 *
		 * از دید کاربر: «فیلتر برمی‌گردد و لغو نمی‌شود.»
		 */
		await visit(page, BASE + '?filter_brand=up3d');

		const before = await countOf(page);
		check('با فیلتر شروع می‌شود', (await page.locator($.selected).count()) === 1, `${before} محصول`);

		// فقط یک درخواست را می‌شکنیم، بعد راه را باز می‌کنیم
		let broken = 0;
		await ctx.route('**/admin-ajax.php', (route) => {
			if (broken === 0) { broken = 1; return route.abort('failed'); }

			return route.continue();
		});

		await (await option(page, 'filter_brand|up3d')).click();
		await page.waitForFunction(() => !document.querySelector('[data-zig-archive]').hasAttribute('aria-busy'), null, { timeout: 15000 });
		await page.waitForTimeout(300);

		check('جعبهٔ خطا بالا آمد', !(await page.locator($.error).isHidden()));
		check('گرید دست‌نخورده ماند', (await countOf(page)) === before, String(await countOf(page)));
		check('و تیکِ پیش‌نمایش پس گرفته شد', (await page.locator($.selected).count()) === 1);

		// حالا همان کلیک را دوباره بزن؛ این بار باید واقعاً برش دارد
		await (await option(page, 'filter_brand|up3d')).click();
		await settle(page);

		check('کلیک دوباره فیلتر را برمی‌دارد، نه اینکه برش گرداند', !new URL(page.url()).searchParams.has('filter_brand'), page.url().split('?')[1] || '(تمیز)');
		check('و شمارش به حالت بی‌فیلتر رفت', (await countOf(page)) > before, String(await countOf(page)));

		await ctx.unroute('**/admin-ajax.php');


	});
	await section('۲۵ اسلاگ غیرلاتین', async () => {

		/*
		 * اسلاگ فارسی در دیتابیس وردپرس درصدکدشده ذخیره می‌شود
		 * (‎±۳۵ درجه‎ ⇐ ‎%c2%b1%db%b3%db%b5-…‎) و ‎data-zig-toggle‎ همان را
		 * می‌آورد؛ ولی ‎URLSearchParams.get()‎ یک بار دیکد می‌کند. دو
		 * نوشتار از یک ترم.
		 *
		 * با مقایسهٔ رشته‌ایِ ساده هیچ‌وقت برابر نمی‌شدند و لغوِ فیلتر
		 * به‌جای برداشتن، دوباره اضافه‌اش می‌کرد. روی اسلاگ لاتین هرگز
		 * دیده نمی‌شد، چون آنجا دو نوشتار یکی‌اند — برای همین همهٔ
		 * سنجه‌های قبلی سبز بودند.
		 */
		const FA = '%c2%b1%db%b3%db%b5-%d8%af%d8%b1%d8%ac%d9%87';

		await visit(page, BASE);

		const exists = (await page.locator(`[data-zig-toggle="filter_b-axis|${FA}"]`).count()) > 0;

		// اگر دادهٔ نمونه این ترم را نداشته باشد، سنجه باید قرمز شود نه
		// اینکه بی‌صدا رد شود؛ وگرنه پوششِ همین باگ خاموش می‌ماند.
		check('ترمِ با اسلاگ فارسی در سایدبار هست', exists, exists ? FA : 'دادهٔ نمونه ندارد — tests/INTEGRATION.md');

		if (!exists) {
			return;
		}

		const clean = await countOf(page);

		await (await option(page, `filter_b-axis|${FA}`)).click();
		await settle(page);

		const filtered = await countOf(page);
		check('اعمال می‌شود', filtered < clean, `${clean} ⇐ ${filtered}`);
		check('و در آدرس می‌نشیند', new URL(page.url()).searchParams.has('filter_b-axis'));

		await (await option(page, `filter_b-axis|${FA}`)).click();
		await settle(page);

		check('و لغو هم می‌شود', (await countOf(page)) === clean, String(await countOf(page)));
		check('آدرس تمیز شد', !new URL(page.url()).searchParams.has('filter_b-axis'), page.url().split('?')[1] || '(تمیز)');
		check('و تیک برداشته شد', (await page.locator($.selected).count()) === 0);

		/*
		 * چیپ همان دلتا را دارد ولی مسیر جدایی است؛ وقتی یکی خراب بود،
		 * آن یکی هم بود.
		 */
		await (await option(page, `filter_b-axis|${FA}`)).click();
		await settle(page);
		await page.locator('.zig-filters__chip a').first().click();
		await settle(page);

		check('از راه چیپ هم لغو می‌شود', (await countOf(page)) === clean, String(await countOf(page)));


	});
	await section('۲۶ عنوان گروه: تمام‌عرض، سه‌حالته، با بولت', async () => {

		/*
		 * پنج ادعای دیداری که هیچ‌کدام در خروجی رندر پیدا نیستند و
		 * خرابی‌شان هم خطایی نمی‌دهد — فقط پنل کمی «بی‌ربط» می‌شود. تنها
		 * راهِ دیدنشان پرسیدن از خودِ مرورگر است.
		 */
		await visit(page, BASE + '?filter_brand=up3d');

		const box = await page.evaluate(() => {
			const outer = document.querySelector('.zig-archive__filters');
			const card = document.querySelector('.zig-filters__card');
			const groups = document.querySelector('.zig-filters__groups');
			const active = document.querySelector('.zig-facet.is-active');
			const plain = document.querySelector('.zig-facet:not(.is-active)');

			if (!active || !plain) {
				return null;
			}

			const at = active.querySelector('.zig-facet__title');
			const pt = plain.querySelector('.zig-facet__title');
			const name = active.querySelector('.zig-facet__name');

			return {
				outerBg: getComputedStyle(outer).backgroundColor,
				groupsPad: parseFloat(getComputedStyle(groups).paddingInlineStart),
				titlePad: parseFloat(getComputedStyle(at).paddingInlineStart),
				openBg: getComputedStyle(at).backgroundColor,
				closedBg: getComputedStyle(pt).backgroundColor,
				openChevron: getComputedStyle(active.querySelector('.zig-facet__chevron')).color,
				closedChevron: getComputedStyle(plain.querySelector('.zig-facet__chevron')).color,
				bullet: getComputedStyle(at, '::before').content,
				bulletW: parseFloat(getComputedStyle(at, '::before').width) || 0,
				plainBullet: getComputedStyle(pt, '::before').content,
				nameW: name.getBoundingClientRect().width,
				titleW: at.getBoundingClientRect().width,
				cardW: card.getBoundingClientRect().width,
			};
		});

		check('گروهِ فعال و غیرفعال هر دو هستند', box !== null);

		if (!box) {
			return;
		}

		const opaque = (c) => !/rgba\(0, 0, 0, 0\)|transparent/.test(c);

		check('ظرف بیرونی پس‌زمینه دارد', opaque(box.outerBg), box.outerBg);
		check('پدینگ افقی از ظرف گروه‌ها رفته', box.groupsPad === 0, `${box.groupsPad}px`);
		check('و روی خودِ عنوان نشسته', box.titlePad > 0, `${box.titlePad}px`);
		check('پس عنوان تا لبهٔ کارت می‌رود', Math.round(box.titleW) === Math.round(box.cardW), `${Math.round(box.titleW)} / ${Math.round(box.cardW)}`);

		check('حالت باز پس‌زمینهٔ خودش را دارد', opaque(box.openBg) && box.openBg !== box.closedBg, `${box.openBg} ≠ ${box.closedBg}`);
		check('فلشِ باز رنگ متفاوت می‌گیرد', box.openChevron !== box.closedChevron, `${box.openChevron} ≠ ${box.closedChevron}`);

		check('گروهِ فعال بولت دارد', box.bullet !== 'none' && box.bulletW > 0, `${box.bulletW}px`);
		check('و گروهِ بی‌فیلتر ندارد', box.plainBullet === 'none', box.plainBullet);
		check('عنوان به اندازهٔ متنش است، نه تمام‌عرض', box.nameW < box.titleW / 2, `${Math.round(box.nameW)} از ${Math.round(box.titleW)}`);

		/*
		 * هاور باید از هر دو حالتِ دیگر جدا باشد، وگرنه کاربر نمی‌فهمد
		 * گروهی که می‌بیند باز است یا فقط زیر مکان‌نماست.
		 */
		const closed = page.locator('.zig-facet:not(.is-active) .zig-facet__title').first();
		await closed.hover();
		await page.waitForTimeout(250);

		const hovered = await closed.evaluate((el) => getComputedStyle(el).backgroundColor);

		check('هاور هم رنگ خودش را دارد', opaque(hovered) && hovered !== box.closedBg, `${hovered} ≠ ${box.closedBg}`);
		check('و با حالت باز یکی نیست', hovered !== box.openBg, `${hovered} ≠ ${box.openBg}`);


	});
	await section('۲۷ سایهٔ پنل، رنگِ کلِ گروهِ فعال، و سقفِ چیپ‌ها', async () => {

		/*
		 * سه ادعای مستقل که سه راه شکستن دارند و هیچ‌کدام خطا نمی‌دهد:
		 *
		 *   • سایه روی ‎.zig-archive__filters‎ نشسته باشد نه روی کارتِ
		 *     ‎overflow: hidden‎ی داخل آن — وگرنه بریده می‌شد.
		 *   • پس‌زمینهٔ «گروهِ فعال» روی خودِ ‎.zig-facet‎ باشد، نه فقط
		 *     روی ‎.zig-facet__title‎اش — وگرنه فقط نوارِ سربرگ رنگ
		 *     می‌گرفت و بدنهٔ باز نه.
		 *   • ردیفِ چیپ‌ها با تعداد کافی واقعاً اسکرول بگیرد، نه فقط
		 *     ‎overflow-y: auto‎ داشته باشد بدون آنکه هیچ‌وقت لازم شود.
		 */
		await visit(page, BASE + '?filter_axis=5-axis');

		const shadow = await page.locator('.zig-archive__filters').evaluate((el) => getComputedStyle(el).boxShadow);

		check('پنل سایه دارد', shadow !== 'none' && shadow !== '', shadow);

		const activeBg = await page.evaluate(() => {
			const active = document.querySelector('.zig-facet.is-active');

			if (!active) {
				return null;
			}

			return {
				facet: getComputedStyle(active).backgroundColor,
				title: getComputedStyle(active.querySelector('.zig-facet__title')).backgroundColor,
			};
		});

		check('گروهِ فعال هست', activeBg !== null);

		if (activeBg) {
			check(
				'پس‌زمینهٔ خودِ گروه هم‌رنگِ سربرگش است',
				activeBg.facet === activeBg.title,
				`${activeBg.facet} / ${activeBg.title}`
			);
		}

		/*
		 * چند فیلترِ مختلفِ *دیگر* می‌زنیم تا چیپ‌ها از سقفِ ۷۰ پیکسل رد
		 * شوند — ‎filter_axis|5-axis‎ از فهرست بیرون است چون همان چیزی
		 * است که با بازکردنِ آدرس فعال شد؛ دوباره‌زدنش برش می‌داشت و
		 * سنجهٔ «گروهِ فعال هست» را زیرِ پا می‌گذاشت.
		 *
		 * دیبونسِ فیلتر ۲۵۰ میلی‌ثانیه است؛ کلیک‌های پشتِ‌سرهم را
		 * ‎settle()‎ خودش جفت‌وجور می‌کند.
		 */
		const toggles = [
			'filter_axis|3-axis',
			'filter_brand|up3d',
			'filter_brand|vhf',
			'filter_brand|roland',
			'filter_material|pmma',
		];

		for (const t of toggles) {
			const el = await option(page, t);

			if (await el.count()) {
				await el.click();
				await settle(page);
			}
		}

		const chips = await page.evaluate(() => {
			const el = document.querySelector('.zig-filters__chips');
			const cs = getComputedStyle(el);

			return {
				count: document.querySelectorAll('.zig-filters__chip').length,
				maxHeight: parseFloat(cs.maxHeight) || 0,
				overflowY: cs.overflowY,
				scrollHeight: el.scrollHeight,
				clientHeight: el.clientHeight,
			};
		});

		check('چند چیپ همزمان فعال شدند', chips.count >= 4, String(chips.count));
		check('ردیفِ چیپ‌ها سقف ارتفاع دارد', chips.maxHeight > 0, `${chips.maxHeight}px`);
		check('و اسکرول می‌گیرد', chips.overflowY === 'auto');
		check('چون محتوا واقعاً از سقف رد شده', chips.scrollHeight > chips.clientHeight, `${chips.scrollHeight} > ${chips.clientHeight}`);


	});
	check('در کل هیچ خطای جاوااسکریپتی رخ نداد', errors.length === 0, errors.slice(0, 3).join(' | '));

	await browser.close();

	const bad = results.filter((r) => !r.ok);
	console.log(`\n${results.length} سنجه، ${bad.length} ناموفق`);
	if (bad.length) { console.log(bad.map((r) => '  ✗ ' + r.name).join('\n')); }
	process.exit(bad.length ? 1 : 0);
};

run().catch((e) => { console.error('ERR', e.message); process.exit(2); });
