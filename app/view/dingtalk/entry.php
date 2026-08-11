<!DOCTYPE html><html lang="zh-CN"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>正在登录…</title>
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap/css/bootstrap.min.css')?>">
<style>body{background:linear-gradient(135deg,#0b5ed7,#3d8bfd);display:flex;align-items:center;justify-content:center;height:100vh;margin:0}</style>
<!-- 钉钉 JSAPI SDK：引入后定义全局 dd（免登依赖此对象）。新版钉钉/工作台 H5 不会自动注入 dd，必须显式加载，否则报 dd is not defined -->
<script src="https://g.alicdn.com/dingding/dingtalk-jsapi/3.1.0/dingtalk.open.js"></script>
</head><body>
<div class="text-center">
  <div class="spinner-border text-white" role="status"></div>
  <div class="mt-3 text-white" style="opacity:.9">正在通过钉钉登录…</div>
</div>
<script src="<?=asset_url('vendor/bootstrap/js/bootstrap.bundle.min.js')?>"></script>
<script>
var TO = <?= json_encode($to, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
// P1（2026-08-09）：OAuth state——服务端签发的一次性 nonce，随 code 回传供 ssoLogin 校验
var OAUTH_STATE = <?= json_encode($oauth_state ?? '', JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
(function(){
  // 是否钉钉环境以 UA 为主（SDK 加载后 dd 在普通浏览器也会被定义，不能再用 typeof dd 判定）
  var isDingTalk = /DingTalk/i.test(navigator.userAgent || '');
  // 非钉钉环境：直接回退账号密码登录（携带深链，登录后跳回目标页）
  if (!isDingTalk) {
    location.href = '/login?redirect=' + encodeURIComponent(TO);
    return;
  }
  // 看门狗：若 8s 内未完成免登（SDK 加载失败 / 用户拒绝 / 权限不足 / 消息卡片 webview 未注入 JSAPI），回退登录页，避免永久转圈
  var fallback = setTimeout(function(){
    location.href = '/login?redirect=' + encodeURIComponent(TO);
  }, 8000);
  function goLogin(){ clearTimeout(fallback); location.href = '/login?redirect=' + encodeURIComponent(TO); }

  // 兼容新旧 SDK：优先一段式 dd.getAuthCode，否则三段式 dd.runtime.permission.requestAuthCode
  function getAuthCode(corpId, onSuccess, onFail){
    if (typeof dd !== 'undefined' && typeof dd.getAuthCode === 'function') {
      dd.getAuthCode({ corpId: corpId, onSuccess: onSuccess, onFail: onFail });
    } else if (typeof dd !== 'undefined' && dd.runtime && dd.runtime.permission && typeof dd.runtime.permission.requestAuthCode === 'function') {
      dd.runtime.permission.requestAuthCode({ corpId: corpId, onSuccess: onSuccess, onFail: onFail });
    } else {
      onFail({ errorMessage: '钉钉 JSAPI 不可用' });
    }
  }

  // 钉钉环境：先 jsapi-config 签名，再 requestAuthCode 免登，成功后直达目标页。
  // ⚠️ 关键修复（v2.37.3）：钉钉 JSAPI 2.0（本系统引入的 3.1.0 SDK）下 dd.getAuthCode 可独立调用，
  // 不依赖 dd.ready / dd.config。消息卡片(action_card single_url)打开的 webview 中 dd.ready 常常不触发，
  // 旧代码把 getAuthCode 包在 dd.ready 内 → 免登永远不执行 → 8s 看门狗回退 /login（即「先点消息只跳登录页」的根因）。
  // 现改为：best-effort dd.config（失败仅告警，不阻断免登），随后直接调用 getAuthCode。
  function doSso(){
    var url = location.href.split('#')[0];
    fetch('/dingtalk/jsapi-config?url=' + encodeURIComponent(url))
      .then(function(r){ return r.json(); })
      .then(function(res){
        var d = res.data || {};
        try {
          if (typeof dd !== 'undefined' && dd.config && d.signature) {
            dd.config({
              agentId: d.agentId,
              corpId: d.corpId,
              timeStamp: d.timestamp,
              nonceStr: d.nonceStr,
              signature: d.signature,
              jsApiList: ['runtime.permission.requestAuthCode']
            });
          }
        } catch(e){ console.warn('dd.config 失败（不影响免登）', e); }
        getAuthCode(d.corpId || '', function(r){
          fetch('/dingtalk/sso-login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: r.code, state: OAUTH_STATE })
          }).then(function(x){ return x.json(); }).then(function(s){
            if (s.code === 0) {
              try { localStorage.setItem('token', s.data.token); } catch(e){}
              clearTimeout(fallback);
              // 移动端 UA：审批消息落地移动端详情页（/m/approval/{id}），该页已含合同摘要/附件/正文卡片，
              // 且仅当前节点待审批人可见「同意/驳回」按钮（纯抄送人自动隐藏，显示「你不是当前节点审批人，无需操作」）；
              // 桌面端 UA：保留 PC 审批详情页。避免手机钉钉打开 PC 布局导致「无合同简介/附件」「抄送人误显审批按钮」等问题。
              var _ua = navigator.userAgent || '';
              var _target = (/Mobi|Android|iPhone|iPad|iPod|Windows Phone/i.test(_ua) && typeof TO === 'string' && TO.indexOf('/approval/') === 0) ? ('/m' + TO) : TO;
              location.href = _target;
            } else {
              console.warn('钉钉免登失败', s.msg);
              goLogin();
            }
          }).catch(function(){ goLogin(); });
        }, function(e){
          // 授权失败（用户取消 / 无权限 / webview 无 JSAPI 原生桥）：回退账号密码登录
          console.warn('钉钉授权失败', e);
          goLogin();
        });
      })
      .catch(function(){ goLogin(); });
  }

  // 等 dd 就绪（CDN SDK 加载后 dd.getAuthCode / dd.runtime.permission.requestAuthCode 即存在）即发起免登，
  // 不再等待 dd.ready（消息卡片 webview 中 dd.ready 可能不触发）。
  var waited = 0;
  (function waitDd(){
    if (typeof dd !== 'undefined' && (typeof dd.getAuthCode === 'function' || (dd.runtime && dd.runtime.permission && typeof dd.runtime.permission.requestAuthCode === 'function'))) {
      doSso(); return;
    }
    if (waited >= 8000) { goLogin(); return; }
    waited += 200; setTimeout(waitDd, 200);
  })();
})();
</script>
</body></html>
