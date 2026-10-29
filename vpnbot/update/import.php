<?php
declare(strict_types=1);
$subscriptionUrl = trim((string)($_GET['url'] ?? ''));
$validUrl = $subscriptionUrl !== '' && strlen($subscriptionUrl) <= 4096
    && filter_var($subscriptionUrl, FILTER_VALIDATE_URL)
    && in_array(strtolower((string)parse_url($subscriptionUrl, PHP_URL_SCHEME)), ['http','https'], true)
    && (string)parse_url($subscriptionUrl, PHP_URL_HOST) !== '';
if (!$validUrl) { http_response_code(400); $subscriptionUrl = ''; }
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$encUrl = rawurlencode($subscriptionUrl);
$encName = rawurlencode('AhwazGSM');
$apps = [
 'v2rayn' => ['title'=>'v2rayN', 'icon'=>'🖥', 'scheme'=>'#v2rayn', 'os'=>'windows', 'hint'=>'ویندوز — کپی لینک و افزودن در برنامه', 'install'=>'https://github.com/2dust/v2rayN/releases'],
 'v2box' => ['title'=>'V2Box', 'icon'=>'◈', 'scheme'=>'v2box://install-sub?url='.$encUrl.'&name='.$encName, 'os'=>'ios', 'hint'=>'آیفون و دستگاه‌های پشتیبانی‌شده', 'install'=>'https://apps.apple.com/search?term=V2Box'],
 'streisand' => ['title'=>'Streisand', 'icon'=>'✧', 'scheme'=>'streisand://import/'.$subscriptionUrl.'#AhwazGSM', 'os'=>'ios', 'hint'=>'آیفون — اگر از این لینک پشتیبانی کند', 'install'=>'https://apps.apple.com/search?term=Streisand'],
 'shadowrocket' => ['title'=>'Shadowrocket', 'icon'=>'🚀', 'scheme'=>'shadowrocket://add/'.$subscriptionUrl, 'os'=>'ios', 'hint'=>'آیفون — لینک اشتراک سازگار', 'install'=>'https://apps.apple.com/search?term=Shadowrocket'],
 'v2rayng' => ['title'=>'V2RayNG', 'icon'=>'✦', 'scheme'=>'v2rayng://install-sub?url='.$encUrl.'&name='.$encName, 'os'=>'android', 'hint'=>'اندروید — افزودن اشتراک', 'install'=>'https://github.com/2dust/v2rayNG/releases'],
 'hiddify' => ['title'=>'Hiddify', 'icon'=>'⬡', 'scheme'=>'hiddify://import/'.$subscriptionUrl.'#AhwazGSM', 'os'=>'all', 'hint'=>'اندروید، آیفون و ویندوز', 'install'=>'https://hiddify.com/'],
 'happ' => ['title'=>'Happ', 'icon'=>'⚡', 'scheme'=>'happ://add/'.$subscriptionUrl, 'os'=>'all', 'hint'=>'اندروید، آیفون و ویندوزِ سازگار', 'install'=>'https://happ-proxy.com/'],
 'singbox' => ['title'=>'sing-box', 'icon'=>'⬢', 'scheme'=>'sing-box://import-remote-profile?url='.$encUrl.'#AhwazGSM', 'os'=>'all', 'hint'=>'نسخه‌ها و کلاینت‌های سازگار', 'install'=>'https://sing-box.sagernet.org/'],
];
$schemes = []; foreach ($apps as $key => $app) $schemes[$key] = $app['scheme'];
$installLinks = []; foreach ($apps as $key => $app) $installLinks[$key] = $app['install'];
?>
<!doctype html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><meta name="theme-color" content="#071810">
<title>افزودن هوشمند اشتراک | AhwazGSM</title>
<style>
:root{color-scheme:dark;--bg:#06120d;--panel:#0b2116;--line:#28523b;--gold:#e6c66d;--txt:#f3fbf5;--muted:#b3cabb;--green:#1d5837}*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Tahoma,Arial,sans-serif;background:var(--bg);color:var(--txt)}body{min-height:100vh;min-height:100svh;line-height:1.75;background:radial-gradient(ellipse at 50% -10%,#285c3e 0,transparent 55%),var(--bg);padding:16px}.shell{width:100%;max-width:680px;min-height:calc(100svh - 32px);margin:auto;display:flex}.card{width:100%;margin:auto;padding:clamp(20px,4vw,34px);border:1px solid var(--line);border-radius:28px;background:linear-gradient(155deg,#10291cf7,#081a11f7);box-shadow:0 24px 70px #0008}.top{display:flex;align-items:center;gap:12px}.logo{display:grid;place-items:center;width:48px;height:48px;border-radius:16px;background:linear-gradient(145deg,#e9d28c,#b28b34);color:#142316;font-size:26px;font-weight:900}.brand{font-size:20px;font-weight:900;color:var(--gold)}.subbrand{font-size:12px;color:var(--muted)}.pill{display:inline-flex;margin-top:18px;border:1px solid #37694c;background:#143523;color:#d4efdc;border-radius:999px;padding:5px 13px;font-size:13px}h1{font-size:clamp(24px,5vw,32px);line-height:1.5;margin:12px 0 5px}p{color:var(--muted);font-size:14px;margin:8px 0 16px}.status{padding:13px 15px;border:1px solid #2b5039;background:#081a10;border-radius:15px;color:#d4e9da;font-size:14px;overflow-wrap:anywhere}.apps{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:17px 0}.app{display:flex;align-items:center;gap:11px;min-width:0;min-height:88px;padding:12px;border:1px solid #315c43;border-radius:19px;background:linear-gradient(135deg,#153c29,#10271b);color:var(--txt);text-decoration:none;text-align:right;transition:transform .15s,border-color .15s}.app:active{transform:scale(.985)}.app.recommended{border-color:var(--gold);box-shadow:0 0 0 1px #e6c66d30,inset 0 0 25px #e6c66d0a}.app.hidden{display:none}.appicon{flex:0 0 43px;height:43px;border-radius:14px;display:grid;place-items:center;background:#244b34;color:var(--gold);font-size:23px;font-weight:bold}.apptext{flex:1;min-width:0}.appname{display:block;font-size:16px;font-weight:900}.hint{display:block;color:#b3cdbb;font-size:11px;line-height:1.6;margin-top:2px}.arrow{font-size:22px;color:var(--gold)}.sectionTitle{font-weight:800;font-size:15px;margin:18px 0 6px}.secondary{display:flex;justify-content:center;align-items:center;width:100%;min-height:52px;margin-top:9px;padding:11px;border-radius:15px;background:#0b1b12;border:1px solid #355642;color:#eff8f1;text-decoration:none;font-weight:bold;font-size:14px;cursor:pointer}.small{font-size:12px;color:#8fa99a;margin-top:16px}.hiddenBox{display:none!important}.footer{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.footer span{font-size:11px;color:#a8c1b0;border:1px solid #284a35;border-radius:999px;padding:4px 9px}textarea{position:fixed;left:-10000px;top:0}@media(max-width:520px){body{padding:0}.shell{max-width:none;min-height:100vh;min-height:100svh}.card{min-height:100vh;min-height:100svh;border:0;border-radius:0;padding:calc(18px + env(safe-area-inset-top)) 16px calc(20px + env(safe-area-inset-bottom));display:flex;flex-direction:column;justify-content:center}.apps{grid-template-columns:1fr;gap:9px;margin:14px 0}.app{min-height:70px;padding:10px 13px}.appicon{height:43px;flex-basis:43px}.brand{font-size:19px}p{font-size:14px;margin-bottom:12px}}@media(max-height:720px){.card{justify-content:flex-start}.apps{gap:7px;margin:10px 0}.app{min-height:62px}.appicon{height:39px;flex-basis:39px}.pill{margin-top:12px}}
</style></head><body><main class="shell"><section class="card">
<div class="top"><div class="logo">✦</div><div><div class="brand">AhwazGSM</div><div class="subbrand">افزودن هوشمند اشتراک VPN</div></div></div>
<?php if (!$validUrl): ?><h1>لینک اشتراک معتبر نیست</h1><p>از داخل ربات دوباره دکمهٔ افزودن اشتراک را بزنید.</p>
<?php else: ?>
<div class="pill" id="devicePill">در حال تشخیص سیستم‌عامل…</div><h1 id="heading">افزودن اشتراک به اپلیکیشن</h1><p id="intro">سیستم‌عامل شما شناسایی می‌شود و اپ‌های سازگار در اولویت قرار می‌گیرند. اپ موردنظر را انتخاب کنید.</p>
<div class="status" id="status" role="status" aria-live="polite">در حال تشخیص دستگاه…</div>
<div class="sectionTitle">انتخاب برنامه</div><div class="apps" id="apps">
<?php foreach ($apps as $key=>$app): ?><a class="app" id="app-<?=h($key)?>" data-app="<?=h($key)?>" data-os="<?=h($app['os'])?>" href="<?=h($app['scheme'])?>"><span class="appicon"><?=h($app['icon'])?></span><span class="apptext"><span class="appname"><?=h($app['title'])?></span><span class="hint"><?=h($app['hint'])?></span></span><span class="arrow">‹</span></a><?php endforeach; ?>
</div>
<div id="windowsBox" class="hiddenBox"><div class="sectionTitle">ویندوز و v2rayN</div><p>اگر v2rayN نصب است، لینک اشتراک را کپی کنید و در پنجرهٔ برنامه Ctrl+V بزنید. واردکردن مستقیم با URI در همهٔ نسخه‌های v2rayN تضمین‌شده نیست.</p><button type="button" class="secondary" id="copyBtn">📋 کپی لینک اشتراک برای v2rayN</button><a class="secondary" href="https://github.com/2dust/v2rayN/releases" target="_blank" rel="noopener">دانلود v2rayN</a></div>
<div id="installBox" class="hiddenBox"><a id="installLink" class="secondary" href="https://hiddify.com/" target="_blank" rel="noopener">دریافت اپ مناسب</a></div>
<textarea id="subUrl" readonly><?=h($subscriptionUrl)?></textarea>
<div class="footer"><span>تشخیص خودکار سیستم‌عامل</span><span>طراحی سازگار با موبایل</span><span>لینک خصوصی اشتراک</span></div>
<p class="small">نکته: بازشدن اپ و افزودن موفق اشتراک به پشتیبانی همان اپ و فرمت لینک بستگی دارد. این کار به‌تنهایی VPN را روشن نمی‌کند. اگر انتقال خودکار مسدود شد، یکی از برنامه‌ها را دستی انتخاب کنید.</p>
<script>
(()=>{
 const ua=navigator.userAgent||'';const ios=/iPhone|iPad|iPod/i.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);const android=/Android/i.test(ua);const windows=/Windows/i.test(ua);const mac=/Macintosh|Mac OS X/i.test(ua)&&!ios;
 const links=<?=json_encode($schemes, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
 const installs=<?=json_encode($installLinks, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
 const status=document.getElementById('status'),pill=document.getElementById('devicePill'),intro=document.getElementById('intro');let preferred='hiddify',osName='دستگاه ناشناخته';
 if(ios){osName='آیفون / iOS';preferred='v2box';intro.textContent='اپ‌های آیفون در اولویت قرار گرفتند. برای افزودن اشتراک، برنامهٔ نصب‌شده را انتخاب کنید.';}
 else if(android){osName='اندروید';preferred='v2rayng';intro.textContent='V2RayNG در اولویت قرار گرفت؛ می‌توانید Happ یا Hiddify و sing-box را هم انتخاب کنید.';}
 else if(windows){osName='ویندوز';preferred='v2rayn';intro.textContent='برای v2rayN روش مطمئن، کپی لینک و واردکردن آن در برنامه است. اپ‌های دارای لینک اختصاصی را می‌توانید از فهرست انتخاب کنید.';document.getElementById('windowsBox').classList.remove('hiddenBox');}
 else if(mac){osName='macOS';preferred='hiddify';}
 pill.textContent='دستگاه شناسایی‌شده: '+osName;
 document.querySelectorAll('[data-app]').forEach(a=>{const key=a.dataset.app;const target=a.dataset.os;const visible=target==='all'||(ios&&target==='ios')||(android&&target==='android')||(windows&&target==='windows')||(!ios&&!android&&!windows&&mac);a.classList.toggle('hidden',!visible);a.classList.toggle('recommended',key===preferred);a.addEventListener('click',async ev=>{if(key==='v2rayn'){ev.preventDefault();try{await navigator.clipboard.writeText(document.getElementById('subUrl').value);status.textContent='لینک اشتراک کپی شد؛ v2rayN را باز کنید و Ctrl+V بزنید.';}catch(e){status.textContent='لینک را با دکمهٔ کپی پایین صفحه کپی کنید و در v2rayN کلید Ctrl+V بزنید.';}return;}status.textContent='در حال تلاش برای بازکردن '+key+'…';document.getElementById('installLink').href=installs[key]||'https://hiddify.com/';document.getElementById('installBox').classList.remove('hiddenBox');});});
 if(!ios&&!android&&!windows&&!mac){document.querySelectorAll('[data-app]').forEach(a=>a.classList.remove('hidden'));}
 document.getElementById('installLink').href=installs[preferred]||'https://hiddify.com/';
 if(windows){status.textContent='ویندوز شناسایی شد. روی v2rayN بزنید تا لینک کپی شود؛ سپس داخل برنامه Ctrl+V بزنید.';}
 else {status.textContent='سیستم‌عامل شناسایی شد. تلاش می‌کنیم اپ پیشنهادی را باز کنیم…';}
 const copyBtn=document.getElementById('copyBtn');copyBtn?.addEventListener('click',async()=>{const sub=document.getElementById('subUrl').value;try{await navigator.clipboard.writeText(sub);status.textContent='لینک اشتراک کپی شد؛ v2rayN را باز کنید و Ctrl+V بزنید.';}catch(e){const t=document.getElementById('subUrl');t.style.position='static';t.style.left='auto';t.style.width='100%';t.style.height='72px';t.select();status.textContent='لینک را کپی کنید؛ سپس در v2rayN کلید Ctrl+V را بزنید.';}});
 // Best-effort app handoff on mobile. OS/browser may require a user tap for external schemes.
 if(ios||android){const a=document.getElementById('app-'+preferred);if(a){window.setTimeout(()=>{window.location.href=links[preferred];status.textContent='اگر اپ باز نشد، دکمهٔ اپ نصب‌شده را لمس کنید یا آن را نصب کنید.';document.getElementById('installBox').classList.remove('hiddenBox');},300);}}
})();
</script>
<?php endif; ?></section></main></body></html>
