<?php $title='修改密码'; $menu_active=''; include __DIR__.'/../layout/header.php'; ?>
<div class="container" style="max-width:480px;margin-top:8vh">
<div class="card shadow-sm"><div class="card-body p-4">
  <h4 class="mb-3 text-center"><i class="bi bi-key"></i> 修改初始密码</h4>
  <div class="alert alert-warning py-2 small">系统检测到您使用的是初始/弱密码，首次登录必须修改后方可继续使用。</div>
  <input type="hidden" name="force" value="1">
  <div class="mb-3">
    <label class="form-label" for="pwdNew">新密码（至少 8 位）</label>
    <input type="password" id="pwdNew" class="form-control form-control-lg" placeholder="请输入新密码" autofocus>
  </div>
  <div class="mb-3">
    <label class="form-label" for="pwdNew2">确认新密码</label>
    <input type="password" id="pwdNew2" class="form-control form-control-lg" placeholder="再次输入新密码">
  </div>
  <button class="btn btn-primary btn-lg w-100" onclick="submitForceChange()"><i class="bi bi-check-circle"></i> 确认修改</button>
</div></div>
</div>
<script>
function submitForceChange(){
  var p1=document.getElementById('pwdNew').value;
  var p2=document.getElementById('pwdNew2').value;
  if(!p1||p1.length<8){ showToast('新密码至少 8 位','warning'); return; }
  if(p1!==p2){ showToast('两次输入的密码不一致','warning'); return; }
  $ajax('/ajax/admin/change-password',{
    method:'POST',
    body:new URLSearchParams({new_password:p1, force:1})
  }).then(function(res){
    showToast(res.msg||'操作完成', res.code===0?'success':'error');
    if(res.code===0){ setTimeout(function(){ location.href='/dashboard'; }, 800); }
  });
}
</script>
<?php include __DIR__.'/../layout/footer.php'; ?>
