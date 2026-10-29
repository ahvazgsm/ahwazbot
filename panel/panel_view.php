<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';

$adminStmt = $pdo->prepare('SELECT username FROM admin WHERE username = :username LIMIT 1');
$adminStmt->execute([':username' => $_SESSION['user'] ?? '']);
if (empty($_SESSION['user']) || !$adminStmt->fetchColumn()) { header('Location: login.php'); exit; }
if (empty($_SESSION['_panel_view_csrf'])) $_SESSION['_panel_view_csrf'] = bin2hex(random_bytes(32));
function pv_h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function pv_label($v): string { $v = trim((string)$v); return $v !== '' ? $v : '—'; }
$code = trim((string)($_GET['code'] ?? $_POST['code_panel'] ?? ''));
$msg = ''; $err = '';
$panel = null;
$load = $pdo->prepare('SELECT * FROM marzban_panel WHERE code_panel = :code LIMIT 1');
$load->execute([':code' => $code]);
$panel = $load->fetch(PDO::FETCH_ASSOC) ?: null;
// قابلیت‌های قابل نمایش برای هر نوع پنل؛ تنظیمات هر رکورد مستقل ذخیره می‌شود.
$pvType = strtolower(trim((string)($panel['type'] ?? 'marzban')));
$pvFeatureSets = [
    'marzban' => ['test','extend','location','inbound','vip','config','sublink','connect'],
    'pasarguard' => ['test','extend','location','inbound','vip','config','sublink','connect'],
    'marzneshin' => ['test','extend','location','inbound','vip','config','sublink','connect'],
    'x-ui_single' => ['test','extend','inbound','config','sublink','connect'],
    'alireza_single' => ['test','extend','inbound','config','sublink','connect'],
    'hiddify' => ['test','extend','config','sublink','connect'],
    'wgdashboard' => ['test','extend','connect'],
    's_ui' => ['test','extend','config','sublink','connect'],
    'ibsng' => ['test','extend','connect'],
    'mikrotik' => ['test','extend','connect'],
    'manualsale' => [],
];
$pvFeatureLabels = ['test'=>'حساب تست','extend'=>'تمدید سرویس','location'=>'تغییر موقعیت','inbound'=>'مدیریت اینباند','vip'=>'اشتراک ویژه','config'=>'نمایش کانفیگ','sublink'=>'لینک اشتراک','connect'=>'فعال‌بودن اتصال'];
$pvFeatureKeys = ['test'=>'TestAccount','extend'=>'status_extend','location'=>'changeloc','inbound'=>'inboundstatus','vip'=>'subvip','config'=>'config','sublink'=>'sublink','connect'=>'conecton'];
$pvFeatureOptions = ['test'=>['ONTestAccount'=>'فعال','OFFTestAccount'=>'غیرفعال'],'extend'=>['on_extend'=>'فعال','off_extend'=>'غیرفعال'],'location'=>['onchangeloc'=>'فعال','offchangeloc'=>'غیرفعال'],'inbound'=>['oninbounddisable'=>'فعال','offinbounddisable'=>'غیرفعال'],'vip'=>['onsubvip'=>'فعال','offsubvip'=>'غیرفعال'],'config'=>['onconfig'=>'فعال','offconfig'=>'غیرفعال'],'sublink'=>['onsublink'=>'فعال','offsublink'=>'غیرفعال'],'connect'=>['onconecton'=>'فعال','offconecton'=>'غیرفعال']];
$pvApplicableFeatures = $pvFeatureSets[$pvType] ?? ['test','extend','config','sublink','connect'];
if (!$panel) { http_response_code(404); }
if ($panel && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals((string)$_SESSION['_panel_view_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('درخواست نامعتبر است؛ صفحه را تازه‌سازی کنید.');
    }
    $name = trim((string)($_POST['name_panel'] ?? ''));
    $url = rtrim(trim((string)($_POST['url_panel'] ?? '')), '/');
    $agent = trim((string)($_POST['agent'] ?? 'all'));
    $username = trim((string)($_POST['username_panel'] ?? ''));
    $password = trim((string)($_POST['password_panel'] ?? ''));
    $methodUsername = trim((string)($_POST['MethodUsername'] ?? ((($panel['type'] ?? '') === 'ahvaz_app') ? 'random' : 'آیدی عددی + حروف و عدد رندوم')));
    $isAhvazAppPanel = (($panel['type'] ?? '') === 'ahvaz_app');
    $status = (string)($_POST['status'] ?? 'inactive');
    $testAccount = (string)($_POST['TestAccount'] ?? ($panel['TestAccount'] ?? 'OFFTestAccount'));
    $statusExtend = (string)($_POST['status_extend'] ?? ($panel['status_extend'] ?? 'off_extend'));
    $changeLoc = (string)($_POST['changeloc'] ?? ($panel['changeloc'] ?? 'offchangeloc'));
    $inboundStatus = (string)($_POST['inboundstatus'] ?? ($panel['inboundstatus'] ?? 'offinbounddisable'));
    $subVip = (string)($_POST['subvip'] ?? ($panel['subvip'] ?? 'offsubvip'));
    $configStatus = (string)($_POST['config'] ?? ($panel['config'] ?? 'offconfig'));
    $subLinkStatus = (string)($_POST['sublink'] ?? ($panel['sublink'] ?? 'offsublink'));
    $connectStatus = (string)($_POST['conecton'] ?? ($panel['conecton'] ?? 'offconecton'));
    $allowedMethods = $isAhvazAppPanel ? ['random','custom'] : ['آیدی عددی + حروف و عدد رندوم','نام کاربری + حروف و عدد رندوم','نام کاربری دلخواه + عدد رندوم','متن دلخواه کاربر + رندوم','متن دلخواه + عدد رندوم','متن دلخواه + عدد ترتیبی','نام کاربری + عدد به ترتیب','آیدی عددی+عدد ترتیبی','متن دلخواه نماینده + عدد ترتیبی','نام کاربری دلخواه','آیدی عددی'];
    if ($name === '' || mb_strlen($name) > 200) $err = 'نام پنل الزامی است و حداکثر ۲۰۰ نویسه می‌تواند باشد.';
    elseif ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url))) $err = 'آدرس پنل باید URL معتبر با http یا https باشد.';
    elseif (!in_array($status, ['active','inactive'], true)) $err = 'وضعیت انتخاب‌شده معتبر نیست.';
    elseif (!in_array($methodUsername, $allowedMethods, true)) $err = 'روش ساخت نام کاربری معتبر نیست.';
    elseif (!in_array($testAccount, ['ONTestAccount','OFFTestAccount'], true) || !in_array($statusExtend, ['on_extend','off_extend'], true) || !in_array($changeLoc, ['onchangeloc','offchangeloc'], true) || !in_array($inboundStatus, ['oninbounddisable','offinbounddisable'], true) || !in_array($subVip, ['onsubvip','offsubvip'], true) || !in_array($configStatus, ['onconfig','offconfig'], true) || !in_array($subLinkStatus, ['onsublink','offsublink'], true) || !in_array($connectStatus, ['onconecton','offconecton'], true)) $err = 'یکی از وضعیت‌های قابلیت پنل معتبر نیست.';
    elseif ($agent === '' || mb_strlen($agent) > 200) $err = 'مقدار نماینده معتبر نیست.';
    else {
        $dupe = $pdo->prepare('SELECT COUNT(*) FROM marzban_panel WHERE name_panel = :name AND code_panel <> :code');
        $dupe->execute([':name'=>$name, ':code'=>$code]);
        if ((int)$dupe->fetchColumn() > 0) $err = 'این نام برای پنل دیگری استفاده شده است.';
        else {
            try {
                $pdo->beginTransaction();
                $save = $pdo->prepare('UPDATE marzban_panel SET name_panel = :name, url_panel = :url, linksubx = :linksubx, agent = :agent, username_panel = :username, password_panel = :password, MethodUsername = :method_username, status = :status, TestAccount = :test_account, status_extend = :status_extend, changeloc = :changeloc, inboundstatus = :inboundstatus, subvip = :subvip, config = :config, sublink = :sublink, conecton = :conecton WHERE code_panel = :code');
                $save->execute([':name'=>$name, ':url'=>$url, ':linksubx'=>$url, ':agent'=>$agent, ':username'=>$username, ':password'=>$password, ':method_username'=>$methodUsername, ':status'=>$status, ':test_account'=>$testAccount, ':status_extend'=>$statusExtend, ':changeloc'=>$changeLoc, ':inboundstatus'=>$inboundStatus, ':subvip'=>$subVip, ':config'=>$configStatus, ':sublink'=>$subLinkStatus, ':conecton'=>$connectStatus, ':code'=>$code]);
                if ((string)$panel['name_panel'] !== $name) {
                    try { $prod = $pdo->prepare('UPDATE product SET Location = :new WHERE Location = :old'); $prod->execute([':new'=>$name, ':old'=>$panel['name_panel']]); } catch (Throwable $ignored) {}
                }
                $pdo->commit();
                $msg = 'تغییرات پنل ذخیره شد.';
                $load->execute([':code'=>$code]); $panel = $load->fetch(PDO::FETCH_ASSOC) ?: $panel;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[panel_view] save panel: '.$e->getMessage());
                $err = 'ذخیره تغییرات انجام نشد؛ گزارش خطای سرور را بررسی کنید.';
            }
        }
    }
}
$productCount = 0;
if ($panel) { try { $c = $pdo->prepare('SELECT COUNT(*) FROM product WHERE Location = :name'); $c->execute([':name'=>(string)$panel['name_panel']]); $productCount=(int)$c->fetchColumn(); } catch (Throwable $e) {} }
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title><?php echo $panel ? pv_h($panel['name_panel']) : 'پنل پیدا نشد'; ?> | Ahwaz-GSM</title><link rel="stylesheet" href="css/theme.css?v=flat50"><link rel="stylesheet" href="css/admin-extra.css?v=flat33"><link rel="stylesheet" href="css/components.css?v=flat33"><script src="js/theme.js?v=panelview2" defer></script><style>
.pv-wrap{max-width:1100px;margin:22px auto;padding:0 16px}.pv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(270px,100%),1fr));gap:14px;margin:18px 0}.pv-card{padding:18px;border:1px solid var(--border-soft);border-radius:16px;background:var(--surface-1,var(--surface-2))}.pv-card h3{margin:0 0 12px;font-size:15px}.pv-row{display:flex;justify-content:space-between;gap:14px;padding:10px 0;border-bottom:1px solid var(--border-soft);overflow-wrap:anywhere}.pv-row:last-child{border-bottom:0}.pv-key{color:var(--text-muted);flex:0 0 110px}.pv-value{font-weight:600;min-width:0;overflow-wrap:anywhere}.pv-field{margin:12px 0}.pv-field label{display:block;font-weight:700;margin-bottom:6px}.pv-field input,.pv-field select{box-sizing:border-box;width:100%;padding:11px;border:1px solid var(--border-soft);border-radius:10px;background:var(--surface-2);color:inherit}.pv-actions{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.pv-alert{padding:12px 14px;border-radius:10px;margin:12px 0}.pv-ok{background:#e5f7eb;color:#14532d}.pv-err{background:#fee2e2;color:#991b1b}.pv-status{display:inline-flex;padding:4px 10px;border-radius:999px;background:var(--surface-2);font-size:12px}@media(max-width:600px){.pv-card{padding:14px}.pv-row{gap:8px}.pv-key{flex-basis:90px}}
</style></head><body><section id="container"><?php include __DIR__ . '/header.php'; ?><section id="main-content"><div class="pv-wrap">
<?php if (!$panel): ?><div class="page-head"><div><div class="page-head__title">پنل پیدا نشد</div><div class="page-head__sub">این پنل در دیتابیس وجود ندارد.</div></div></div><a class="btn btn-primary" href="index.php">بازگشت به داشبورد</a>
<?php else: ?>
<div class="page-head"><div><div class="page-head__title"><?php echo icon('server', 'svg-icon svg-lg'); ?><?php echo pv_h($panel['name_panel'] ?? 'پنل'); ?></div><div class="page-head__sub">اطلاعات و تنظیمات فقط برای پنل انتخاب‌شده</div></div><span class="pv-status"><?php echo (($panel['status'] ?? '') === 'active') ? 'فعال در ربات' : 'غیرفعال در ربات'; ?></span></div>
<?php if ($msg): ?><div class="pv-alert pv-ok"><?php echo pv_h($msg); ?></div><?php endif; ?><?php if ($err): ?><div class="pv-alert pv-err"><?php echo pv_h($err); ?></div><?php endif; ?>
<div class="pv-grid"><div class="pv-card"><h3>اطلاعات پنل</h3><div class="pv-row"><span class="pv-key">نام پنل</span><span class="pv-value"><?php echo pv_h(pv_label($panel['name_panel'] ?? '')); ?></span></div><div class="pv-row"><span class="pv-key">نوع پنل</span><span class="pv-value"><?php echo pv_h(pv_label($panel['type'] ?? '')); ?></span></div><div class="pv-row"><span class="pv-key">روش ساخت نام کاربری</span><span class="pv-value"><?php echo pv_h(pv_label($panel['MethodUsername'] ?? '')); ?></span></div><?php foreach ($pvApplicableFeatures as $feature): $key=$pvFeatureKeys[$feature]; $onValue=array_key_first($pvFeatureOptions[$feature]); ?><div class="pv-row"><span class="pv-key"><?php echo pv_h($pvFeatureLabels[$feature]); ?></span><span class="pv-value"><?php echo (($panel[$key] ?? '')===$onValue)?'فعال':'غیرفعال'; ?></span></div><?php endforeach; ?><div class="pv-row"><span class="pv-key">شناسه</span><span class="pv-value"><?php echo pv_h(pv_label($panel['code_panel'] ?? '')); ?></span></div><div class="pv-row"><span class="pv-key">نماینده</span><span class="pv-value"><?php echo pv_h(pv_label($panel['agent'] ?? '')); ?></span></div><div class="pv-row"><span class="pv-key">تعداد محصولات</span><span class="pv-value"><?php echo number_format($productCount); ?></span></div><div class="pv-row"><span class="pv-key">آدرس پنل</span><span class="pv-value"><?php echo !empty($panel['url_panel']) ? '<a target="_blank" rel="noopener noreferrer" href="'.pv_h($panel['url_panel']).'">'.pv_h($panel['url_panel']).'</a>' : '—'; ?></span></div></div>
<div class="pv-card"><h3>ویرایش پنل</h3><p>تغییر نام پنل، آدرس، وضعیت نمایش در دکمه‌های ربات و نماینده از اینجا انجام می‌شود. غیرفعال‌کردن پنل، آن را از فهرست پنل‌های فعال ربات حذف می‌کند.</p><form method="post"><input type="hidden" name="csrf" value="<?php echo pv_h($_SESSION['_panel_view_csrf']); ?>"><input type="hidden" name="code_panel" value="<?php echo pv_h($panel['code_panel']); ?>"><div class="pv-field"><label for="name_panel">نام پنل</label><input id="name_panel" name="name_panel" maxlength="200" required value="<?php echo pv_h($panel['name_panel'] ?? ''); ?>"></div><div class="pv-field"><label for="url_panel">آدرس پنل</label><input id="url_panel" name="url_panel" type="url" placeholder="https://example.com" value="<?php echo pv_h($panel['url_panel'] ?? ''); ?>"></div><div class="pv-field"><label for="username_panel">نام کاربری ورود پنل</label><input id="username_panel" name="username_panel" maxlength="200" autocomplete="off" value="<?php echo pv_h($panel['username_panel'] ?? ''); ?>"></div><div class="pv-field"><label for="password_panel">رمز عبور / توکن پنل</label><input id="password_panel" name="password_panel" type="password" maxlength="200" autocomplete="new-password" value="<?php echo pv_h($panel['password_panel'] ?? ''); ?>"><small>برای پنل‌هایی که به‌جای رمز عبور از توکن یا API Key استفاده می‌کنند، مقدار فعلی همین فیلد ذخیره می‌شود.</small><button class="btn" type="button" onclick="const f=document.getElementById('password_panel');f.type=f.type==='password'?'text':'password';">نمایش / مخفی‌کردن</button></div><div class="pv-field"><label for="MethodUsername">روش ساخت نام کاربری اکانت‌ها</label><select id="MethodUsername" name="MethodUsername"><?php if ($pvType === 'ahvaz_app'): ?><option value="random" <?php echo (($panel['MethodUsername'] ?? 'random')==='random')?'selected':''; ?>>رندوم خودکار (نام کاربری و رمز عبور خودکار)</option><option value="custom" <?php echo (($panel['MethodUsername'] ?? '')==='custom')?'selected':''; ?>>نام کاربری دلخواه مشتری + رمز تصادفی</option><?php else: $methods=['آیدی عددی + حروف و عدد رندوم','نام کاربری + حروف و عدد رندوم','نام کاربری دلخواه + عدد رندوم','متن دلخواه کاربر + رندوم','متن دلخواه + عدد رندوم','متن دلخواه + عدد ترتیبی','نام کاربری + عدد به ترتیب','آیدی عددی+عدد ترتیبی','متن دلخواه نماینده + عدد ترتیبی','نام کاربری دلخواه','آیدی عددی']; foreach($methods as $method): ?><option value="<?php echo pv_h($method); ?>" <?php echo (($panel['MethodUsername'] ?? '')===$method)?'selected':''; ?>><?php echo pv_h($method); ?></option><?php endforeach; endif; ?></select><?php if ($pvType === 'ahvaz_app'): ?><small>این گزینه‌ها مخصوص پنل اپ AhvazVPN هستند و با روش ساخت نام کاربری پنل‌های VPN دیگر تفاوت دارند.</small><?php endif; ?></div><h4>قابلیت‌های اختصاصی این نوع پنل</h4><p>این فهرست با توجه به نوع همین پنل نمایش داده می‌شود؛ تغییرات فقط روی همین پنل ذخیره می‌شوند.</p><?php if (!$pvApplicableFeatures): ?><div class="pv-alert">برای نوع «فروش دستی» قابلیت فنی پنل نمایش داده نمی‌شود.</div><?php else: foreach ($pvApplicableFeatures as $feature): $key=$pvFeatureKeys[$feature]; $current=(string)($panel[$key] ?? array_key_first($pvFeatureOptions[$feature])); ?><div class="pv-field"><label for="<?php echo pv_h($key); ?>"><?php echo pv_h($pvFeatureLabels[$feature]); ?></label><select id="<?php echo pv_h($key); ?>" name="<?php echo pv_h($key); ?>"><?php foreach ($pvFeatureOptions[$feature] as $value=>$label): ?><option value="<?php echo pv_h($value); ?>" <?php echo $current===$value?'selected':''; ?>><?php echo pv_h($label); ?></option><?php endforeach; ?></select></div><?php endforeach; endif; ?><div class="pv-field"><label for="agent">نماینده / دسترسی ربات</label><input id="agent" name="agent" maxlength="200" required value="<?php echo pv_h($panel['agent'] ?? 'all'); ?>"><small>برای نمایش عمومی در ربات معمولاً مقدار all استفاده می‌شود؛ مقدار فعلی را فقط در صورت نیاز تغییر بده.</small></div><div class="pv-field"><label for="status">وضعیت</label><select id="status" name="status"><option value="active" <?php echo (($panel['status'] ?? '')==='active')?'selected':''; ?>>فعال — نمایش در ربات</option><option value="inactive" <?php echo (($panel['status'] ?? '')!=='active')?'selected':''; ?>>غیرفعال — مخفی از دکمه‌های ربات</option></select></div><button class="btn btn-primary" type="submit">ذخیره تغییرات</button></form></div></div>
<div class="pv-actions"><a class="btn btn-primary" href="product.php">مدیریت محصولات</a><a class="btn" href="stock.php">انبار شبکه ملی</a><a class="btn" href="manual_service.php?panel=<?php echo rawurlencode((string)$panel['code_panel']); ?>">فروش دستی</a><a class="btn" href="index.php">داشبورد</a></div>
<?php endif; ?></div></section></section></body></html>
