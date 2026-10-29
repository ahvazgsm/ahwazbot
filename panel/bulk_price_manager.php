<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/bulk_price.php';
if (!isset($pdo) || !($pdo instanceof PDO) || empty($_SESSION['user'])) {
    header('Location: login.php'); exit;
}
$check = $pdo->prepare('SELECT 1 FROM admin WHERE username = ? LIMIT 1');
$check->execute([$_SESSION['user']]);
if (!$check->fetchColumn()) { header('Location: login.php'); exit; }
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ویرایش دسته‌جمعی قیمت محصولات</title>
<link rel="stylesheet" href="css/theme.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/bulk_price.css">
<style>
.bp-standalone{max-width:1100px;margin:24px auto;padding:20px;direction:rtl;color:var(--text-main,#222)}
.bp-standalone .bp-card{padding:22px;border:1px solid var(--border-soft,#ddd);border-radius:14px;background:var(--surface-1,#fff)}
.bp-standalone h1{font-size:1.35rem;margin:0 0 12px}.bp-standalone p{line-height:1.9}
.bp-standalone .btn{cursor:pointer;border:0;border-radius:8px;padding:10px 16px;font:inherit}
.bp-standalone .btn-primary{background:var(--accent,#2563eb);color:white}
.bp-standalone .bp-warning{padding:10px 14px;border-radius:8px;background:var(--accent-soft,#f4f4f5);margin:12px 0}
</style>
</head>
<body>
<section id="container">
<?php include __DIR__ . '/header.php'; ?>
<section id="main-content"><div class="bp-standalone"><div class="bp-card">
<h1>ویرایش دسته‌جمعی قیمت محصولات</h1>
<p>محصولات را بر اساس دسته‌بندی، پنل یا نام فیلتر کنید؛ سپس افزایش یا کاهش مبلغ ثابت را برای موارد انتخاب‌شده پیش‌نمایش و تأیید کنید.</p>
<div class="bp-warning">برای ایمنی، عملیات فقط روی محصولات انتخاب‌شده انجام می‌شود. پیش از ثبت، قیمت‌های جدید را بازبینی کنید.</div>
<button type="button" data-bp-open="modal-bulk-price" class="btn btn-primary">باز کردن ابزار قیمت گروهی</button>
<p><a href="product.php">بازگشت به محصولات</a></p>
</div></div></section>
</section>
<?php echo fx_bp_modal_html('modal-bulk-price'); ?>
<script src="js/bulk_price.js" defer></script>
</body></html>
