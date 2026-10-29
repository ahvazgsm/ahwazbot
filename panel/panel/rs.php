<?php
require_once __DIR__ . '/../config.php';
$mysqli = new mysqli("localhost", $usernamedb, $passworddb, $dbname);

error_reporting(E_ALL);
ini_set('display_errors', 1);
// خطاهای mysqli رو exception کنه (که سفید نشه)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli->set_charset('utf8mb4');

try {

    // ساخت جدول بکاپ با id
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS user_balance_backup (
            id BIGINT PRIMARY KEY,
            balance DECIMAL(20,2)
        )
    ");

    // صفر کردن
    if (isset($_POST['reset_all'])) {

        $mysqli->query("TRUNCATE TABLE user_balance_backup");

        $mysqli->query("
            INSERT INTO user_balance_backup (id, balance)
            SELECT id, Balance FROM user
        ");

        $mysqli->query("UPDATE user SET Balance = 0");

        $msg = "✅ همه موجودی‌ها صفر شد";
    }

    // بازگردانی
    if (isset($_POST['restore_all'])) {

        $mysqli->query("
            UPDATE user u
            JOIN user_balance_backup b ON u.id = b.id
            SET u.Balance = b.balance
        ");

        $msg = "♻️ موجودی‌ها برگشت";
    }

} catch (Throwable $e) {
    // هر خطایی باشه اینجا نمایش میده (دیگه صفحه سفید نمی‌مونه)
    die("❌ ERROR: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مدیریت موجودی</title>
<style>
body{background:#0f172a;color:#fff;font-family:sans-serif;text-align:center;padding:30px}
.box{background:#1e293b;padding:20px;border-radius:15px;max-width:400px;margin:auto}
button{width:100%;padding:15px;margin:10px 0;border:none;border-radius:10px;color:#fff;font-size:14px;cursor:pointer}
.btn-reset{background:#ef4444}.btn-restore{background:#22c55e}
.msg{background:#334155;padding:10px;border-radius:10px;margin-bottom:10px}
</style>
</head>
<body>
<div class="box">
<h3>💰 مدیریت موجودی کاربران</h3>
<h2>🤖اختصاصی parsconn_bot</h2>
<?php if(isset($msg)): ?>
<div class="msg"><?= $msg ?></div>
<?php endif; ?>

<form method="post">
    <button type="submit" name="reset_all" class="btn-reset">🚨 صفر کردن کل موجودی</button>
</form>

<form method="post">
    <button type="submit" name="restore_all" class="btn-restore">♻️ بازگردانی موجودی</button>
</form>

</div>
</body>
</html>