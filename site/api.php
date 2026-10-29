<?php
require_once __DIR__.'/bootstrap.php';
$action=$_GET['action'] ?? '';

if ($action==='login_webapp') {
    $raw=$_POST['initData'] ?? '';
    parse_str($raw,$data);
    $v=ag_verify_tg($data);
    if(!$v || empty($v['user'])) ag_json(['ok'=>false,'message'=>'ورود تلگرام معتبر نیست.'],403);
    $tg=json_decode($v['user'],true);
    if(!is_array($tg) || empty($tg['id'])) ag_json(['ok'=>false,'message'=>'اطلاعات کاربر نامعتبر است.'],403);
    if(isset($v['auth_date']) && time()-(int)$v['auth_date'] > 86400) ag_json(['ok'=>false,'message'=>'نشست تلگرام منقضی شده است.'],403);
    global $pdo;
    $st=$pdo->prepare("SELECT id FROM user WHERE id=? LIMIT 1"); $st->execute([(int)$tg['id']]);
    if(!$st->fetchColumn()) ag_json(['ok'=>false,'message'=>'ابتدا یک‌بار ربات را Start کنید.'],404);
    ag_login_user((int)$tg['id']);
    ag_json(['ok'=>true]);
}

if ($action==='telegram_callback') {
    $v=ag_tg_login_verify($_GET);
    if(!$v || empty($v['id'])) { http_response_code(403); exit('ورود تلگرام نامعتبر است.'); }
    if(isset($v['auth_date']) && time()-(int)$v['auth_date']>86400) exit('نشست منقضی شده است.');
    global $pdo;
    $st=$pdo->prepare("SELECT id FROM user WHERE id=? LIMIT 1"); $st->execute([(int)$v['id']]);
    if(!$st->fetchColumn()) exit('ابتدا ربات را در تلگرام Start کنید، سپس دوباره وارد شوید.');
    ag_login_user((int)$v['id']);
    header('Location: index.php#dashboard'); exit;
}
if ($action==='logout') { $_SESSION=[]; session_destroy(); header('Location:index.php'); exit; }

if ($action==='site_config') {
    ag_json(['ok'=>true,'bot'=>'ahwazgsm14_bot']);
}
if ($action==='me') {
    $u=ag_user();
    if(!$u) ag_json(['ok'=>true,'user'=>null]);
    ag_json(['ok'=>true,'user'=>[
        'id'=>$u['id'],'username'=>$u['username'] ?? '',
        'balance'=>(float)($u['Balance']??0),
        'affiliate_count'=>(int)($u['affiliatescount']??0),
        'register'=>$u['register']??null
    ]]);
}
if ($action==='products') {
    global $pdo;
    $st=$pdo->query("SELECT code_product,name_product,price_product,Volume_constraint,Service_time,note,Location,category FROM product ORDER BY CAST(price_product AS UNSIGNED) ASC");
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    ag_json(['ok'=>true,'items'=>$rows]);
}
if ($action==='panels') {
    global $pdo;
    $st=$pdo->query("SELECT code_panel,name_panel,type,status FROM marzban_panel WHERE status='active' OR status IS NULL");
    ag_json(['ok'=>true,'items'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}
if ($action==='dashboard') {
    $u=ag_auth_required(); global $pdo;
    $q=$pdo->prepare("SELECT id_invoice,username,name_product,price_product,Volume,Service_time,time_sell,Status,Service_location FROM invoice WHERE id_user=? ORDER BY time_sell DESC LIMIT 100");
    $q->execute([$u['id']]); $services=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT * FROM Payment_report WHERE id_user=? ORDER BY id DESC LIMIT 30");
    try { $q->execute([$u['id']]); $payments=$q->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e) { $payments=[]; }
    ag_json(['ok'=>true,'user'=>[
        'id'=>$u['id'],'username'=>$u['username']??'','balance'=>(float)($u['Balance']??0),
        'affiliate_count'=>(int)($u['affiliatescount']??0)
    ],'services'=>$services,'payments'=>$payments]);
}
if ($action==='buy') {
    $u=ag_auth_required();
    $input=json_decode(file_get_contents('php://input'),true) ?: [];
    $service=(string)($input['service_id']??'');
    $panel=(string)($input['country_id']??'');
    if($service==='' || $panel==='') ag_json(['ok'=>false,'message'=>'سرویس و سرور را انتخاب کنید.'],422);
    // Proxy to the existing, battle-tested Mini App purchase API.
    $payload=[
      'actions'=>'purchase','user_id'=>(int)$u['id'],'username'=>(string)($u['username']??''),
      'service_id'=>$service,'country_id'=>$panel,
      'custom_service'=>null,'custom_username'=>'','custom_note'=>''
    ];
    $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
    $host=$_SERVER['HTTP_HOST']??'localhost';
    $root=rtrim(dirname(dirname($_SERVER['SCRIPT_NAME']??'/site/api.php')),'/');
    $url=$scheme.'://'.$host.($root==='/'?'':$root).'/api/miniapp.php';
    $token=(string)($u['token']??'');
    if($token==='') ag_json(['ok'=>false,'message'=>'توکن کاربر موجود نیست.'],500);
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>[
      'Content-Type: application/json','Authorization: Bearer '.$token
    ],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>60]);
    $out=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($out===false) ag_json(['ok'=>false,'message'=>'خطای ارتباط با سیستم خرید: '.$err],502);
    http_response_code($code?:200); header('Content-Type: application/json; charset=utf-8'); echo $out; exit;
}
ag_json(['ok'=>false,'message'=>'مسیر نامعتبر است.'],404);
