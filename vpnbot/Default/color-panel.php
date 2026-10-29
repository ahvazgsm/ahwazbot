<?php
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
$dir = __DIR__;
$configFile = $dir . '/button-colors.json';
// ورود با همان نشست پنل اصلی؛ نیازی به رمز جداگانه برای رنگ‌بندی نیست.
if (empty($_SESSION['user'])) {
    header('Location: ../../panel/login.php');
    exit;
}
if (empty($_SESSION['vpnbot_csrf'])) $_SESSION['vpnbot_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['vpnbot_csrf'];
$notice = '';
$allowed = ['primary', 'success', 'danger'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    exit('درخواست نامعتبر است؛ صفحه را تازه‌سازی کنید.');
}
$config = is_file($configFile) ? json_decode((string)file_get_contents($configFile), true) : [];
if (!is_array($config)) $config = [];
$replyButtonList = [
    'buy' => 'منوی ثابت: خرید اشتراک',
    'test' => 'منوی ثابت: سرویس تست',
    'my_service' => 'منوی ثابت: سرویس‌های من',
    'wallet' => 'منوی ثابت: کیف پول / حساب کاربری',
    'support' => 'منوی ثابت: پشتیبانی',
    'admin' => 'منوی ثابت: پنل مدیریت',
    'back' => 'منوی ثابت: بازگشت / منوی اصلی',
];
$buttonList = [
    'main_buy' => 'خرید اشتراک',
    'main_test' => 'سرویس تست',
    'backorder' => 'سرویس‌های من',
    'account' => 'حساب کاربری / کیف پول',
    'main_support' => 'پشتیبانی',
    'admin' => 'پنل مدیریت',
    'choosepaymentmethod' => 'انتخاب روش پرداخت',
    'AddBalance' => 'افزایش موجودی',
    'backuser' => 'بازگشت به منوی اصلی',
    'customsellvolume' => 'حجم دلخواه',
    'main_menu' => 'منوی اصلی',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    foreach (['primary','success','danger'] as $role) {
        $value = (string)($_POST[$role] ?? '');
        if (in_array($value, $allowed, true)) $config[$role] = $value;
    }
    $config['buttons'] = is_array($config['buttons'] ?? null) ? $config['buttons'] : [];
    $config['reply_buttons'] = is_array($config['reply_buttons'] ?? null) ? $config['reply_buttons'] : [];
    foreach ($buttonList as $key => $label) {
        $value = (string)($_POST['btn_'.$key] ?? '');
        if (in_array($value, $allowed, true)) $config['buttons'][$key] = $value;
    }
    foreach ($replyButtonList as $key => $label) {
        $value = (string)($_POST['reply_'.$key] ?? '');
        if (in_array($value, $allowed, true)) $config['reply_buttons'][$key] = $value;
    }
    $saved = @file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($saved === false) $notice = 'خطا در ذخیره تنظیمات؛ دسترسی نوشتن فایل button-colors.json را بررسی کنید.';
    else { @chmod($configFile, 0640); $notice = 'تنظیمات ذخیره شد. برای اعمال، ربات را یک بار تست کنید.'; }
}
$labels = ['primary'=>'آبی','success'=>'سبز','danger'=>'قرمز'];
function colorSelect($name, $current, $labels) { echo '<select name="'.htmlspecialchars($name,ENT_QUOTES,'UTF-8').'">'; foreach ($labels as $value=>$label) echo '<option value="'.$value.'"'.($current===$value?' selected':'').'>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</option>'; echo '</select>'; }
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>پنل مدیریت رنگ دکمه‌ها</title><style>
*{box-sizing:border-box}body{font-family:tahoma,Arial;background:radial-gradient(circle at top right,#172554,#07111f 65%);color:#f8fafc;margin:0;padding:22px}.wrap{max-width:980px;margin:auto}.hero,.card{background:rgba(15,30,52,.94);border:1px solid #24466b;border-radius:20px;padding:22px;margin:14px 0;box-shadow:0 12px 35px #0004}.hero{border-color:#c99b22;background:linear-gradient(120deg,#14243a,#172554,#172033)}h1{margin:0 0 8px;color:#facc15}.note{color:#cbd5e1;line-height:2}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}label{display:block;margin:12px 0 8px}.item{border:1px solid #234363;background:#0b1a2d;border-radius:14px;padding:14px}.item strong{display:block;margin-bottom:8px}select,button{width:100%;padding:12px;border:1px solid #355477;border-radius:12px;font:inherit}select{background:#0b1728;color:#fff}button{background:linear-gradient(100deg,#facc15,#f59e0b);color:#111827;font-weight:bold;margin-top:20px;cursor:pointer}.swatches{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.pill{padding:10px 14px;border-radius:12px;color:#fff;border:1px solid #ffffff55}.blue{background:#2563eb}.green{background:#16a34a}.red{background:#dc2626}.yellow{background:linear-gradient(135deg,#facc15,#d97706);color:#171717}.glass{background:linear-gradient(135deg,#ffffff25,#2563eb22);backdrop-filter:blur(12px);border:1px solid #ffffff55;box-shadow:inset 0 1px #ffffff55,0 0 18px #38bdf833}.notice{padding:12px;border-radius:10px;background:#14532d;color:#dcfce7;line-height:1.8}.warning{border-right:4px solid #facc15;padding:10px 14px;background:#facc1514;border-radius:8px;line-height:2}a{color:#93c5fd}
</style></head><body><div class="wrap"><div class="hero"><h1>🛡️ Ahwaz-GSM | پنل رنگ دکمه‌ها</h1><div class="note">تنظیم رنگ دکمه‌های ثابت پایین چت و دکمه‌های داخل پیام‌ها به‌صورت جداگانه.</div></div>
<div class="card"><h2>🎨 پیش‌نمایش رنگ‌ها</h2><div class="swatches"><span class="pill blue">آبی</span><span class="pill green">سبز</span><span class="pill red">قرمز</span><span class="pill yellow">زرد طلایی – فقط پیش‌نمایش پنل</span><span class="pill glass">✨ شیشه‌ای – فقط پیش‌نمایش پنل</span></div><div class="warning">تلگرام Bot API 9.4 به بعد رنگ دکمه‌های ثابت و دکمه‌های داخل پیام را با سه سبک استاندارد آبی، سبز و قرمز پشتیبانی می‌کند. رنگ زرد دلخواه و افکت شیشه‌ای واقعی از طریق Bot API قابل تنظیم نیستند؛ رنگ‌های پنل وب فقط پیش‌نمایش هستند.</div></div>
<form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8')?>"><div class="card"><h2>رنگ‌های پیش‌فرض دکمه‌های داخل پیام</h2><div class="grid"><div class="item"><label>دکمه‌های عادی</label><?php colorSelect('primary',$config['primary']??'primary',$labels); ?></div><div class="item"><label>تأیید / موفقیت</label><?php colorSelect('success',$config['success']??'success',$labels); ?></div><div class="item"><label>بازگشت / لغو</label><?php colorSelect('danger',$config['danger']??'danger',$labels); ?></div></div></div>
<div class="card"><h2>📌 رنگ دکمه‌های ثابت پایین چت</h2><p class="note">این بخش روی Reply Keyboard ثابت اعمال می‌شود؛ برای مشاهده رنگ‌ها از نسخه جدید تلگرام استفاده کنید.</p><div class="grid"><?php foreach ($replyButtonList as $key=>$label): $default = in_array($key,['buy','wallet','support'],true)?'success':($key==='back'?'danger':'primary'); ?><div class="item"><strong><?=htmlspecialchars($label,ENT_QUOTES,'UTF-8')?></strong><label>رنگ دکمه</label><?php colorSelect('reply_'.$key,$config['reply_buttons'][$key]??$default,$labels); ?></div><?php endforeach; ?></div></div>
<div class="card"><h2>🧩 رنگ جداگانه هر دکمه داخل پیام‌ها</h2><div class="grid"><?php foreach ($buttonList as $key=>$label): $default = ($key==='admin'||$key==='backuser')?'danger':(($key==='main_buy'||$key==='account'||$key==='AddBalance')?'success':'primary'); ?><div class="item"><strong><?=htmlspecialchars($label,ENT_QUOTES,'UTF-8')?></strong><label>رنگ دکمه</label><?php colorSelect('btn_'.$key,$config['buttons'][$key]??$default,$labels); ?></div><?php endforeach; ?></div><button name="save" value="1">💾 ذخیره همه تنظیمات</button><?php if ($notice !== ''): ?><p class="notice"><?=htmlspecialchars($notice,ENT_QUOTES,'UTF-8')?></p><?php endif; ?></div></form>
<div class="card note"><b>منوی اصلی ثابت و رنگی:</b> منوی اصلی با Reply Keyboard در پایین چت باقی می‌ماند و رنگ استاندارد آبی/سبز/قرمز از تنظیمات بالا اعمال می‌شود. رنگ زرد اختصاصی و افکت شیشه‌ای واقعی توسط Telegram Bot API پشتیبانی نمی‌شوند.<br><a href="../../panel/vpnbot.php">بازگشت به پنل مدیریت ربات‌ها</a></div></div></body></html>
