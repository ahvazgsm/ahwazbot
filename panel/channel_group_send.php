<?php
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/csrf.php';

$stmt = $pdo->prepare('SELECT * FROM admin WHERE username = :username LIMIT 1');
$stmt->execute([':username' => $_SESSION['user'] ?? '']);
$adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (empty($_SESSION['user']) || !$adminRow) {
    header('Location: login.php');
    exit;
}
fx_csrf_guard();
$csrf = fx_csrf_token();
$result = null;
$old = ['chat_id' => '', 'message' => '', 'parse' => 'HTML', 'training_url' => '', 'buttons' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $unused) {
        if (isset($_POST[$key]) && is_string($_POST[$key])) $old[$key] = trim($_POST[$key]);
    }
    $chatId = $old['chat_id'];
    $message = $old['message'];
    $parse = in_array($old['parse'], ['HTML', 'Markdown', 'plain'], true) ? $old['parse'] : 'HTML';
    $parseMode = $parse === 'plain' ? null : $parse;
    $chatId = $old['chat_id'];
    $message = $old['message'];
    $parse = in_array($old['parse'], ['HTML', 'Markdown', 'plain'], true) ? $old['parse'] : 'HTML';
    $parseMode = $parse === 'plain' ? null : $parse;
    $trainingUrl = trim($old['training_url']);
    $selectedButtons = isset($_POST['buttons']) && is_array($_POST['buttons']) ? array_values(array_intersect(['buy', 'training', 'renew'], array_map('strval', $_POST['buttons']))) : [];
    $old['buttons'] = $selectedButtons;
    $botUsername = isset($usernamebot) ? trim((string)$usernamebot, " @\t\n\r\0\x0B") : '';

    if ($chatId === '' || !(preg_match('/^-?\d+$/', $chatId) || preg_match('/^@[A-Za-z0-9_]{5,}$/', $chatId))) {
        $result = ['ok' => false, 'message' => 'شناسه مقصد معتبر نیست. شناسه عددی گروه/کانال یا @username را وارد کن.'];
    } elseif ($message === '' || mb_strlen($message, 'UTF-8') > 4096) {
        $result = ['ok' => false, 'message' => 'متن پیام باید بین ۱ تا ۴۰۹۶ کاراکتر باشد.'];
    } elseif (in_array('training', $selectedButtons, true) && (!filter_var($trainingUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($trainingUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
        $result = ['ok' => false, 'message' => 'لینک آموزش معتبر نیست؛ لینک باید با http:// یا https:// شروع شود.'];
    } elseif (array_intersect($selectedButtons, ['buy', 'renew']) && !preg_match('/^[A-Za-z0-9_]{5,}$/', $botUsername)) {
        $result = ['ok' => false, 'message' => 'برای دکمه خرید یا تمدید، نام کاربری ربات در config.php باید معتبر باشد.'];
    } else {
        // Add only the buttons explicitly selected in the panel; no extra Start button is added.
        $inlineKeyboard = [];
        if (in_array('buy', $selectedButtons, true)) {
            $buyUrl = 'https://t.me/' . $botUsername . '?start=buy';
            $inlineKeyboard[] = [['text' => '🛒　　خرید اشتراک　　', 'url' => $buyUrl, 'style' => 'success']];
        }
        if (in_array('training', $selectedButtons, true)) {
            $inlineKeyboard[] = [['text' => '📚　　آموزش استفاده　　', 'url' => $trainingUrl, 'style' => 'primary']];
        }
        if (in_array('renew', $selectedButtons, true)) {
            $renewUrl = 'https://t.me/' . $botUsername . '?start=extend';
            $inlineKeyboard[] = [['text' => '🔄　　تمدید اشتراک　　', 'url' => $renewUrl, 'style' => 'danger']];
        }
        $payload = ['chat_id' => $chatId, 'text' => $message];
        if ($inlineKeyboard) $payload['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
        if ($parseMode !== null) $payload['parse_mode'] = $parseMode;
        try {
            $response = telegram('sendMessage', $payload);
            if (is_array($response) && !empty($response['ok'])) {
                $result = ['ok' => true, 'message' => $selectedButtons ? 'پیام همراه دکمه‌های انتخاب‌شده با موفقیت ارسال شد.' : 'پیام بدون دکمه با موفقیت ارسال شد.'];
            } else {
                $description = is_array($response) ? (string)($response['description'] ?? 'خطای نامشخص تلگرام') : 'پاسخ نامعتبر از تلگرام';
                $result = ['ok' => false, 'message' => 'ارسال ناموفق بود: ' . $description];
            }
        } catch (Throwable $e) {
            error_log('[channel_group_send] ' . $e->getMessage());
            $result = ['ok' => false, 'message' => 'ارسال انجام نشد؛ خطای ارتباط با تلگرام رخ داد.'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>ارسال به کانال یا گروه</title>
<link rel="stylesheet" href="css/theme.css?v=flat50">
<script src="js/theme.js?v=flat50" defer></script>
<style>
.cgs-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px}.cgs-field{margin-bottom:16px}.cgs-field label{display:block;margin-bottom:7px;font-weight:600}.cgs-field input,.cgs-field textarea,.cgs-field select{width:100%;box-sizing:border-box}.cgs-field textarea{min-height:180px;resize:vertical}.cgs-help{font-size:12px;line-height:1.9;color:var(--text-muted);white-space:pre-line}.cgs-result{padding:12px 14px;border-radius:10px;margin-bottom:16px}.cgs-result.ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35)}.cgs-result.bad{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35)}@media(max-width:850px){.cgs-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<section id="container">
<?php include __DIR__ . '/header.php'; ?>
<section id="main-content"><div class="wrapper fx-page-settings-theme">
<div class="page-head"><div><div class="page-head__title"><?php echo icon('send', 'svg-icon svg-lg'); ?> ارسال به کانال یا گروه</div><div class="page-head__sub">ارسال پیام همراه دکمه‌های Inline رنگی به مقصد تلگرام</div></div></div>
<?php if ($result): ?><div class="cgs-result <?php echo $result['ok'] ? 'ok' : 'bad'; ?>"><?php echo htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="post" class="cgs-grid">
<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
<div class="card"><div class="card__head"><div class="card__title">متن پیام و دکمه‌ها</div></div>
<div class="cgs-field"><label for="cgs-message">متن پیام</label><textarea class="form-control" id="cgs-message" name="message" required maxlength="4096" placeholder="متن پیام را وارد کن..."><?php echo htmlspecialchars($old['message'], ENT_QUOTES, 'UTF-8'); ?></textarea></div>
<div class="cgs-field"><label for="cgs-parse">فرمت متن</label><select class="form-control" id="cgs-parse" name="parse"><option value="HTML" <?php echo $old['parse']==='HTML'?'selected':''; ?>>HTML</option><option value="Markdown" <?php echo $old['parse']==='Markdown'?'selected':''; ?>>Markdown</option><option value="plain" <?php echo $old['parse']==='plain'?'selected':''; ?>>متن ساده</option></select></div>
<div class="cgs-field"><label>دکمه‌هایی که می‌خواهی ارسال شوند</label>
<label style="display:block;font-weight:400;margin:8px 0"><input type="checkbox" name="buttons[]" value="buy" <?php echo in_array('buy', $old['buttons'], true)?'checked':''; ?>> 🛒 خرید اشتراک</label>
<label style="display:block;font-weight:400;margin:8px 0"><input type="checkbox" name="buttons[]" value="training" <?php echo in_array('training', $old['buttons'], true)?'checked':''; ?>> 📚 آموزش استفاده</label>
<label style="display:block;font-weight:400;margin:8px 0"><input type="checkbox" name="buttons[]" value="renew" <?php echo in_array('renew', $old['buttons'], true)?'checked':''; ?>> 🔄 تمدید اشتراک</label>
</div>
<div class="cgs-field"><label for="cgs-training-url">لینک آموزش (فقط اگر دکمه آموزش انتخاب شده)</label><input class="form-control" type="url" id="cgs-training-url" name="training_url" value="<?php echo htmlspecialchars($old['training_url'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://t.me/your_training_channel"></div>
<div class="cgs-help">انتخاب دکمه‌ها اختیاری است؛ می‌توانی فقط یک دکمه، دو دکمه یا هیچ دکمه‌ای بفرستی. دکمه اضافی «استارت ربات» ساخته نمی‌شود.</div>
<div style="margin-top:18px"><button type="submit" class="btn btn-primary"><?php echo icon('send', 'svg-icon svg-sm'); ?> ارسال پیام</button></div>
</div>
<aside class="card"><div class="card__head"><div class="card__title">مقصد ارسال</div></div>
<div class="cgs-field"><label for="cgs-chatid">شناسه کانال یا گروه</label><input class="form-control" id="cgs-chatid" name="chat_id" required value="<?php echo htmlspecialchars($old['chat_id'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="-1001234567890 یا @channelname"></div>
<div class="cgs-help">ربات باید عضو مقصد باشد و اجازه ارسال پیام داشته باشد. در کانال معمولاً باید ربات مدیر باشد. ابتدا ارسال را با یک گروه آزمایشی امتحان کن.</div>
</aside>
</form>
</div></section></section>
</body></html>
