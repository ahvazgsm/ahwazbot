<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
$mysqli = new mysqli("localhost", $usernamedb, $passworddb, $dbname);


if ($mysqli->connect_error) {
    die("خطا اتصال: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");

// تولید namerecord یونیک
function generateUniqueNumber($mysqli) {
    do {
        $num = rand(10000000, 99999999);
        $check = $mysqli->query("SELECT id FROM manualsell WHERE namerecord = '$num'");
    } while ($check && $check->num_rows > 0);

    return $num;
}

// گرفتن محصولات (نام + کد)
$products = $mysqli->query("SELECT code_product, name_product FROM product");

// گرفتن پنل‌ها
$panels = $mysqli->query("SELECT code_panel FROM marzban_panel");

$success = 0;

if (isset($_POST['submit'])) {

    $codepanel   = $_POST['codepanel'];
    $codeproduct = $_POST['codeproduct'];
    $username    = $_POST['username'];
    $status      = isset($_POST['is_sold']) ? "sold" : "active";

    $configs = explode("\n", $_POST['configs']);

    foreach ($configs as $config) {
        $config = trim($config);

        if (!empty($config)) {

            $namerecord = generateUniqueNumber($mysqli);

            $stmt = $mysqli->prepare("INSERT INTO manualsell 
            (codepanel, codeproduct, namerecord, username, contentrecord, status) 
            VALUES (?, ?, ?, ?, ?, ?)");

            $stmt->bind_param("ssssss", 
                $codepanel,
                $codeproduct,
                $namerecord,
                $username,
                $config,
                $status
            );

            if ($stmt->execute()) {
                $success++;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bulk Config Pro</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    background: #0f172a;
    color: white;
    font-family: sans-serif;
}

.container {
    padding: 15px;
}

.box {
    background: #1e293b;
    padding: 20px;
    border-radius: 15px;
    max-width: 600px;
    margin: auto;
}

h2 { text-align: center; }

select, input, textarea {
    width: 100%;
    margin: 8px 0;
    padding: 12px;
    border-radius: 10px;
    border: none;
}

textarea { resize: none; }

.checkbox {
    display: flex;
    gap: 8px;
    margin: 10px 0;
    font-size: 14px;
}

button {
    background: #22c55e;
    padding: 14px;
    width: 100%;
    border: none;
    border-radius: 10px;
    color: white;
    cursor: pointer;
}

.success {
    background: green;
    padding: 10px;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 10px;
}
</style>

</head>
<body>

<div class="container">
<div class="box">
<h2>🤖اختصاصی parsconn_bot</h2>
<h2>🚀 ثبت دسته‌جمعی کانفیگ</h2>

<?php if($success > 0): ?>
<div class="success">✔️ <?php echo $success; ?> رکورد ثبت شد</div>
<?php endif; ?>

<form method="post">

<!-- انتخاب پنل -->
<select name="codepanel" required>
<option value="">انتخاب پنل</option>
<?php while($row = $panels->fetch_assoc()): ?>
<option value="<?= $row['code_panel'] ?>">
<?= $row['code_panel'] ?>
</option>
<?php endwhile; ?>
</select>

<!-- انتخاب محصول -->
<select name="codeproduct" required>
<option value="">انتخاب محصول</option>
<option value="usertest">🧪 usertest (اکانت تست)</option>

<?php while($row = $products->fetch_assoc()): ?>
<option value="<?= $row['code_product'] ?>">
<?= $row['name_product'] ?> (<?= $row['code_product'] ?>)
</option>
<?php endwhile; ?>

</select>

<input type="text" name="username" placeholder="username" required>

<div class="checkbox">
<input type="checkbox" name="is_sold" id="is_sold">
<label for="is_sold">اگر تیک بزنی → sold / اگر نزنی → active</label>
</div>

<textarea name="configs" rows="10" placeholder="هر خط یک کانفیگ"></textarea>

<button name="submit">🔥 ثبت کانفیگ‌ها</button>

</form>

</div>
</div>

</body>
</html>