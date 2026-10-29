<?php require_once __DIR__.'/bootstrap.php'; $u=ag_user(); ?>
<!doctype html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AHWAZ-GSM | سرویس هوشمند</title>
<meta name="theme-color" content="#0b1020">
<link rel="stylesheet" href="assets/style.css">
<script>
window.AG = {
  logged: <?= $u ? 'true' : 'false' ?>,
  bot: "ahwazgsm14_bot"
};
</script>
</head><body>
<div class="bg"></div>
<header><a class="brand" href="#"><span class="logo">AG</span><b>AHWAZ-GSM</b></a>
<nav><a href="#features">امکانات</a><a href="#plans">پلن‌ها</a><a href="#support">پشتیبانی</a></nav>
<div class="actions" id="auth"></div></header>

<main>
<section class="hero">
<div class="hero-copy"><span class="eyebrow">⚡ سریع • امن • حرفه‌ای</span>
<h1>اتصال سریع، امن و<br><em>بدون محدودیت</em></h1>
<p>سرویس‌های حرفه‌ای AHWAZ-GSM با مدیریت آسان، پنل کاربری مدرن و پشتیبانی در کنار شما هستند.</p>
<div class="hero-actions"><a class="btn primary" href="#plans">خرید سرویس</a><button class="btn ghost" onclick="AG.openDashboard()">ورود به پنل</button></div>
<div class="stats"><div><b>۲۴/۷</b><span>پشتیبانی</span></div><div><b>⚡</b><span>فعال‌سازی سریع</span></div><div><b>🛡</b><span>مدیریت امن</span></div></div></div>
<div class="shield"><div class="orb">AG</div><div class="rings"></div></div>
</section>

<section id="features"><h2>چرا AHWAZ-GSM؟</h2><div class="grid four">
<article>⚡<h3>سرعت بالا</h3><p>سرویس‌های پایدار برای استفاده روزمره.</p></article>
<article>🛡️<h3>مدیریت امن</h3><p>حساب، سرویس و اطلاعات شما در یک پنل.</p></article>
<article>☁️<h3>اتصال پایدار</h3><p>مدیریت متمرکز سرویس‌ها و سرورها.</p></article>
<article>🎧<h3>پشتیبانی</h3><p>دسترسی سریع به راه‌های ارتباطی.</p></article>
</div></section>

<section id="plans"><div class="section-head"><div><h2>پلن‌های موجود</h2><p>اطلاعات واقعی از دیتابیس ربات شما بارگذاری می‌شود.</p></div><select id="panelSelect"><option value="">انتخاب سرور برای خرید</option></select></div><div class="grid plans" id="products"><div class="loading">در حال دریافت سرویس‌ها…</div></div></section>

<section id="dashboard" class="dashboard hidden"><div class="dash-head"><div><span class="eyebrow">پنل کاربری</span><h2 id="welcome">خوش آمدید</h2></div><button class="btn ghost" onclick="AG.closeDashboard()">بستن</button></div>
<div class="grid metrics"><article><span>کیف پول</span><b id="balance">۰ تومان</b></article><article><span>سرویس‌های من</span><b id="serviceCount">۰</b></article><article><span>زیرمجموعه‌ها</span><b id="affiliateCount">۰</b></article></div>
<div class="panel-block"><h3>سرویس‌های من</h3><div id="services"></div></div>
<div class="panel-block"><h3>تراکنش‌های اخیر</h3><div id="payments"></div></div>
</section>

<section id="support" class="support"><h2>پشتیبانی همیشه در دسترس است</h2><p>برای مدیریت سفارش و دریافت راهنمایی از ربات یا پشتیبانی استفاده کنید.</p><a class="btn primary" id="botLink" target="_blank">ورود به ربات</a></section>
</main>
<footer><div class="brand"><span class="logo">AG</span><b>AHWAZ-GSM</b></div><span>پنل کاربری متصل به سیستم اصلی ربات</span><span>© <?=date('Y')?> AHWAZ-GSM</span></footer>

<div class="modal hidden" id="loginModal"><div class="modal-card"><button class="x" onclick="AG.hideLogin()">×</button><h2>ورود به حساب</h2><p>با حساب تلگرام خود وارد شوید.</p>
<div id="tgLogin"></div><p class="hint">در موبایل، اگر سایت را از Mini App باز کنید ورود خودکار انجام می‌شود.</p></div></div>
<div class="toast hidden" id="toast"></div>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script src="assets/app.js?v=20260904"></script>
</body></html>
