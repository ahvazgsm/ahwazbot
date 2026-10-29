<?php
date_default_timezone_set('Asia/Tehran');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

$stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
$stmt->execute([':username' => $_SESSION['user'] ?? '']);
if (empty($_SESSION['user']) || !$stmt->fetch(PDO::FETCH_ASSOC)) {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403); exit('درخواست نامعتبر است.');
    }
    // Store only non-sensitive integration preferences. Never store bank passwords/OTP here.
    $enabled = isset($_POST['enabled']) ? '1' : '0';
    $interval = (int)($_POST['interval'] ?? 60);
    if (!in_array($interval, [60, 300, 600], true)) $interval = 60;
    $settings = [
        'blu_monitor_enabled' => $enabled,
        'blu_monitor_interval' => (string)$interval,
    ];
    foreach ($settings as $key => $value) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM setting WHERE NameSetting = :k");
        try {
            $q->execute([':k'=>$key]);
            if ((int)$q->fetchColumn() > 0) {
                $u = $pdo->prepare("UPDATE setting SET ValueSetting = :v WHERE NameSetting = :k");
                $u->execute([':v'=>$value, ':k'=>$key]);
            } else {
                $i = $pdo->prepare("INSERT INTO setting (NameSetting, ValueSetting) VALUES (:k, :v)");
                $i->execute([':k'=>$key, ':v'=>$value]);
            }
        } catch (Throwable $e) {
            // Schema differs across installations; the page remains safe and explains that settings weren't saved.
            $notice = 'ساختار جدول تنظیمات این نسخه متفاوت است؛ تنظیمات ذخیره نشد. با پشتیبانی فنی بررسی شود.';
            error_log('[blu-panel] settings save failed: '.$e->getMessage());
            break;
        }
    }
    if ($notice === '') $notice = 'تنظیمات پنل ذخیره شد. دریافت خودکار تراکنش‌ها تا زمان اتصال منبع معتبر هنوز فعال نیست.';
}
$enabled = '0'; $interval = '60';
try {
    $q = $pdo->query("SELECT NameSetting, ValueSetting FROM setting WHERE NameSetting IN ('blu_monitor_enabled','blu_monitor_interval')");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['NameSetting'] === 'blu_monitor_enabled') $enabled = (string)$row['ValueSetting'];
        if ($row['NameSetting'] === 'blu_monitor_interval') $interval = (string)$row['ValueSetting'];
    }
} catch (Throwable $e) {}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>مدیریت حساب بلو</title><link rel="stylesheet" href="css/bootstrap.min.css"><link rel="stylesheet" href="css/style.css">
<style>body{font-family:Tahoma,Arial;background:#f5f7fb}.blu-wrap{max-width:980px;margin:35px auto;padding:0 15px}.blu-card{background:#fff;border:1px solid #e5e9f0;border-radius:14px;padding:24px;margin-bottom:18px;box-shadow:0 4px 18px #15223a0a}.blu-card h3{margin-top:0}.muted{color:#687386;line-height:2}.status{display:inline-block;padding:6px 12px;border-radius:20px;background:#fff2d8;color:#8a5b00}.form-control{max-width:420px}.btn-primary{margin-top:12px}</style></head><body>
<div class="blu-wrap">
<h2>مدیریت حساب بلو</h2>
<p class="muted">تنظیمات اولیه اتصال حساب بلو و وضعیت سرویس بررسی تراکنش‌ها.</p>
<?php if ($notice): ?><div class="alert alert-info"><?php echo htmlspecialchars($notice,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?>
<div class="blu-card"><h3>وضعیت اتصال</h3><span class="status">اتصال خودکار هنوز راه‌اندازی نشده</span>
<p class="muted">این صفحه فعلاً گزینه‌های مدیریتی را فراهم می‌کند. برای دریافت واقعی تراکنش‌ها باید روش مجاز و پایدار خواندن تراکنش‌ها از وب‌اپ بلو بررسی و جداگانه پیاده‌سازی شود. تا آن زمان هیچ سفارشی بر اساس این صفحه به‌صورت خودکار تأیید نمی‌شود.</p>
</div>
<div class="blu-card"><h3>تنظیمات بررسی</h3><form method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
<label><input type="checkbox" name="enabled" value="1" <?php echo $enabled==='1'?'checked':''; ?>> فعال‌سازی بررسی تراکنش‌ها (پس از نصب سرویس اتصال)</label>
<div style="margin-top:18px"><label for="interval">فاصله بررسی</label><select class="form-control" id="interval" name="interval">
<option value="60" <?php echo $interval==='60'?'selected':''; ?>>هر ۱ دقیقه</option>
<option value="300" <?php echo $interval==='300'?'selected':''; ?>>هر ۵ دقیقه</option>
<option value="600" <?php echo $interval==='600'?'selected':''; ?>>هر ۱۰ دقیقه</option>
</select></div>
<button class="btn btn-primary" type="submit">ذخیره تنظیمات</button></form>
</div>
<div class="blu-card"><h3>امنیت</h3><p class="muted">رمز ورود بلو و کد یک‌بارمصرف را در این پنل یا دیتابیس ربات ذخیره نکنید. اگر ورود مجدد یا تأیید امنیتی لازم باشد، سرویس باید متوقف شود تا مدیر آن را انجام دهد.</p></div>
<p><a href="index.php">بازگشت به پنل</a></p>
</div></body></html>
