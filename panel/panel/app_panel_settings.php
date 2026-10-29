<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/csrf.php';
$q=$pdo->prepare('SELECT * FROM admin WHERE username=:u'); $q->execute([':u'=>$_SESSION['user']??'']);
if (!$q->fetch(PDO::FETCH_ASSOC)) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    fx_csrf_guard();
    $code=trim((string)($_POST['code_panel']??''));
    $url=trim((string)($_POST['url_panel']??''));
    $key=trim((string)($_POST['bridge_key']??''));
    $mode=(string)($_POST['username_mode']??'random');
    if (!in_array($mode,['random','custom'],true)) $mode='random';
    if (!filter_var($url,FILTER_VALIDATE_URL) || stripos($url,'https://')!==0) $error='آدرس بک‌اند باید URL معتبر HTTPS باشد.';
    elseif (strlen($key)<24) $error='کلید اتصال باید حداقل ۲۴ نویسه باشد.';
    else {
      $stmt=$pdo->prepare("UPDATE marzban_panel SET url_panel=:url, linksubx=:url, password_panel=:key, MethodUsername=:mode, config=:config WHERE code_panel=:code AND type='ahvaz_app'");
      $stmt->execute([':url'=>rtrim($url,'/'),':key'=>$key,':mode'=>$mode,':config'=>json_encode(['integration'=>'ahvaz_app','username_mode'=>$mode],JSON_UNESCAPED_UNICODE),':code'=>$code]);
      if ($stmt->rowCount()>=0) $success='تنظیمات ذخیره شد.';
    }
}
$panels=$pdo->query("SELECT code_panel,name_panel,url_panel,password_panel,MethodUsername FROM marzban_panel WHERE type='ahvaz_app' ORDER BY name_panel")->fetchAll(PDO::FETCH_ASSOC);
$_csrf=fx_csrf_token();
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تنظیمات پنل اپ AhvazVPN</title><link rel="stylesheet" href="css/theme.css?v=flat50"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"><script src="js/theme.js?v=flat50" defer></script></head><body><section id="container"><?php include 'header.php'; ?><section id="main-content"><section class="wrapper"><div class="page-head"><div><div class="page-head__title">تنظیمات پنل اپ AhvazVPN</div><div class="page-head__sub">آدرس API، کلید اتصال و روش ساخت اطلاعات ورود</div></div></div>
<?php if(!empty($error)): ?><div class="alert alert-danger"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif; ?><?php if(!empty($success)): ?><div class="alert alert-success"><?=htmlspecialchars($success,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
<?php if(!$panels): ?><div class="card"><div class="card__body">هنوز پنل اپ اختصاصی اضافه نشده است. در ربات برو به «افزودن پنل» و «اپ اختصاصی AhvazVPN» را انتخاب کن.</div></div><?php endif; ?>
<?php foreach($panels as $panel): ?><div class="card" style="margin-bottom:18px"><div class="card__head"><div class="card__title">📱 <?=htmlspecialchars($panel['name_panel'],ENT_QUOTES,'UTF-8')?></div><span class="chip">AhvazVPN App</span></div><div class="card__body"><form method="post"><?=fx_csrf_field()?> <input type="hidden" name="code_panel" value="<?=htmlspecialchars($panel['code_panel'],ENT_QUOTES,'UTF-8')?>"><div class="form-group"><label class="form-label">آدرس بک‌اند (HTTPS)</label><input class="form-control" type="url" name="url_panel" value="<?=htmlspecialchars($panel['url_panel']??'',ENT_QUOTES,'UTF-8')?>" placeholder="https://example.com/ahvaz_backend" required></div><div class="form-group"><label class="form-label">کلید اتصال</label><input class="form-control" type="password" name="bridge_key" value="<?=htmlspecialchars($panel['password_panel']??'',ENT_QUOTES,'UTF-8')?>" minlength="24" autocomplete="new-password" required></div><div class="form-group"><label class="form-label">روش ساخت نام کاربری و رمز عبور</label><select class="form-control" name="username_mode"><option value="random" <?=($panel['MethodUsername']??'')==='random'?'selected':''?>>رندوم خودکار</option><option value="custom" <?=($panel['MethodUsername']??'')==='custom'?'selected':''?>>نام کاربری دلخواه مشتری + رمز تصادفی</option></select></div><button class="btn btn-primary" type="submit">ذخیره تنظیمات</button></form></div></div><?php endforeach; ?></section></section></section></body></html>
