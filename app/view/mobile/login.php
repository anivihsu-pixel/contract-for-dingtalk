<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 - 合同管理系统</title>
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap-icons/bootstrap-icons.v2.43.2.min.css')?>">
<link rel="stylesheet" href="<?=asset_url('css/mobile.css')?>">
<style>
  body{background:linear-gradient(135deg,#0b5ed7 0%,#6ea8fe 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .m-login{width:100%;max-width:360px;background:#fff;border-radius:18px;padding:30px 22px;box-shadow:0 14px 44px rgba(0,0,0,.2)}
  .m-login .brand{text-align:center;margin-bottom:22px}
  .m-login .brand i{font-size:34px;color:var(--m-brand)}
  .m-login .brand h4{margin:10px 0 0;font-size:18px;font-weight:600;color:var(--m-text)}
  .m-login .field{margin-bottom:14px}
  .m-login .field input{width:100%;height:46px;border:1px solid #e3e6eb;border-radius:10px;padding:0 14px;font-size:15px;outline:none}
  .m-login .field input:focus{border-color:var(--m-brand)}
  .m-login .err{color:#e54545;font-size:13px;text-align:center;min-height:18px;margin-top:8px}
</style>
</head>
<body>
<div class="m-login">
  <div class="brand">
    <i class="bi bi-file-text"></i>
    <h4>合同管理系统</h4>
  </div>
  <form id="loginForm">
    <div class="field"><input type="text" name="username" placeholder="账号" autocomplete="username" required></div>
    <div class="field"><input type="password" name="password" placeholder="密码" autocomplete="current-password" required></div>
    <button type="submit" class="m-btn m-btn-brand" style="width:100%;height:46px;font-size:16px">登 录</button>
    <div class="err" id="loginErr"></div>
  </form>
</div>
<script>
(function(){
  var form = document.getElementById('loginForm');
  var err  = document.getElementById('loginErr');
  form.addEventListener('submit', function(e){
    e.preventDefault();
    err.textContent = '';
    var fd = new FormData(form);
    var btn = form.querySelector('button[type=submit]');
    btn.disabled = true; btn.textContent = '登录中…';
    // 2026-08-05 根治：提交前先 GET 一次让服务器重新同步 session 与 cookie 的 CSRF token，
    // 覆盖「cookie 缺失 / 会话过期后旧 token 失配」导致的 403「CSRF 校验失败」
    var getCsrf = function(){ return (document.cookie.match(/(?:^|; )csrf_token=([^;]*)/) || [])[1] || ''; };
    var doSubmit = function(csrf){
      fetch('/login', {
        method: 'POST',
        body: new URLSearchParams(fd),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf }
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        btn.disabled = false; btn.textContent = '登 录';
        if (res.code === 0) {
          if (res.data && res.data.force_reset) {
            location.href = '/profile/change-password';
          } else {
            var dp = new URLSearchParams(location.search).get('redirect');
            location.href = dp || (res.data && res.data.redirect) || '/m';
          }
        } else {
          err.textContent = res.msg || '登录失败';
        }
      })
      .catch(function(){
        btn.disabled = false; btn.textContent = '登 录';
        err.textContent = '网络异常，请重试';
      });
    };
    fetch('/login', { method: 'GET' }).then(function(){ doSubmit(getCsrf()); }).catch(function(){ doSubmit(getCsrf()); });
  });
})();
</script>
</body>
</html>
