<?php
session_start();
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$root = realpath(__DIR__ . '/../vpnbot');
if (!$root || !is_dir($root)) { http_response_code(500); exit('مسیر vpnbot پیدا نشد.'); }
if (empty($_SESSION['vpnbot_csrf'])) $_SESSION['vpnbot_csrf'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['vpnbot_csrf'];
$msg = ''; $err = '';
$updateRoot = realpath($root . '/update');
$allowed = ['admin.php','index.php','keyboard.php','color-panel.php','import.php','botapi.php','func.php','version'];
function safe_bot_dir($root, $name) {
    if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $name) || in_array($name, ['update','Default'], true)) return false;
    $path = realpath($root . '/' . $name);
    return $path && strpos($path, $root . DIRECTORY_SEPARATOR) === 0 && is_dir($path) ? $path : false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { $err = 'درخواست نامعتبر است؛ صفحه را تازه‌سازی کنید.'; }
    else {
        $action = (string)($_POST['action'] ?? ''); $bot = (string)($_POST['bot'] ?? ''); $dir = safe_bot_dir($root, $bot);
        if (!$dir) $err = 'ربات انتخاب‌شده معتبر نیست.';
        elseif ($action === 'save_colors') {
            $allowedColors = ['primary','success','danger'];
            $labelsForButtons = ['main_buy','main_test','backorder','account','main_support','admin','choosepaymentmethod','AddBalance','backuser','customsellvolume','main_menu'];
            $labelsForReply = ['buy','test','my_service','wallet','support','admin','back'];
            $cfgFile = $dir . '/button-colors.json';
            $cfg = is_file($cfgFile) ? json_decode((string)@file_get_contents($cfgFile), true) : [];
            if (!is_array($cfg)) $cfg = [];
            foreach (['primary','success','danger'] as $role) {
                $v = (string)($_POST['color_' . $role] ?? '');
                if (in_array($v, $allowedColors, true)) $cfg[$role] = $v;
            }
            $cfg['buttons'] = is_array($cfg['buttons'] ?? null) ? $cfg['buttons'] : [];
            $cfg['reply_buttons'] = is_array($cfg['reply_buttons'] ?? null) ? $cfg['reply_buttons'] : [];
            foreach ($labelsForButtons as $key) {
                $v = (string)($_POST['btn_' . $key] ?? '');
                if (in_array($v, $allowedColors, true)) $cfg['buttons'][$key] = $v;
            }
            foreach ($labelsForReply as $key) {
                $v = (string)($_POST['reply_' . $key] ?? '');
                if (in_array($v, $allowedColors, true)) $cfg['reply_buttons'][$key] = $v;
            }
            $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false || @file_put_contents($cfgFile, $json, LOCK_EX) === false) $err = 'ذخیره رنگ‌ها ناموفق بود؛ دسترسی نوشتن پوشه ربات را بررسی کنید.';
            else { @chmod($cfgFile, 0640); $msg = 'رنگ‌بندی ربات «' . htmlspecialchars($bot, ENT_QUOTES, 'UTF-8') . '» ذخیره شد.'; }
        }
        elseif ($action === 'update') {
            if (!$updateRoot || !is_dir($updateRoot)) $err = 'پوشه vpnbot/update موجود نیست.';
            else {
                $backupDir = $root . '/.backups/' . date('Ymd_His') . '_' . $bot;
                $copied = []; $failed = [];
                foreach ($allowed as $file) {
                    $src = $updateRoot . DIRECTORY_SEPARATOR . $file;
                    if (!is_file($src) || !is_readable($src)) continue;
                    if (is_link($src) || realpath($src) === false || strpos(realpath($src), $updateRoot . DIRECTORY_SEPARATOR) !== 0) { $failed[] = $file; continue; }
                    if (!is_dir($backupDir) && !@mkdir($backupDir, 0750, true)) { $failed[] = $file; continue; }
                    $deny = dirname($backupDir) . '/.htaccess';
                    if (!is_file($deny)) @file_put_contents($deny, "Require all denied\nDeny from all\n");
                    if (is_file($dir . DIRECTORY_SEPARATOR . $file)) @copy($dir . DIRECTORY_SEPARATOR . $file, $backupDir . DIRECTORY_SEPARATOR . $file);
                    if (@copy($src, $dir . DIRECTORY_SEPARATOR . $file)) $copied[] = $file; else $failed[] = $file;
                }
                if ($copied) $msg = 'به‌روزرسانی ربات «' . htmlspecialchars($bot, ENT_QUOTES, 'UTF-8') . '» انجام شد. فایل‌های به‌روزشده: ' . implode('، ', $copied) . ($failed ? ' | خطا: ' . implode('، ', $failed) : '');
                else $err = 'فایلی برای به‌روزرسانی پیدا نشد یا کپی فایل‌ها ناموفق بود.';
            }
        } else $err = 'عملیات ناشناخته است.';
    }
}
$bots = [];
foreach (scandir($root) ?: [] as $name) {
    if ($name === '.' || $name === '..' || in_array($name, ['update','.backups'], true)) continue;
    $dir = safe_bot_dir($root, $name); if (!$dir) continue;
    $version = is_file($dir . '/version') ? trim((string)@file_get_contents($dir . '/version')) : 'نامشخص';
    $bots[] = ['name'=>$name,'version'=>$version,'admin'=>is_file($dir.'/admin.php'),'keyboard'=>is_file($dir.'/keyboard.php'),'color'=>is_file($dir.'/color-panel.php') && is_file($dir.'/button-colors.json'),'config'=>is_file($dir.'/config.php'),'updated'=>@filemtime($dir) ?: 0];
}
usort($bots, fn($a,$b)=>strcmp($a['name'],$b['name']));
$updateFiles = [];
if ($updateRoot) foreach ($allowed as $f) if (is_file($updateRoot.'/'.$f)) $updateFiles[]=$f;
$colorBot = (string)($_GET['color_bot'] ?? $_POST['bot'] ?? ($bots[0]['name'] ?? ''));
$colorDir = $colorBot !== '' ? safe_bot_dir($root, $colorBot) : false;
if (!$colorDir) { $colorBot = $bots[0]['name'] ?? ''; $colorDir = $colorBot !== '' ? safe_bot_dir($root, $colorBot) : false; }
$colorConfig = [];
if ($colorDir && is_file($colorDir . '/button-colors.json')) { $loadedColors = json_decode((string)@file_get_contents($colorDir . '/button-colors.json'), true); if (is_array($loadedColors)) $colorConfig = $loadedColors; }
$botColorConfigs = [];
foreach ($bots as $itemBot) {
    $itemDir = safe_bot_dir($root, $itemBot['name']);
    $itemCfg = [];
    if ($itemDir && is_file($itemDir . '/button-colors.json')) {
        $decoded = json_decode((string)@file_get_contents($itemDir . '/button-colors.json'), true);
        if (is_array($decoded)) $itemCfg = $decoded;
    }
    $botColorConfigs[$itemBot['name']] = $itemCfg;
}
$showColorModal = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'save_colors');
$colorOptions = ['primary'=>'آبی','success'=>'سبز','danger'=>'قرمز'];
$inlineButtonList = ['main_buy'=>'خرید اشتراک','main_test'=>'سرویس تست','backorder'=>'سرویس‌های من','account'=>'حساب کاربری / کیف پول','main_support'=>'پشتیبانی','admin'=>'پنل مدیریت','choosepaymentmethod'=>'انتخاب روش پرداخت','AddBalance'=>'افزایش موجودی','backuser'=>'بازگشت به منوی اصلی','customsellvolume'=>'حجم دلخواه','main_menu'=>'منوی اصلی'];
$inlineReplyButtonList = ['buy'=>'منوی ثابت: خرید اشتراک','test'=>'منوی ثابت: سرویس تست','my_service'=>'منوی ثابت: سرویس‌های من','wallet'=>'منوی ثابت: کیف پول / حساب کاربری','support'=>'منوی ثابت: پشتیبانی','admin'=>'منوی ثابت: پنل مدیریت','back'=>'منوی ثابت: بازگشت / منوی اصلی'];
?><!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>مدیریت ربات‌ها | پنل Ahwaz-GSM</title><link rel="stylesheet" href="css/theme.css?v=flat50"><link rel="stylesheet" href="css/ahwazgsm-panel.css"><script src="js/theme.js?v=flat50" defer></script><style>.ag-main{padding:8px 0 28px}.ag-hero{margin-bottom:18px}.ag-panel,.ag-stats article{box-shadow:var(--shadow-1,0 8px 24px rgba(0,0,0,.06))}.ag-color-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.ag-color-item{padding:14px;border:1px solid var(--border-soft,rgba(255,255,255,.1));background:var(--surface-2,#1A1B20);border-radius:12px}.ag-color-item label{display:block;margin:0 0 8px;color:var(--text-muted,#A1A1AA)}.ag-color-item select,.ag-color-chooser{width:100%;padding:10px;border:1px solid var(--border-soft,rgba(255,255,255,.12));border-radius:9px;background:var(--surface-1,#15161A);color:var(--text-main,#F4F4F5);font:inherit}.ag-color-preview{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.ag-preview-btn{display:inline-block;padding:9px 14px;border-radius:10px;font-size:12px;font-weight:700;color:#fff;border:1px solid #ffffff30}.ag-preview-blue{background:#2563eb}.ag-preview-green{background:#16a34a}.ag-preview-red{background:#dc2626}.ag-color-save{margin-top:14px}.ag-page-content{min-width:0}.ag-actions a.ag-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;white-space:nowrap}
/* Use the same live theme, accent color, surfaces and text colors as the main Ahwaz-GSM panel. */
body{background:var(--bg-body,#0E0F12)!important;color:var(--text-main,#F4F4F5)!important;font-family:inherit!important}
.ag-hero{background:linear-gradient(115deg,var(--surface-2,#1A1B20),var(--surface-1,#15161A));border:1px solid var(--border-soft,rgba(255,255,255,.07));box-shadow:var(--shadow-1,none)}
.ag-eyebrow,.ag-hero-icon{color:var(--accent-ink,var(--accent,#3b82f6))}
.ag-hero p{color:var(--text-muted,#A1A1AA)}
.ag-stats article,.ag-panel{background:var(--surface-1,#15161A);color:var(--text-main,#F4F4F5);border-color:var(--border-soft,rgba(255,255,255,.07));box-shadow:var(--shadow-1,none)}
.ag-stats span,.ag-stats small,.ag-panel-head p,.ag-bot-name small,.ag-empty,.ag-note small{color:var(--text-muted,#A1A1AA)}
.ag-stats strong,.ag-panel-head h2,.ag-bot-name b{color:var(--text-main,#F4F4F5)}
.ag-stats article:before{background:var(--accent,#3b82f6)}
.ag-panel-head{border-color:var(--border-soft,rgba(255,255,255,.07))}
.ag-search{background:var(--surface-2,#1A1B20);border-color:var(--border-soft,rgba(255,255,255,.07));color:var(--text-main,#F4F4F5)}
.ag-search span{color:var(--accent-ink,var(--accent,#3b82f6))}.ag-search input{color:var(--text-main,#F4F4F5)}
.ag-table th{background:var(--surface-2,#1A1B20);color:var(--text-muted,#A1A1AA)}
.ag-table td{border-color:var(--border-soft,rgba(255,255,255,.07))}.ag-table tbody tr:hover{background:var(--surface-2,#1A1B20)}
.ag-bot-avatar{background:var(--accent-soft,rgba(59,130,246,.15))}
.ag-version{background:var(--surface-2,#1A1B20);color:var(--accent-ink,#60A5FA)}
.ag-btn.primary{background:var(--accent,#3b82f6);color:var(--accent-fg,#fff);box-shadow:none}
.ag-btn.secondary{background:var(--surface-2,#1A1B20);color:var(--text-main,#F4F4F5);border:1px solid var(--border-soft,rgba(255,255,255,.07))}
.ag-note{background:var(--surface-1,#15161A);border-color:var(--border-soft,rgba(255,255,255,.07))}.ag-note b{color:var(--accent-ink,var(--accent,#3b82f6))}.ag-note p{color:var(--text-muted,#A1A1AA)}.ag-note code{background:var(--surface-2,#1A1B20);color:var(--text-main,#F4F4F5)}

.ag-bot-actions{display:flex;gap:7px;flex-wrap:wrap;align-items:center}.ag-btn.color-manage{background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff;border:0;box-shadow:0 5px 14px rgba(99,102,241,.22)}
.ag-color-modal{position:fixed;inset:0;z-index:9999;background:rgba(3,7,18,.76);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;padding:18px}.ag-color-modal.is-open{display:flex}.ag-color-dialog{width:min(1050px,100%);max-height:92vh;overflow:auto;background:var(--surface-1,#15161A);color:var(--text-main,#F4F4F5);border:1px solid var(--border-soft,rgba(255,255,255,.12));border-radius:20px;box-shadow:0 24px 90px rgba(0,0,0,.42)}.ag-color-dialog-head{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;background:var(--surface-1,#15161A);border-bottom:1px solid var(--border-soft,rgba(255,255,255,.1))}.ag-color-dialog-head h2{margin:0;font-size:20px}.ag-color-dialog-head p{margin:5px 0 0;color:var(--text-muted,#A1A1AA);font-size:13px}.ag-modal-close{border:1px solid var(--border-soft,rgba(255,255,255,.15));background:var(--surface-2,#1A1B20);color:var(--text-main,#F4F4F5);border-radius:10px;padding:8px 12px;font:inherit;cursor:pointer}.ag-color-dialog-body{padding:18px 20px}.ag-telegram-preview{max-width:520px;margin:0 auto 22px;padding:12px;background:#17212b;border:1px solid #33414d;border-radius:18px;color:#e7edf3}.ag-telegram-title{display:flex;align-items:center;gap:10px;padding:4px 4px 12px;border-bottom:1px solid #34434f;margin-bottom:12px}.ag-telegram-avatar{width:36px;height:36px;display:grid;place-items:center;border-radius:50%;background:#3287c8;font-size:19px}.ag-telegram-title strong{display:block;font-size:14px}.ag-telegram-title small{display:block;color:#9db0c0;font-size:11px}.ag-chat-bubble{background:#243442;border-radius:12px 12px 12px 4px;padding:12px;margin-bottom:10px;font-size:13px;line-height:1.8}.ag-preview-keyboard{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:5px}.ag-preview-key{border:0;border-radius:7px;min-height:38px;padding:7px 5px;color:#fff;font:600 12px inherit;cursor:default;transition:background .15s}.ag-preview-key.blue{background:#2879c7}.ag-preview-key.green{background:#269b64}.ag-preview-key.red{background:#d94b55}.ag-preview-caption{font-size:11px;color:#a9bbc8;margin:12px 0 6px}.ag-color-section-title{margin:20px 0 10px;font-size:15px}.ag-color-dialog .ag-color-item{background:var(--surface-2,#1A1B20)}.ag-color-dialog-footer{position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;background:var(--surface-1,#15161A);border-top:1px solid var(--border-soft,rgba(255,255,255,.1))}.ag-color-dialog-footer .ag-btn{font:inherit;cursor:pointer}.ag-color-status{font-size:12px;color:var(--text-muted,#A1A1AA);margin-inline-end:auto;align-self:center}@media(max-width:650px){.ag-color-modal{padding:6px}.ag-color-dialog{max-height:96vh;border-radius:14px}.ag-color-dialog-head,.ag-color-dialog-body{padding:14px}.ag-preview-keyboard{gap:4px}.ag-preview-key{font-size:11px}.ag-color-dialog-footer{padding:12px 14px;flex-wrap:wrap}}
@media (prefers-color-scheme: light){[data-theme="light"] .ag-table th{background:var(--surface-2,#F4F4F5)}}
@media(max-width:700px){.ag-panel-head{gap:12px}.ag-table{min-width:760px}}</style></head><body><section id="container"><?php include __DIR__ . '/header.php'; ?><section id="main-content"><div class="wrapper"><main class="ag-main ag-page-content"><section class="ag-hero"><div><span class="ag-eyebrow">BOT CONTROL CENTER</span><h1>مدیریت ربات‌ها</h1><p>مشاهده وضعیت ربات‌ها و انتشار نسخه‌های به‌روزرسانی‌شده از بسته محلی پنل.</p></div><div class="ag-hero-icon">✦</div></section><?php if($msg):?><div class="ag-alert success"><?=htmlspecialchars($msg,ENT_QUOTES,'UTF-8')?></div><?php endif;?><?php if($err):?><div class="ag-alert error"><?=htmlspecialchars($err,ENT_QUOTES,'UTF-8')?></div><?php endif;?><section class="ag-stats"><article><span>کل ربات‌ها</span><strong><?=count($bots)?></strong><small>پوشه‌های شناسایی‌شده</small></article><article><span>قابل مدیریت</span><strong><?=count(array_filter($bots,fn($b)=>$b['admin']))?></strong><small>دارای پنل مدیریت</small></article><article><span>فایل‌های آپدیت</span><strong><?=count($updateFiles)?></strong><small>در پوشه vpnbot/update</small></article></section><section class="ag-panel"><div class="ag-panel-head"><div><h2>فهرست ربات‌ها</h2><p>اطلاعات تنظیمات و داده‌های کاربران در فرآیند آپدیت جایگزین نمی‌شوند.</p></div><label class="ag-search"><span>⌕</span><input id="botSearch" type="search" placeholder="جستجوی نام ربات…"></label></div><?php if(!$bots):?><div class="ag-empty">هیچ پوشه رباتی پیدا نشد.</div><?php else:?><div class="ag-table-wrap"><table class="ag-table"><thead><tr><th>ربات</th><th>نسخه</th><th>وضعیت فایل‌ها</th><th>عملیات</th></tr></thead><tbody><?php foreach($bots as $b):?><tr data-bot-name="<?=htmlspecialchars(strtolower($b['name']),ENT_QUOTES,'UTF-8')?>"><td><div class="ag-bot-name"><span class="ag-bot-avatar">🤖</span><span><b><?=htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8')?></b><small><?=date('Y-m-d H:i',$b['updated'])?></small></span></div></td><td><span class="ag-version">v<?=htmlspecialchars($b['version'],ENT_QUOTES,'UTF-8')?></span></td><td><div class="ag-chips"><span class="<?=$b['admin']?'ok':'missing'?>">پنل <?=$b['admin']?'موجود':'نیست'?></span><span class="<?=$b['keyboard']?'ok':'missing'?>">دکمه‌ها <?=$b['keyboard']?'موجود':'نیست'?></span><span class="<?=$b['config']?'ok':'missing'?>">تنظیمات <?=$b['config']?'موجود':'نیست'?></span></div></td><td><div class="ag-bot-actions"><button type="button" class="ag-btn color-manage" data-open-color data-bot="<?=htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8')?>">🎨 مدیریت دکمه‌ها</button><form method="post" onsubmit="return confirm('فایل‌های مجاز نسخه جدید روی این ربات کپی شود؟ از فایل‌های فعلی پشتیبان گرفته می‌شود.');"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="bot" value="<?=htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="update"><button class="ag-btn primary" type="submit" <?=$updateFiles?'':'disabled'?>>⟳ به‌روزرسانی</button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section><div class="ag-color-modal<?=($showColorModal && !$err)?' is-open':''?>" id="botColorModal" aria-hidden="<?=($showColorModal && !$err)?'false':'true'?>">
<div class="ag-color-dialog" role="dialog" aria-modal="true" aria-labelledby="colorModalTitle">
<div class="ag-color-dialog-head"><div><h2 id="colorModalTitle">🎨 مدیریت دکمه‌های ربات</h2><p>رنگ و ظاهر دکمه‌های همین ربات را تنظیم کن؛ پیش‌نمایش به‌صورت زنده تغییر می‌کند.</p></div><button type="button" class="ag-modal-close" data-close-color aria-label="بستن پنجره">✕ بستن</button></div>
<div class="ag-color-dialog-body">
<div class="ag-telegram-preview"><div class="ag-telegram-title"><span class="ag-telegram-avatar">🤖</span><span><strong id="previewBotName"><?=htmlspecialchars($colorBot,ENT_QUOTES,'UTF-8')?></strong><small>ربات · پیش‌نمایش منو</small></span></div><div class="ag-chat-bubble">سلام! به ربات خوش آمدید. از دکمه‌های زیر برای ادامه استفاده کنید.</div><div class="ag-preview-caption">دکمه‌های منوی اصلی ربات</div><div class="ag-preview-keyboard"><button class="ag-preview-key green" type="button" data-preview-key="btn_main_buy">🛒 خرید اشتراک</button><button class="ag-preview-key blue" type="button" data-preview-key="btn_main_test">🧪 سرویس تست</button><button class="ag-preview-key blue" type="button" data-preview-key="btn_backorder">📦 سرویس‌های من</button><button class="ag-preview-key green" type="button" data-preview-key="btn_account">👤 حساب کاربری</button><button class="ag-preview-key green" type="button" data-preview-key="btn_main_support">🎧 پشتیبانی</button><button class="ag-preview-key blue" type="button" data-preview-key="btn_admin">⚙️ پنل مدیریت</button><button class="ag-preview-key red" type="button" data-preview-key="btn_backuser" style="grid-column:1/-1">↩ بازگشت به منوی اصلی</button></div><div class="ag-preview-caption">نمونه شبیه‌سازی‌شده از دکمه‌های ربات؛ رنگ واقعی تلگرام وابسته به نوع دکمه و پشتیبانی API است.</div></div>
<form method="post" id="inlineColorForm"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="save_colors"><input type="hidden" name="bot" id="colorFormBot" value="<?=htmlspecialchars($colorBot,ENT_QUOTES,'UTF-8')?>">
<h3 class="ag-color-section-title">رنگ‌های پایه</h3><div class="ag-color-grid"><?php foreach(['primary'=>'دکمه عادی','success'=>'تأیید / خرید','danger'=>'بازگشت / لغو'] as $role=>$label): $v=$colorConfig[$role]??$role; ?><div class="ag-color-item"><label for="color_<?=$role?>"><?=htmlspecialchars($label,ENT_QUOTES,'UTF-8')?></label><select id="color_<?=$role?>" name="color_<?=$role?>" data-color-role="<?=$role?>"><?php foreach($colorOptions as $val=>$text):?><option value="<?=$val?>" <?=$v===$val?'selected':''?>><?=$text?></option><?php endforeach;?></select></div><?php endforeach;?></div>
<h3 class="ag-color-section-title">رنگ هر دکمه در منوی اصلی</h3><div class="ag-color-grid"><?php foreach($inlineButtonList as $key=>$label): $default=in_array($key,['main_buy','account','main_support'],true)?'success':($key==='backuser'?'danger':'primary'); $v=$colorConfig['buttons'][$key]??$default; ?><div class="ag-color-item"><label for="btn_<?=$key?>"><?=htmlspecialchars($label,ENT_QUOTES,'UTF-8')?></label><select id="btn_<?=$key?>" name="btn_<?=$key?>" data-button-key="<?=$key?>"><?php foreach($colorOptions as $val=>$text):?><option value="<?=$val?>" <?=$v===$val?'selected':''?>><?=$text?></option><?php endforeach;?></select></div><?php endforeach;?></div>
<h3 class="ag-color-section-title">رنگ دکمه‌های منوی ثابت پایین چت</h3><div class="ag-color-grid"><?php foreach($inlineReplyButtonList as $key=>$label): $default=in_array($key,['buy','wallet','support'],true)?'success':($key==='back'?'danger':'primary'); $v=$colorConfig['reply_buttons'][$key]??$default; ?><div class="ag-color-item"><label for="reply_<?=$key?>"><?=htmlspecialchars($label,ENT_QUOTES,'UTF-8')?></label><select id="reply_<?=$key?>" name="reply_<?=$key?>" data-reply-key="<?=$key?>"><?php foreach($colorOptions as $val=>$text):?><option value="<?=$val?>" <?=$v===$val?'selected':''?>><?=$text?></option><?php endforeach;?></select></div><?php endforeach;?></div>
</form></div><div class="ag-color-dialog-footer"><span class="ag-color-status" id="colorModalStatus">تنظیمات برای هر ربات جداگانه ذخیره می‌شود.</span><button type="button" class="ag-btn secondary" data-close-color>انصراف</button><button type="submit" form="inlineColorForm" class="ag-btn primary">💾 ذخیره تنظیمات ربات</button></div></div></div>
<section class="ag-note"><b>پیش از به‌روزرسانی</b><p>فقط فایل‌های مشخص‌شده در پوشه <code>vpnbot/update</code> کپی می‌شوند. فایل‌های <code>config.php</code>، اطلاعات کاربران، محصولات و پوشه‌های داده دست‌نخورده می‌مانند. از فایل‌های موجود نیز نسخه پشتیبان در <code>vpnbot/.backups</code> تهیه می‌شود.</p><small>فایل‌های منبع موجود: <?=htmlspecialchars(implode('، ',$updateFiles) ?: 'هیچ فایل مجازی یافت نشد',ENT_QUOTES,'UTF-8')?></small></section></main></div></section></section><script>
document.getElementById('botSearch')?.addEventListener('input',function(){const q=this.value.toLowerCase().trim();document.querySelectorAll('[data-bot-name]').forEach(row=>row.hidden=!row.dataset.botName.includes(q));});
(function(){
 const modal=document.getElementById('botColorModal'), form=document.getElementById('inlineColorForm'); if(!modal||!form)return;
 const configs=<?=json_encode($botColorConfigs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;
 const colorClass={primary:'blue',success:'green',danger:'red'};
 const defaults={primary:'primary',success:'success',danger:'danger'};
 function valFor(cfg,name,fallback){const el=form.elements[name];if(!el)return;const role=name.replace('color_','');el.value=(cfg && typeof cfg[role]==='string' && ['primary','success','danger'].includes(cfg[role]))?cfg[role]:fallback;}
 function applyConfig(bot){const cfg=configs[bot]||{};document.getElementById('colorFormBot').value=bot;document.getElementById('previewBotName').textContent=bot;
 ['primary','success','danger'].forEach(role=>valFor(cfg,'color_'+role,role));
 form.querySelectorAll('[data-button-key]').forEach(el=>{const k=el.dataset.buttonKey;el.value=(cfg.buttons&&['primary','success','danger'].includes(cfg.buttons[k]))?cfg.buttons[k]:(['main_buy','account','main_support'].includes(k)?'success':(k==='backuser'?'danger':'primary'));});
 form.querySelectorAll('[data-reply-key]').forEach(el=>{const k=el.dataset.replyKey;el.value=(cfg.reply_buttons&&['primary','success','danger'].includes(cfg.reply_buttons[k]))?cfg.reply_buttons[k]:(['buy','wallet','support'].includes(k)?'success':(k==='back'?'danger':'primary'));});
 refreshPreview();
 }
 function selectedColor(key){const el=form.querySelector('[name="'+key+'"]');return colorClass[el?.value]||'blue';}
 function refreshPreview(){modal.querySelectorAll('[data-preview-key]').forEach(btn=>{btn.className='ag-preview-key '+selectedColor(btn.dataset.previewKey);});}
 function open(bot){applyConfig(bot);modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';}
 function close(){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';}
 document.querySelectorAll('[data-open-color]').forEach(btn=>btn.addEventListener('click',()=>open(btn.dataset.bot)));
 modal.querySelectorAll('[data-close-color]').forEach(btn=>btn.addEventListener('click',close));modal.addEventListener('click',e=>{if(e.target===modal)close();});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('is-open'))close();});
 form.querySelectorAll('select').forEach(el=>el.addEventListener('change',refreshPreview));
 if(modal.classList.contains('is-open')){document.body.style.overflow='hidden';applyConfig(document.getElementById('colorFormBot').value);}
})();
</script></body></html>
