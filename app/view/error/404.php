<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>页面不存在 · 合同管理系统</title>
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap/css/bootstrap.min.css')?>">
<link rel="stylesheet" href="<?=asset_url('vendor/bootstrap-icons/bootstrap-icons.v2.43.2.min.css')?>">
<style>
  body{ background:var(--bg-page); min-height:100vh; display:flex; align-items:center; justify-content:center; }
  .e-box{ background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); padding:40px 36px; max-width:440px; text-align:center; }
  .e-icon{ font-size:54px; color:#6c757d; }
  .e-title{ font-size:20px; font-weight:600; margin-top:10px; color:var(--text-main); }
  .e-desc{ color:var(--text-3); margin-top:8px; font-size:14px; }
  .e-btn{ margin-top:22px; }
</style>
</head>
<body>
  <div class="e-box">
    <div class="e-icon"><i class="bi bi-compass"></i></div>
    <?php if(!empty($err_msg)): ?>
    <div class="e-title"><?=htmlspecialchars($err_msg)?></div>
    <?php else: ?>
    <div class="e-title">页面不存在</div>
    <?php endif; ?>
    <div class="e-desc">您访问的链接可能已失效、被移除或输入有误。</div>
    <div class="e-btn">
      <a href="<?=$back_url ?? '/dashboard'?>" class="btn btn-primary px-4">
        <i class="bi bi-arrow-left"></i> <?=$home_text ?? '返回驾驶舱'?>
      </a>
    </div>
  </div>
</body>
</html>
