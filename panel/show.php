<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
$mysqli = new mysqli("localhost", $usernamedb, $passworddb, $dbname);

if ($mysqli->connect_error) {
    die("خطا اتصال: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");

// حذف رکورد با POST (امن‌تر)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $stmt = $mysqli->prepare("DELETE FROM manualsell WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: manage_manualsell.php");
    exit;
}

// گرفتن داده‌ها با JOIN
$result = $mysqli->query("
    SELECT m.*, p.name_product 
    FROM manualsell m
    LEFT JOIN product p ON m.codeproduct = p.code_product
    ORDER BY m.id DESC
");

// شمارش موجودی
$countResult = $mysqli->query("
    SELECT m.codeproduct, p.name_product,
    SUM(m.status='active') as active_count,
    SUM(m.status='sold') as sold_count
    FROM manualsell m
    LEFT JOIN product p ON m.codeproduct = p.code_product
    GROUP BY m.codeproduct
");

$counts = [];
while($row = $countResult->fetch_assoc()){
    $counts[] = $row;
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مدیریت انبار</title>
<h2>🤖اختصاصی parsconn_bot</h2>
<style>
* { box-sizing: border-box; }

body {
    background: #0f172a;
    color: white;
    font-family: sans-serif;
    margin: 0;
    padding: 10px;
}

/* کارت‌ها */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.card {
    background: #1e293b;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
    font-size: 13px;
}

.active { color: #22c55e; }
.sold { color: #ef4444; }

/* جدول */
.table-container {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #1e293b;
    border-radius: 10px;
    overflow: hidden;
}

th, td {
    padding: 8px;
    text-align: center;
    font-size: 12px;
}

th {
    background: #334155;
}

tr:nth-child(even) {
    background: #0f172a;
}

/* کانفیگ */
.config {
    max-width: 200px;
    overflow-x: auto;
    font-size: 10px;
    direction: ltr;
}

/* دکمه */
.btn-delete {
    background: #ef4444;
    border: none;
    padding: 6px 8px;
    border-radius: 6px;
    color: white;
    cursor: pointer;
    font-size: 11px;
}

.btn-delete:hover {
    background: #dc2626;
}

/* موبایل */
@media (max-width: 600px) {
    th, td {
        font-size: 10px;
        padding: 6px;
    }

    .config {
        max-width: 150px;
    }
}
</style>

</head>
<body>

<h3 style="text-align:center;">📦 مدیریت انبار</h3>

<!-- آمار -->
<div class="stats">
<?php foreach($counts as $c): ?>
<div class="card">
    <div><b><?= $c['name_product'] ?? $c['codeproduct'] ?></b></div>
    <div class="active">🟢 <?= $c['active_count'] ?></div>
    <div class="sold">🔴 <?= $c['sold_count'] ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- جدول -->
<div class="table-container">
<table>

<tr>
<th>ID</th>
<th>محصول</th>
<th>پنل</th>
<th>status</th>
<th>کانفیگ</th>
<th>حذف</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>

<td><?= $row['id'] ?></td>

<td><?= $row['name_product'] ?? $row['codeproduct'] ?></td>

<td><?= $row['codepanel'] ?></td>

<td class="<?= $row['status']=='active' ? 'active' : 'sold' ?>">
<?= $row['status'] ?>
</td>

<td class="config"><?= htmlspecialchars($row['contentrecord']) ?></td>

<td>
<form method="post" onsubmit="return confirm('حذف شود؟')">
<input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
<button type="submit" class="btn-delete">حذف</button>
</form>
</td>

</tr>
<?php endwhile; ?>

</table>
</div>

</body>
</html>