<!DOCTYPE html><html lang="zh-CN"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 - 合同管理系统</title>
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap/css/bootstrap.min.css')?>">
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap-icons/bootstrap-icons.v2.48.0.min.css')?>">
<style>body{background:linear-gradient(135deg,#0b5ed7 0%,#6ea8fe 100%);min-height:100vh}.login-card{max-width:400px;margin:10vh auto;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.2)}.login-card .card-body{padding:2.5rem}</style>
<!-- 钉钉 JSAPI SDK：引入后定义全局 dd（免登/setTitle 等依赖此对象）。新版钉钉/工作台 H5 不会自动注入 dd，必须显式加载，否则无感登录报 dd is not defined -->
<script src="https://g.alicdn.com/dingding/dingtalk-jsapi/3.1.0/dingtalk.open.js"></script>
</head><body>
<div class="container"><div class="card login-card"><div class="card-body">
<h3 class="text-center mb-4"><i class="bi bi-file-text text-primary"></i> 合同管理系统</h3>
<form id="loginForm">
<div class="mb-3"><input type="text" name="username" class="form-control form-control-lg" placeholder="账号" required autofocus></div>
<div class="mb-3"><input type="password" name="password" class="form-control form-control-lg" placeholder="密码" required></div>
<button type="submit" class="btn btn-primary btn-lg w-100 mb-3"><i class="bi bi-box-arrow-in-right"></i> 登 录</button>
</form>
</div></div></div>
<script src="<?=asset_url('vendor/bootstrap/js/bootstrap.bundle.min.js')?>"></script>
<script>
document.getElementById('loginForm').addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(this);
var dp=new URLSearchParams(location.search).get('redirect')||'';if(dp)fd.set('redirect',dp);
// 2026-08-05 根治：登录提交前先 GET 一次让服务器重新同步 session 与 cookie 的 CSRF token，
// 覆盖「cookie 缺失 / 会话过期后旧 token 失配」导致的 403「CSRF 校验失败」（用户无需手动刷新）
function getCsrf(){return (document.cookie.match(/(?:^|; )csrf_token=([^;]*)/)||[])[1]||'';}
function doSubmit(csrf){fetch('/login',{method:'POST',body:new URLSearchParams(fd),headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf}}).then(r=>r.json()).then(res=>{if(res.code===0){if(res.data&&res.data.force_reset){location.href='/profile/change-password';}else{location.href=(res.data&&res.data.redirect)||'/dashboard';}}else showLoginErr(res.msg);});}
fetch('/login',{method:'GET'}).then(function(){doSubmit(getCsrf());}).catch(function(){doSubmit(getCsrf());});
});
// 2026-08-03 复查修复：登录失败提示改用轻量 toast（替代原生 alert，与全站体验一致）
function showLoginErr(msg){var el=document.getElementById('loginErr');if(!el){el=document.createElement('div');el.id='loginErr';el.style.cssText='position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;background:#fa5151;color:#fff;padding:10px 20px;border-radius:8px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.2)';document.body.appendChild(el);}el.textContent=msg;el.style.display='block';setTimeout(function(){el.style.display='none';},3000);}
// DingTalk SSO（v2.35.x 对齐 entry.php 免登策略）：
// ① 是否钉钉环境以 UA 为主（SDK 加载后 dd 在普通浏览器也会被定义，不能再用 typeof dd 判定）；
// ② dd 可能尚未就绪（极少数 WebView 时序），用看门狗轮询等待，最多 8s，超时则降级为账号密码登录（不强制跳转，本页即登录页）；
// ③ 兼容新旧 SDK：优先一段式 dd.getAuthCode，否则三段式 dd.runtime.permission.requestAuthCode。
(function(){
  if(!/DingTalk/i.test(navigator.userAgent || '')) return; // 非钉钉环境：走普通账号密码登录
  function _dtGetAuthCode(corpId, onSuccess, onFail){
    if (typeof dd !== 'undefined' && typeof dd.getAuthCode === 'function') {
      dd.getAuthCode({ corpId: corpId, onSuccess: onSuccess, onFail: onFail });
    } else if (typeof dd !== 'undefined' && dd.runtime && dd.runtime.permission && dd.runtime.permission.requestAuthCode) {
      dd.runtime.permission.requestAuthCode({ corpId: corpId, onSuccess: onSuccess, onFail: onFail });
    } else if (onFail) {
      onFail({ errorMessage: '钉钉 JSAPI 不可用' });
    }
  }
  function startSSO(){
    var _url=location.href.split('#')[0];
    fetch('/dingtalk/jsapi-config?url='+encodeURIComponent(_url)).then(function(r){return r.json();}).then(function(res){
      var d=res.data||{};
      try {
        if (typeof dd !== 'undefined' && dd.config && d.signature) {
          dd.config({agentId:d.agentId,corpId:d.corpId,timeStamp:d.timestamp,nonceStr:d.nonceStr,signature:d.signature,jsApiList:['runtime.permission.requestAuthCode']});
        }
      } catch(e){ console.warn('dd.config 失败（不影响免登）', e); }
      // v2.37.3：JSAPI 2.0 下 dd.getAuthCode 可独立调用，不依赖 dd.ready（消息卡片 webview 中 dd.ready 常不触发）
      _dtGetAuthCode(d.corpId||'', function(r){
          fetch('/dingtalk/sso-login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({code:r.code})})
            .then(function(x){return x.json();}).then(function(s){
              if(s.code===0){ try{localStorage.setItem('token',s.data.token);}catch(e){}
                var dp=new URLSearchParams(location.search).get('redirect')||'';
                location.href=(dp.charAt(0)==='/'&&dp.charAt(1)!=='/'&&dp.indexOf('\\')<0)?dp:'/dashboard';
              } else { console.warn('钉钉免登失败', s.msg); }
            });
        }, function(e){ console.warn('钉钉授权失败',e); });
    });
  }
  // 看门狗：dd 未就绪时轮询等待，最多 8s；就绪即发起免登；超时降级为账号密码登录（不强制跳转）
  var _waited=0;
  (function waitDd(){
    if (typeof dd !== 'undefined' && (typeof dd.getAuthCode === 'function' || (dd.runtime && dd.runtime.permission && typeof dd.runtime.permission.requestAuthCode === 'function'))) { startSSO(); return; }
    if (_waited>=8000) { console.warn('钉钉 JSAPI 未就绪，降级为账号密码登录'); return; }
    _waited+=200; setTimeout(waitDd, 200);
  })();
})();
</script></body></html>
