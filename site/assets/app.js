window.AG = window.AG || {};
window.AG.bot = "ahwazgsm14_bot";
const AG={
 qs:s=>document.querySelector(s),
 async get(a){return (await fetch('api.php?action='+a)).json()},
 money:n=>new Intl.NumberFormat('fa-IR').format(Number(n||0))+' تومان',
 toast(m){let e=this.qs('#toast');e.textContent=m;e.classList.remove('hidden');setTimeout(()=>e.classList.add('hidden'),3500)},
 async init(){
   AG.bot = AG.bot || 'ahwazgsm14_bot'; this.qs('#botLink').href='https://t.me/'+AG.bot;
   await this.autoTelegramLogin(); await this.authUI(); await this.loadProducts(); await this.loadPanels();
   if(location.hash==='#dashboard') this.openDashboard();
 },
 async autoTelegramLogin(){
   try{if(window.Telegram?.WebApp?.initData){let fd=new FormData();fd.append('initData',Telegram.WebApp.initData);let r=await fetch('api.php?action=login_webapp',{method:'POST',body:fd});let j=await r.json();if(j.ok) window.AG.logged=true;}}catch(e){}
 },
 async authUI(){let me=await this.get('me');window.AG.logged=!!me.user;this.qs('#auth').innerHTML=me.user?`<button class="btn ghost" onclick="AG.openDashboard()">پنل من</button>`:`<button class="btn primary" onclick="AG.showLogin()">ورود</button>`},
 showLogin(){
   this.qs('#loginModal').classList.remove('hidden'); let box=this.qs('#tgLogin');box.innerHTML='';
   if(!AG.bot){AG.bot='ahwazgsm14_bot';}
   let s=document.createElement('script');s.async=true;s.src='https://telegram.org/js/telegram-widget.js?22';s.setAttribute('data-telegram-login',AG.bot);s.setAttribute('data-size','large');s.setAttribute('data-radius','10');s.setAttribute('data-auth-url',location.href.replace(/index\.php.*/,'')+'api.php?action=telegram_callback');box.appendChild(s)
 },
 hideLogin(){this.qs('#loginModal').classList.add('hidden')},
 async loadProducts(){let j=await this.get('products');let box=this.qs('#products');if(!j.ok){box.textContent='خطا در دریافت سرویس‌ها';return} box.innerHTML=j.items.length?j.items.map((x,i)=>`<article class="plan">${i===1?'<span class="badge">پیشنهادی</span>':''}<h3>${this.esc(x.name_product||'سرویس')}</h3><small>${this.esc(x.note||'')}</small><div class="price">${this.money(x.price_product)}</div><small>📦 ${x.Volume_constraint||'نامحدود'} گیگ | ⏳ ${x.Service_time||'نامحدود'} روز</small><button class="btn primary" onclick="AG.buy('${this.escAttr(x.code_product)}')">خرید سرویس</button></article>`).join(''):'<div class="loading">در حال حاضر سرویسی موجود نیست.</div>'},
 async loadPanels(){let j=await this.get('panels');let s=this.qs('#panelSelect');if(j.ok)j.items.forEach(x=>s.insertAdjacentHTML('beforeend',`<option value="${this.escAttr(x.code_panel)}">${this.esc(x.name_panel)}</option>`))},
 async openDashboard(){if(!window.AG.logged){this.showLogin();return}let j=await this.get('dashboard');if(!j.ok){this.toast(j.message);return}this.qs('#dashboard').classList.remove('hidden');location.hash='dashboard';this.qs('#welcome').textContent='خوش آمدید '+(j.user.username?'@'+j.user.username:'');this.qs('#balance').textContent=this.money(j.user.balance);this.qs('#serviceCount').textContent=new Intl.NumberFormat('fa-IR').format(j.services.length);this.qs('#affiliateCount').textContent=new Intl.NumberFormat('fa-IR').format(j.user.affiliate_count);
 this.qs('#services').innerHTML=j.services.length?j.services.map(x=>`<div class="row"><b>${this.esc(x.name_product)}</b><span>${this.esc(x.username)}</span><span>${this.esc(x.Status)}</span><span>${this.money(x.price_product)}</span></div>`).join(''):'<p>هنوز سرویسی ندارید.</p>';
 this.qs('#payments').innerHTML=j.payments.length?j.payments.slice(0,8).map(x=>`<div class="row"><b>${this.esc(x.name_product||x.id_payment||'تراکنش')}</b><span>${this.esc(x.payment_Status||'')}</span><span>${this.money(x.amount||x.price||0)}</span><span></span></div>`).join(''):'<p>تراکنشی برای نمایش وجود ندارد.</p>';
 this.qs('#dashboard').scrollIntoView({behavior:'smooth'});
 },
 closeDashboard(){this.qs('#dashboard').classList.add('hidden');history.replaceState(null,'',location.pathname)},
 async buy(id){if(!window.AG.logged){this.showLogin();return}let panel=this.qs('#panelSelect').value;if(!panel){this.toast('ابتدا سرور موردنظر را انتخاب کنید.');return}if(!confirm('خرید این سرویس با استفاده از کیف پول انجام شود؟'))return;let r=await fetch('api.php?action=buy',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({service_id:id,country_id:panel})});let j=await r.json().catch(()=>({status:false,msg:'پاسخ نامعتبر'}));if(j.status||j.ok){this.toast(j.msg||'خرید با موفقیت انجام شد.');setTimeout(()=>this.openDashboard(),700)}else this.toast(j.msg||j.message||'خرید انجام نشد.')},
 esc:s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])),
 escAttr(s){return String(s??'').replace(/['\\]/g,'')},
};document.addEventListener('DOMContentLoaded',()=>AG.init());
