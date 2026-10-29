<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Tehran');

$local = __DIR__ . '/config.local.php';
$rootConfig = dirname(__DIR__) . '/config.php';
if (is_file($local)) {
    $candidate = require $local;
    if (is_string($candidate) && $candidate !== '') $rootConfig = $candidate;
}
if (!is_file($rootConfig)) {
    http_response_code(500);
    exit('خطای نصب: فایل config.php پروژه اصلی پیدا نشد.');
}
require_once $rootConfig;
$siteConfig = __DIR__ . '/config.site.php';
if (is_file($siteConfig)) require_once $siteConfig;

function ag_json($data, int $code=200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function ag_user(): ?array {
    global $pdo;
    if (empty($_SESSION['ag_uid'])) return null;
    $st=$pdo->prepare("SELECT * FROM user WHERE id=? LIMIT 1");
    $st->execute([$_SESSION['ag_uid']]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
function ag_auth_required(): array {
    $u=ag_user();
    if (!$u) ag_json(['ok'=>false,'message'=>'ابتدا وارد حساب شوید.'],401);
    return $u;
}
function ag_verify_tg(array $data): ?array {
    global $APIKEY;
    if (empty($data['hash'])) return null;
    $hash=$data['hash']; unset($data['hash']);
    foreach ($data as $k=>$v) if (is_array($v)) $data[$k]=json_encode($v, JSON_UNESCAPED_UNICODE);
    ksort($data);
    $pairs=[]; foreach($data as $k=>$v) $pairs[]=$k.'='.$v;
    $check=implode("\n",$pairs);
    $secret=hash('sha256',(string)$APIKEY,true);
    $calc=hash_hmac('sha256',$check,$secret);
    return hash_equals($calc,$hash)?$data:null;
}
function ag_tg_login_verify(array $data): ?array {
    global $APIKEY;
    if (empty($data['hash'])) return null;
    $hash=$data['hash']; unset($data['hash']);
    ksort($data);
    $pairs=[]; foreach($data as $k=>$v) $pairs[]=$k.'='.$v;
    $check=implode("\n",$pairs);
    $secret=hash('sha256',(string)$APIKEY,true);
    $calc=hash_hmac('sha256',$check,$secret);
    return hash_equals($calc,$hash)?$data:null;
}
function ag_login_user(int $uid): void { session_regenerate_id(true); $_SESSION['ag_uid']=$uid; }
function ag_money($v): string { return number_format((float)$v).' تومان'; }
