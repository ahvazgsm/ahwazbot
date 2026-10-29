<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
$auth = $pdo->prepare('SELECT username FROM admin WHERE username = :username LIMIT 1');
$auth->execute([':username' => $_SESSION['user']]);
if (!$auth->fetch(PDO::FETCH_ASSOC)) { header('Location: login.php'); exit; }
if (empty($_SESSION['csrf_import_button'])) $_SESSION['csrf_import_button'] = bin2hex(random_bytes(32));
$presets = ['#1d5837','#2563eb','#7c3aed','#db2777','#ea580c','#0891b2','#dc2626','#111827','#d4a72c'];
$message = '';
$color = '#1d5837';
try {
    // Ensure the setting exists as a real column instead of relying on a helper's implicit schema changes.
    $columns = $pdo->query("SHOW COLUMNS FROM `setting`")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('import_button_color', $columns, true)) {
        $pdo->exec("ALTER TABLE `setting` ADD COLUMN `import_button_color` VARCHAR(7) NOT NULL DEFAULT '#1d5837'");
    }
    $stmt = $pdo->query("SELECT `import_button_color` FROM `setting` LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$row['import_button_color'])) $color = strtolower($row['import_button_color']);
} catch (Throwable $e) {
    error_log('Import button color read/schema error: '.$e->getMessage());
    $message = 'خطا در خواندن تنظیم رنگ از دیتابیس؛ دسترسی ALTER/SELECT جدول setting را بررسی کنید.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    $newColor = strtolower(trim((string)($_POST['color'] ?? '')));
    if (!hash_equals($_SESSION['csrf_import_button'], $token)) {
        $message = 'درخواست معتبر نیست؛ صفحه را تازه‌سازی کنید.';
    } elseif (!preg_match('/^#[0-9a-f]{6}$/', $newColor)) {
        $message = 'کد رنگ معتبر نیست.';
    } else {
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM `setting`")->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('import_button_color', $columns, true)) {
                $pdo->exec("ALTER TABLE `setting` ADD COLUMN `import_button_color` VARCHAR(7) NOT NULL DEFAULT '#1d5837'");
            }
            $stmt = $pdo->prepare("UPDATE `setting` SET `import_button_color` = :color");
            $stmt->execute([':color' => $newColor]);
            $verify = $pdo->query("SELECT `import_button_color` FROM `setting` LIMIT 1")->fetchColumn();
            if (!is_string($verify) || strtolower($verify) !== $newColor) throw new RuntimeException('Color verification failed');
            $color = $newColor;
            $message = 'رنگ با موفقیت در دیتابیس ذخیره و تأیید شد. صفحه افزودن اشتراک را دوباره بارگذاری کنید.';
        } catch (Throwable $e) {
            error_log('Unable to save import button color: ' . $e->getMessage());
            $message = 'ذخیره رنگ ناموفق بود. مجوز ALTER/UPDATE جدول setting و گزارش خطای PHP را بررسی کنید.';
        }
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>رنگ دکمه افزودن یک‌کلیکی</title><link href="css/bootstrap.min.css" rel="stylesheet"><link href="css/style.css" rel="stylesheet"><link href="css/style-responsive.css" rel="stylesheet"><style>
body{font-family:Tahoma,Arial,sans-serif}.color-wrap{max-width:900px;margin:24px auto;padding:0 14px}.color-card{background:#fff;border:1px solid #e6e9ec;border-radius:18px;padding:24px;box-shadow:0 8px 30px #172b4d0b}.color-title{font-size:22px;font-weight:800;margin:0 0 8px}.muted{color:#667085;line-height:1.9}.color-row{display:flex;gap:24px;align-items:center;flex-wrap:wrap;margin:24px 0}.color-picker{width:74px;height:58px;padding:4px;border:1px solid #d0d5dd;border-radius:12px;background:#fff;cursor:pointer}.hex-input{max-width:180px;direction:ltr;text-align:left}.swatches{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0}.swatch{width:34px;height:34px;border:2px solid #fff;outline:1px solid #d0d5dd;border-radius:50%;cursor:pointer}.swatch:focus{outline:3px solid #64748b}.preview{border:1px dashed #d0d5dd;border-radius:16px;padding:22px;background:#f8fafc;margin-top:18px}.preview-btn{display:block;text-align:center;color:#fff!important;background:var(--button-color);padding:14px 18px;border-radius:13px;font-weight:800;font-size:16px;text-decoration:none;box-shadow:0 8px 18px color-mix(in srgb,var(--button-color),transparent 72%);max-width:440px}.save-btn{background:#172b4d;color:#fff;border:0;border-radius:10px;padding:12px 22px;font-weight:bold}.notice{padding:12px 15px;background:#ecfdf3;color:#067647;border-radius:10px;margin:16px 0}.warning{padding:12px 15px;background:#fffaeb;color:#92400e;border-radius:10px;margin-top:18px;line-height:1.9}@media(max-width:600px){.color-card{padding:17px}.color-title{font-size:19px}}
</style></head><body><section id="container"><?php include __DIR__ . '/header.php'; ?><section id="main-content"><section class="wrapper"><div class="color-wrap"><div class="color-card"><h1 class="color-title">🎨 رنگ دکمهٔ «افزودن با یک کلیک»</h1><p class="muted">رنگ دکمه‌های انتخاب اپ در صفحهٔ افزودن اشتراک را از این بخش تنظیم کنید. تغییرات در دیتابیس ذخیره می‌شوند و نیازی به ویرایش دستی فایل نیست.</p><?php if ($message !== ''): ?><div class="notice"><?=htmlspecialchars($message, ENT_QUOTES, 'UTF-8')?></div><?php endif; ?><form method="post" id="colorForm"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_import_button'], ENT_QUOTES, 'UTF-8')?>"><div class="color-row"><div><label for="colorPicker"><strong>انتخاب رنگ</strong></label><br><input class="color-picker" type="color" id="colorPicker" value="<?=htmlspecialchars($color, ENT_QUOTES, 'UTF-8')?>"></div><div><label for="colorHex"><strong>کد HEX</strong></label><br><input class="form-control hex-input" type="text" id="colorHex" name="color" value="<?=htmlspecialchars($color, ENT_QUOTES, 'UTF-8')?>" pattern="#[0-9a-fA-F]{6}" maxlength="7" required></div></div><strong>رنگ‌های آماده</strong><div class="swatches"><?php foreach ($presets as $preset): ?><button type="button" class="swatch" data-color="<?=$preset?>" title="<?=$preset?>" aria-label="<?=$preset?>" style="background:<?=$preset?>"></button><?php endforeach; ?></div><div class="preview"><p class="muted">پیش‌نمایش زنده</p><a href="#preview" class="preview-btn" id="previewBtn">📲 افزودن اشتراک به اپلیکیشن</a></div><p style="margin-top:22px"><button class="save-btn" type="submit">ذخیره رنگ</button> <a href="index.php" style="margin-right:12px">بازگشت به پنل</a></p></form><div class="warning"><strong>نکته:</strong> تلگرام برای دکمه‌های داخل خود ربات، API عمومی جهت تعیین رنگ اختصاصی هر دکمه ندارد. این تنظیم، رنگ دکمه‌های داخل صفحهٔ افزودن یک‌کلیکی را تغییر می‌دهد؛ ظاهر دکمهٔ inline داخل چت تلگرام قابل‌رنگ‌آمیزی از سمت ربات نیست.</div></div></div></section></section></section><script>
const picker=document.getElementById('colorPicker'),hex=document.getElementById('colorHex'),preview=document.getElementById('previewBtn');function setColor(v){if(!/^#[0-9a-fA-F]{6}$/.test(v))return;picker.value=v;hex.value=v;preview.style.setProperty('--button-color',v)}picker.addEventListener('input',()=>setColor(picker.value));hex.addEventListener('input',()=>{if(/^#[0-9a-fA-F]{6}$/.test(hex.value))setColor(hex.value)});document.querySelectorAll('[data-color]').forEach(b=>b.addEventListener('click',()=>setColor(b.dataset.color)));setColor(hex.value);
</script></body></html>
