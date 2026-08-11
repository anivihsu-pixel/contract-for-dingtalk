</div></div>

<!-- 页脚版权信息（PC 与移动端统一展示；系统设置「系统配置」页可维护，v2.34.0） -->
<div class="site-footer text-center text-muted small py-3">
  <?=htmlspecialchars($site_copyright ?? '', ENT_QUOTES) ?>
</div>

<!-- Mobile bottom nav -->
<div class="mobile-nav">
<a href="/dashboard"><i class="bi bi-speedometer2"></i><span>首页</span></a>
<a href="/contract"><i class="bi bi-file-text"></i><span>合同</span></a>
<a href="/customer"><i class="bi bi-people"></i><span>客户</span></a>
<a href="/approval"><i class="bi bi-check2-circle"></i><span>审批</span></a>
<a href="/archive"><i class="bi bi-archive"></i><span>归档</span></a>
<a href="javascript:void(0)" onclick="document.querySelector('.sidebar').classList.toggle('show')"><i class="bi bi-list"></i><span>菜单</span></a>
</div>

<script src="<?=asset_url('vendor/bootstrap/js/bootstrap.bundle.min.js')?>"></script>
<script src="<?=asset_url('js/attachments.js')?>"></script>
<script src="<?=asset_url('js/app.js')?>"></script>
<script>
// 防止快速重复点击站内链接导致重复导航/并发请求（缓解“连续快速点菜单出错”）
// 仅拦截 500ms 内同一 URL 的重复点击（双击同一菜单）；不同菜单间快速切换不受影响。
(function(){
  var locks = {};
  document.addEventListener('click', function(e){
    var a = e.target.closest ? e.target.closest('a[href]') : null;
    if(!a) return;
    var href = a.getAttribute('href') || '';
    // 放行锚点/js/新窗口/外部http/tel/mailto 协议（电话拨打不应被防抖锁拦截）
    if(href.charAt(0)==='#' || href.indexOf('javascript:')===0 || a.target==='_blank' || /^https?:/i.test(href) || /^(tel|mailto):/i.test(href)) return;
    if(locks[href]){ e.preventDefault(); return; }
    locks[href] = true;
    setTimeout(function(){ delete locks[href]; }, 500);
  }, true);
})();
// 电话号码复制按钮（事件委托，支持 innerHTML 重绘后仍生效）
document.addEventListener('click', function(e){
  var el = e.target.closest ? e.target.closest('.phone-copy') : null;
  if(!el) return;
  var phone = el.getAttribute('data-phone') || '';
  if(!phone) return;
  if(navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(phone).then(function(){ showToast('已复制 '+phone, 'success'); }, function(){ fallbackCopy(phone); });
  } else {
    fallbackCopy(phone);
  }
});
function fallbackCopy(text){
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position='fixed'; ta.style.opacity='0';
  document.body.appendChild(ta); ta.select();
  try{ document.execCommand('copy'); showToast('已复制 '+text, 'success'); }
  catch(_){ showToast('复制失败，请手动选择', 'error'); }
  document.body.removeChild(ta);
}
</script>
</body></html>
