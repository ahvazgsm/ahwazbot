<?php
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); exit('Database unavailable'); }
$check = $pdo->prepare('SELECT username FROM admin WHERE username = :username LIMIT 1');
$check->execute([':username' => $_SESSION['user'] ?? '']);
if (!$check->fetchColumn()) { header('Location: login.php'); exit; }
if (empty($_SESSION['_panel_management_csrf'])) $_SESSION['_panel_management_csrf'] = bin2hex(random_bytes(32));
function pm_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$msg = ''; $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals((string)$_SESSION['_panel_management_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('درخواست نامعتبر است؛ صفحه را تازه‌سازی کنید.');
    }
    $code = trim((string)($_POST['code_panel'] ?? ''));
    $action = (string)($_POST['action'] ?? 'save');
    $q = $pdo->prepare('SELECT * FROM marzban_panel WHERE code_panel = :code LIMIT 1');
    $q->execute([':code' => $code]);
    $panel = $q->fetch(PDO::FETCH_ASSOC);
    if (!$panel) {
        $err = 'پنل انتخاب‌شده پیدا نشد.';
    } elseif ($action === 'toggle') {
        $status = (($panel['status'] ?? 'active') === 'active') ? 'inactive' : 'active';
        $u = $pdo->prepare('UPDATE marzban_panel SET status = :status WHERE code_panel = :code');
        $u->execute([':status' => $status, ':code' => $code]);
        $msg = $status === 'active' ? 'پنل روشن شد.' : 'پنل خاموش شد.';
    } elseif ($action === 'save_auth') {
        $usernamePanel = trim((string)($_POST['username_panel'] ?? ''));
        $passwordPanel = (string)($_POST['password_panel'] ?? '');
        $apiKey = trim((string)($_POST['api_key'] ?? ''));
        if (mb_strlen($usernamePanel) > 200 || mb_strlen($passwordPanel) > 1000 || mb_strlen($apiKey) > 4000) {
            $err = 'طول یکی از فیلدهای ورود یا کلید API بیش از حد مجاز است.';
        } else {
            try {
                // Empty password/key fields preserve existing secrets; secrets are never rendered back into HTML.
                $sql = 'UPDATE marzban_panel SET username_panel = :username';
                $params = [':username' => ($usernamePanel !== '' ? $usernamePanel : (string)($panel['username_panel'] ?? '')), ':code' => $code];
                if ($passwordPanel !== '') { $sql .= ', password_panel = :password'; $params[':password'] = $passwordPanel; }
                if ($apiKey !== '') { $sql .= ', api_key = :api_key'; $params[':api_key'] = $apiKey; }
                $sql .= ' WHERE code_panel = :code';
                $u = $pdo->prepare($sql);
                $u->execute($params);
                $msg = 'اطلاعات ورود و کلید API ذخیره شد. فیلدهای رمز و کلید خالی، مقدار قبلی را حفظ می‌کنند.';
            } catch (Throwable $e) {
                error_log('Panel credentials save failed: ' . $e->getMessage());
                $err = 'ذخیره اطلاعات ورود انجام نشد؛ ستون‌های username_panel، password_panel و api_key را بررسی کنید.';
            }
        }
    } elseif ($action === 'save') {
        $name = trim((string)($_POST['name_panel'] ?? ''));
        $url = rtrim(trim((string)($_POST['url_panel'] ?? '')), '/');
        $download = trim((string)($_POST['app_download_url'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $err = 'نام پنل الزامی است و باید حداکثر ۱۰۰ نویسه باشد.';
        } elseif ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url))) {
            $err = 'آدرس پنل باید یک URL معتبر با http:// یا https:// باشد.';
        } elseif ($download !== '' && (!filter_var($download, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $download))) {
            $err = 'لینک دانلود باید یک URL معتبر با http:// یا https:// باشد؛ برای حذف لینک، فیلد را خالی بگذارید.';
        } else {
            $dupe = $pdo->prepare('SELECT COUNT(*) FROM marzban_panel WHERE name_panel = :name AND code_panel <> :code');
            $dupe->execute([':name' => $name, ':code' => $code]);
            if ((int)$dupe->fetchColumn() > 0) {
                $err = 'این نام برای پنل دیگری استفاده شده است.';
            } else {
                $cfg = json_decode((string)($panel['config'] ?? ''), true);
                if (!is_array($cfg)) $cfg = [];
                if ($download === '') unset($cfg['app_download_url']); else $cfg['app_download_url'] = $download;
                $pdo->beginTransaction();
                try {
                    $u = $pdo->prepare('UPDATE marzban_panel SET name_panel = :name, url_panel = :url, linksubx = :url2, config = :config WHERE code_panel = :code');
                    $u->execute([':name'=>$name, ':url'=>$url, ':url2'=>$url, ':config'=>json_encode($cfg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ':code'=>$code]);
                    if ((string)$panel['name_panel'] !== $name) {
                        try { $p = $pdo->prepare('UPDATE product SET Location = :new_name WHERE Location = :old_name'); $p->execute([':new_name'=>$name, ':old_name'=>$panel['name_panel']]); } catch (Throwable $ignored) {}
                    }
                    $pdo->commit();
                    $msg = 'تنظیمات پنل ذخیره شد.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Panel management save failed: ' . $e->getMessage());
                    $err = 'ذخیره تغییرات انجام نشد؛ گزارش خطای سرور را بررسی کنید.';
                }
            }
        }
    }
}
try {
    $panels = $pdo->query('SELECT * FROM marzban_panel ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $panels = []; $err = $err ?: 'خواندن فهرست پنل‌ها انجام نشد؛ جدول marzban_panel را بررسی کنید.'; }
$selectedCode = trim((string)($_GET['edit'] ?? $_POST['code_panel'] ?? ''));
$selected = null;
foreach ($panels as $p) if ((string)$p['code_panel'] === $selectedCode) { $selected = $p; break; }
if (!$selected && $panels) $selected = $panels[0];
$selectedConfig = $selected ? json_decode((string)($selected['config'] ?? ''), true) : [];
if (!is_array($selectedConfig)) $selectedConfig = [];
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>مدیریت پنل‌ها</title><link rel="stylesheet" href="css/theme.css?v=panel-management-1"><style>
.pm-wrap{max-width:1200px;margin:24px auto;padding:0 16px}.pm-card{background:var(--surface-1,#fff);border:1px solid var(--border,#ddd);border-radius:18px;padding:20px;margin-bottom:18px}.pm-grid{display:grid;grid-template-columns: minmax(230px,.8fr) minmax(300px,1.4fr);gap:18px}.pm-list{display:flex;flex-direction:column;gap:8px}.pm-item{display:block;text-decoration:none;color:inherit;border:1px solid var(--border,#ddd);border-radius:12px;padding:12px}.pm-item.active{border-color:var(--accent,#087f5b);box-shadow:0 0 0 1px var(--accent,#087f5b) inset}.pm-muted{opacity:.72;font-size:13px;overflow-wrap:anywhere}.pm-status{display:inline-block;padding:4px 9px;border-radius:20px;font-size:12px;background:#e8f8ee;color:#126333}.pm-status.off{background:#fff0f0;color:#9c2222}.pm-field{margin:14px 0}.pm-field label{display:block;font-weight:700;margin-bottom:7px}.pm-field input{box-sizing:border-box;width:100%;padding:12px;border:1px solid var(--border,#ccc);border-radius:10px;background:var(--surface-2,#fff);color:inherit}.pm-btn{display:inline-block;border:0;border-radius:10px;padding:11px 16px;background:var(--accent,#087f5b);color:#fff;font-weight:700;cursor:pointer;text-decoration:none}.pm-btn.secondary{background:#596579}.pm-alert{padding:12px 15px;border-radius:11px;margin-bottom:14px}.pm-ok{background:#e8f8ee;color:#126333}.pm-err{background:#fff0f0;color:#9c2222}@media(max-width:760px){.pm-grid{grid-template-columns:1fr}.pm-card{padding:15px}}
</style></head><body><section id="container"><?php include __DIR__ . '/header.php'; ?><main id="main-content"><div class="pm-wrap"><div class="pm-card"><h2>🗂 مدیریت پنل‌ها</h2><p>از این صفحه می‌توانید پنل‌های ثبت‌شده در ربات را انتخاب کنید، نام و آدرس آن‌ها را ویرایش کنید، لینک دانلود اپ را تنظیم کنید و وضعیت روشن/خاموش فروش را تغییر دهید.</p><p class="pm-muted">این صفحه اطلاعات اتصال محرمانه را نمایش نمی‌دهد. خاموش‌کردن پنل، وضعیت فروش آن را در ربات غیرفعال می‌کند و سرویس سرور را متوقف نمی‌کند.</p></div>
<div class="pm-card"><h3>دسترسی سریع</h3><p><a class="pm-btn secondary" href="stock.php">📦 مدیریت انبارها</a> <a class="pm-btn secondary" href="panel_management.php">🗂 مدیریت پنل‌ها</a></p><p class="pm-muted">تنظیمات ورود مرزبان و کلید API برای هر پنل در فرم جداگانه پایین صفحه ذخیره می‌شود.</p></div>
<?php if ($msg): ?><div class="pm-alert pm-ok"><?php echo pm_h($msg); ?></div><?php endif; ?><?php if ($err): ?><div class="pm-alert pm-err"><?php echo pm_h($err); ?></div><?php endif; ?>
<div class="pm-grid"><div class="pm-card"><h3>پنل‌های ثبت‌شده (<?php echo count($panels); ?>)</h3><?php if (!$panels): ?><p>فهرست پنل‌ها از جدول فعلی marzban_panel خوانده می‌شود؛ اگر این فهرست خالی است، اتصال دیتابیس یا جدول پنل‌ها را بررسی کنید.</p><?php else: ?><div class="pm-list"><?php foreach ($panels as $p): ?><a class="pm-item <?php echo $selected && (string)$selected['code_panel']===(string)$p['code_panel']?'active':''; ?>" href="panel_management.php?edit=<?php echo rawurlencode((string)$p['code_panel']); ?>"><strong><?php echo pm_h($p['name_panel'] ?? 'بدون نام'); ?></strong><br><span class="pm-muted">نوع: <?php echo pm_h($p['type'] ?? 'نامشخص'); ?></span><br><span class="pm-status <?php echo (($p['status'] ?? 'active')==='active')?'':'off'; ?>"><?php echo (($p['status'] ?? 'active')==='active')?'روشن / فروش فعال':'خاموش / فروش غیرفعال'; ?></span></a><?php endforeach; ?></div><?php endif; ?></div>
<div class="pm-card"><h3>ویرایش پنل</h3><?php if (!$selected): ?><p>برای ویرایش، ابتدا پنلی را از فهرست انتخاب کنید.</p><?php else: ?><p class="pm-muted">کد پنل: <code><?php echo pm_h($selected['code_panel']); ?></code> · نوع: <b><?php echo pm_h($selected['type'] ?? 'نامشخص'); ?></b></p><form method="post"><input type="hidden" name="csrf" value="<?php echo pm_h($_SESSION['_panel_management_csrf']); ?>"><input type="hidden" name="code_panel" value="<?php echo pm_h($selected['code_panel']); ?>"><input type="hidden" name="action" value="save"><div class="pm-field"><label for="name_panel">نام پنل</label><input id="name_panel" name="name_panel" maxlength="100" required value="<?php echo pm_h($selected['name_panel'] ?? ''); ?>"></div><div class="pm-field"><label for="url_panel">آدرس پنل / بک‌اند</label><input id="url_panel" name="url_panel" type="url" placeholder="https://example.com" value="<?php echo pm_h($selected['url_panel'] ?? ''); ?>"><div class="pm-muted">آدرس فعلی پنل؛ مقدار خالی فقط در صورتی بگذارید که این نوع پنل به آدرس نیاز ندارد.</div></div><div class="pm-field"><label for="app_download_url">لینک دانلود اپ (اختیاری)</label><input id="app_download_url" name="app_download_url" type="url" placeholder="https://example.com/app.apk" value="<?php echo pm_h($selectedConfig['app_download_url'] ?? ''); ?>"><div class="pm-muted">برای حذف لینک، فیلد را خالی بگذارید. این مقدار در تنظیمات JSON پنل ذخیره می‌شود.</div></div><button class="pm-btn" type="submit">ذخیره تغییرات</button></form><form method="post" style="margin-top:12px" onsubmit="return confirm('وضعیت پنل تغییر کند؟');"><input type="hidden" name="csrf" value="<?php echo pm_h($_SESSION['_panel_management_csrf']); ?>"><input type="hidden" name="code_panel" value="<?php echo pm_h($selected['code_panel']); ?>"><input type="hidden" name="action" value="toggle"><button class="pm-btn secondary" type="submit"><?php echo (($selected['status'] ?? 'active')==='active')?'🔌 خاموش کردن پنل':'🔌 روشن کردن پنل'; ?></button></form>
<div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--border,#ddd)"><h3>🔐 مدیریت یوزر، پسورد مرزبان و API Key</h3><p class="pm-muted">برای امنیت، رمز عبور و کلید ذخیره‌شده نمایش داده نمی‌شوند. اگر رمز یا کلید را تغییر نمی‌دهید، فیلد آن را خالی بگذارید.</p><form method="post"><input type="hidden" name="csrf" value="<?php echo pm_h($_SESSION['_panel_management_csrf']); ?>"><input type="hidden" name="code_panel" value="<?php echo pm_h($selected['code_panel']); ?>"><input type="hidden" name="action" value="save_auth"><div class="pm-field"><label for="username_panel">نام کاربری مرزبان</label><input id="username_panel" name="username_panel" maxlength="200" autocomplete="off" value="<?php echo pm_h($selected['username_panel'] ?? ''); ?>"></div><div class="pm-field"><label for="password_panel">رمز عبور مرزبان (در صورت تغییر وارد کنید)</label><input id="password_panel" name="password_panel" type="password" maxlength="1000" autocomplete="new-password" placeholder="برای حفظ رمز فعلی خالی بگذارید"></div><div class="pm-field"><label for="api_key">کلید API / Key</label><input id="api_key" name="api_key" type="password" maxlength="4000" autocomplete="new-password" placeholder="برای حفظ کلید فعلی خالی بگذارید"></div><button class="pm-btn" type="submit">ذخیره اطلاعات ورود و کلید</button></form></div><?php endif; ?></div></div></div></main></section></body></html>
