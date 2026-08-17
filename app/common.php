<?php
// +----------------------------------------------------------------------
// | 全局辅助函数
// +----------------------------------------------------------------------

use think\facade\Session;
use think\facade\Cache;
use think\facade\Db;

/** JSON 成功响应 */
function json_success($data = null, string $msg = 'ok', int $code = 0): \think\response\Json
{
    return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
}

/** JSON 错误响应 */
function json_error(string $msg = 'error', int $code = 1, $data = null): \think\response\Json
{
    return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
}

/** Layui table 数据格式 (用于列表页 AJAX) */
function layui_table(array $list, int $total, int $code = 0, string $msg = ''): \think\response\Json
{
    return json(['code' => $code, 'msg' => $msg, 'count' => $total, 'data' => $list]);
}

/** 当前用户 ID */
function user_id(): int
{
    return Session::get('user_id', 0);
}

/** 当前用户 */
function current_user(): ?array
{
    return Session::get('user') ?: null;
}

/** 是否管理员 */
function is_admin(): bool
{
    $user = current_user();
    return !empty($user['is_admin']);
}

/**
 * 静态资源 URL（带文件修改时间指纹，解决浏览器缓存）
 * v2.28.2：以 filemtime 作为指纹，改一个文件只刷新该文件缓存，精准高效；
 * 文件不存在时回退为无 ?v= 后缀的原始路径（兜底，避免 500）。
 * @param string $path 相对于 public/static/ 的路径，如 'js/contract.js'、'css/mobile.css'
 * @return string 形如 '/static/js/contract.js?v=1753200000'
 */
function asset_url(string $path): string
{
    static $cache = [];
    $path = ltrim($path, '/');
    if (isset($cache[$path])) return $cache[$path];

    $fullPath = public_path() . 'static/' . $path;
    $url = '/static/' . $path;
    if (is_file($fullPath)) {
        $url .= '?v=' . filemtime($fullPath);
    }
    return $cache[$path] = $url;
}

/**
 * 读取当前系统版本号
 * 优先级：config/version.php（发布包内始终存在，不依赖根目录 VERSION.md 部署位置）→ VERSION.md（本地开发回退）→ 'unknown'
 * 用于侧栏/页脚/系统配置页的统一版本展示。
 * @return string 如 v2.35.7；双重回退均失败时返回 'unknown'
 */
function app_version(): string
{
    static $v = null;
    if ($v !== null) {
        return $v;
    }
    // 1) 优先：config/version.php（release.sh 打包时自动写入，部署后始终可读）
    $configFile = config_path() . 'version.php';
    if (is_file($configFile)) {
        $ver = include $configFile;
        if (is_string($ver) && $ver !== '') {
            $v = $ver;
            return $v;
        }
    }
    // 2) 回退：VERSION.md（本地开发环境，发布前）
    $v = 'unknown';
    $file = root_path() . 'VERSION.md';
    if (is_file($file)) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                if (preg_match('/当前版本：\s*(v[\d.]+)/u', $line, $m)) {
                    $v = $m[1];
                    break;
                }
            }
        }
    }
    return $v;
}

/**
 * 读取单条系统配置值（system_config 表），带 300s 短缓存以缓解每页渲染的查询压力。
 * @param string $key     配置键
 * @param string $default 缺失时的默认值
 * @return string 配置值（始终为字符串）
 */
function sys_config(string $key, string $default = ''): string
{
    $cacheKey = 'syscfg_' . $key;
    $val = \think\facade\Cache::get($cacheKey);
    if ($val === null) {
        try {
            $row = \think\facade\Db::name('system_config')->where('config_key', $key)->value('config_value');
        } catch (\Throwable $e) {
            $row = null;
        }
        $val = ($row === null || $row === false) ? $default : (string)$row;
        \think\facade\Cache::set($cacheKey, $val, 300);
    }
    return $val;
}

/**
 * 生成合同编号 HT-YYYYMMDD-序号
 * v2.2 优化：支持通过 system_config 自定义前缀和格式
 * 参考：勾股OA 的自定义合同编号规则 (Apache-2.0)
 * 源：https://github.com/gouguoyin/office
 */
function generate_contract_no(string $categoryCode = ''): string
{
    // REV-19：编号生成入事务，并配合 contract_no 唯一索引（兜底并发）保证高并发下不重复
    return \think\facade\Db::transaction(function () use ($categoryCode) {
        $configs = \think\facade\Db::name('system_config')
            ->whereIn('config_key', ['contract_no_prefix', 'contract_no_format'])
            ->column('config_value', 'config_key');

        $prefix = $configs['contract_no_prefix'] ?? 'HT';
        $fmt    = $configs['contract_no_format'] ?? '{PREFIX}-{DATE}-{SEQ}';
        // 兼容旧格式（无花括号占位符，如历史配置写入的 'PREFIX-DATE-SEQ'）：
        // 自动补花括号，使已部署环境的历史配置也能正确替换，避免编号原样显示 'PREFIX-DATE-SEQ'（#4 修复）。
        if (strpos($fmt, '{PREFIX}') === false) {
            $fmt = str_replace(
                ['PREFIX', 'CATEGORY', 'DATE', 'SEQ'],
                ['{PREFIX}', '{CATEGORY}', '{DATE}', '{SEQ}'],
                $fmt
            );
        }
        $datePart = date('Ymd');

        $catCode = strtoupper($categoryCode ?: 'XX');

        // REV-19③：根据当前格式构造「前缀%」模式（SEQ 留空），使 LIKE 走索引。
        // 例如默认 PREFIX-DATE-SEQ → "HT-20260716-%"，避免 LIKE '%date%' 全表扫描且无法命中索引。
        $likePattern = str_replace(
            ['{PREFIX}', '{CATEGORY}', '{DATE}', '{SEQ}'],
            [$prefix, $catCode, $datePart, ''],
            $fmt
        );
        $likePattern = rtrim($likePattern, '-_') . '%';

        // 查询同日最大序号（前缀匹配可命中 contract_no 索引）
        $seqQuery = \think\facade\Db::name('contract')
            ->where('contract_no', 'like', $likePattern)
            ->order('contract_no', 'desc');
        // P2-2（M3）：高并发下 SELECT 不加锁会读到相同最大序号，导致 uk_contract_no 唯一索引冲突。
        // MySQL 用 SELECT ... FOR UPDATE 行锁（ThinkPHP lock(true) 生成 FOR UPDATE，对空结果集也加 gap 锁防并发插入）；
        // SQLite 不支持 FOR UPDATE，故不锁，仅靠外层事务 + 下方唯一索引兜底重试（保证不报错、最终唯一）。
        if (config('database.default') === 'mysql') {
            $seqQuery->lock(true);
        }
        $lastNo = $seqQuery->value('contract_no');

        $seq = 1;
        if ($lastNo && preg_match('/(\d{4})$/', $lastNo, $m)) {
            $seq = (int)$m[1] + 1;
        }

        $map = [
            '{PREFIX}'   => $prefix,
            '{CATEGORY}' => $catCode,
            '{DATE}'     => $datePart,
            '{SEQ}'      => str_pad($seq, 4, '0', STR_PAD_LEFT),
        ];
        $no = str_replace(array_keys($map), array_values($map), $fmt);

        // 去重：递增序号直到不冲突（带上限保护，避免极端情况下死循环）
        $guard = 0;
        while (\think\facade\Db::name('contract')->where('contract_no', $no)->find() && $guard < 9999) {
            $seq++;
            $map['{SEQ}'] = str_pad($seq, 4, '0', STR_PAD_LEFT);
            $no = str_replace(array_keys($map), array_values($map), $fmt);
            $guard++;
        }
        // 兜底：若仍冲突（极高并发/序号用尽），追加时间戳后缀保证唯一
        if (\think\facade\Db::name('contract')->where('contract_no', $no)->find()) {
            $no .= '-' . date('His');
        }
        return $no;
    });
}

/**
 * 生成附件预览签名令牌（用于 /preview 的免会话鉴权）
 * 移动端文档预览被 WebView 甩到外部浏览器/查看器时，顶层导航无法携带 JWT 头、
 * 外部上下文也无会话 Cookie，故以「路径绑定 + 短期有效」的令牌放行，避免外部浏览器要求重新登录（#3 修复）。
 *
 * v2.44.3 窗口化：exp 对齐到「下下个」TTL 窗口边界，使同一文件在窗口内签发的令牌完全一致 →
 * 预览 URL 稳定 → 浏览器缓存（/preview 已带 Cache-Control: max-age=3600）可命中，
 * 修复「每次进入详情页 token 变化 → URL 变化 → 图片/文档每次全量重新下载」的移动端加载慢问题。
 * 取「下下个边界」而非「下个边界」：窗口内任意时刻签发的令牌最短有效期都 ≥ ttl
 * （若取下个边界，窗口尾部签发的令牌会立即过期导致预览 401），最长有效期 = 2 倍 ttl，
 * 与 /preview 的 max-age=3600 缓存期限对齐；令牌仍为路径绑定 + HMAC 签名 + 定时失效，安全影响可忽略。
 * @param string $path 文件相对路径（与库中 file_url 一致）
 * @param int    $ttl  有效期（秒），默认 1800
 * @return string base64(exp.signature)
 */
function preview_token(string $path, int $ttl = 1800): string
{
    $window = max(60, (int)$ttl);
    $exp    = intdiv(time(), $window) * $window + $window * 2; // 下下个窗口边界
    $sig    = hash_hmac('sha256', $path . '|' . $exp, \app\common\logic\AuthLogic::jwtSecret());
    return base64_encode($exp . '.' . $sig);
}

/**
 * 校验附件预览签名令牌
 * @param string $path  文件相对路径（必须与签发时一致）
 * @param string $token base64(exp.signature)
 * @return bool
 */
function validate_preview_token(string $path, string $token): bool
{
    $dec = base64_decode($token, true);
    if ($dec === false || strpos($dec, '.') === false) {
        return false;
    }
    list($exp, $sig) = explode('.', $dec, 2);
    if (!is_numeric($exp) || (int)$exp < time()) {
        return false; // 过期
    }
    $expect = hash_hmac('sha256', $path . '|' . (int)$exp, \app\common\logic\AuthLogic::jwtSecret());
    return hash_equals($expect, (string)$sig);
}

/** 合同分类列表 — 优先从字典 dict_contract_category 读取，兼容旧 contract_categories
 * REV-40：跨请求缓存（300s），与 dict() 一致——同一进程内 static 作 L1，避免同请求重复读缓存存储。
 */
function contract_categories(): array
{
    static $local = null;
    if ($local !== null) {
        return $local;
    }
    return $local = Cache::remember('contract_categories', function () {
        // 优先从字典读取
        $config = \think\facade\Db::name('system_config')
            ->where('config_key', 'dict_contract_category')
            ->value('config_value');
        if ($config) {
            $arr = json_decode($config, true);
            if (is_array($arr) && !empty($arr)) {
                // v2.40.7：合同分类下拉过滤停用项（历史 label 解析走 dict() 全量，不受影响）
                return dict_enabled('contract_category');
            }
        }
        // 兼容旧配置
        $config = \think\facade\Db::name('system_config')
            ->where('config_key', 'contract_categories')
            ->value('config_value');
        if ($config) {
            $arr = json_decode($config, true);
            if (is_array($arr)) return $arr;
        }
        return [
            'SALES'    => '销售合同',
            'PURCHASE' => '采购合同',
            'LABOR'    => '劳动合同',
            'LEASE'    => '租赁合同',
            'NDA'      => '保密协议',
            'SERVICE'  => '服务合同',
            'OTHER'    => '其他',
        ];
    }, 300);
}

/** 合同状态 -> 中文标签（CR-57/REV-36：单一口径，复用 ContractLogic::STATUS_LABELS，避免状态标签与状态机漂移） */
function contract_status_map(): array
{
    return \app\common\logic\ContractLogic::STATUS_LABELS;
}

/**
 * 合同状态 -> 移动端标签样式类（m-tag-*，P2-7/M20：键集合统一引用 ContractLogic::STATUS_LABELS，
 * 新增状态自动覆盖；样式类与状态文案解耦）
 */
function contract_status_badge(): array
{
    // 各状态的移动端样式类（仅外观）
    $classByStatus = [
        'DRAFT'            => 'm-tag-warn',   // v2.44.4：草稿徽标改琥珀（方案 A：与浅琥珀卡片底区分一致）
        'PENDING_APPROVAL' => 'm-tag-warn',
        'REJECTED'         => 'm-tag-danger',
        'EXECUTING'        => 'm-tag-ok',
        'COMPLETED'        => 'm-tag-ok',
        'TERMINATED'       => 'm-tag-muted',
        'EXPIRED'          => 'm-tag-danger',
        'ARCHIVED'         => 'm-tag-muted',
    ];
    // 键集合以 STATUS_LABELS 为唯一真源，确保任何新增状态都会出现在徽标映射中
    $badge = [];
    foreach (array_keys(\app\common\logic\ContractLogic::STATUS_LABELS) as $status) {
        $badge[$status] = $classByStatus[$status] ?? 'm-tag-muted';
    }
    return $badge;
}

/**
 * 合同状态标签（P2-7/M19：中文文案统一引用 ContractLogic::STATUS_LABELS 作为唯一真源，
 * 避免桌面「已到期」/移动「已过期」等文案漂移；样式类仅在此处维护）
 */
function contract_status_label(string $status): string
{
    // 状态中文从 STATUS_LABELS 单一来源读取（EXPIRED 已统一为「已到期」）
    $text = \app\common\logic\ContractLogic::STATUS_LABELS[$status] ?? $status;
    // 各状态对应的徽标样式类（与状态文案解耦，仅负责外观）
    $classMap = [
        'DRAFT'            => 'pc-tag-warn',   // v2.44.4：草稿徽标统一琥珀（方案 A，与列表/移动端一致）
        'PENDING_APPROVAL' => 'pc-tag-warn',
        'REJECTED'         => 'pc-tag-danger',
        'EXECUTING'        => 'pc-tag-ok',
        'COMPLETED'        => 'pc-tag-ok',
        'TERMINATED'       => 'pc-tag-muted',
        'EXPIRED'          => 'pc-tag-danger',
        'ARCHIVED'         => 'pc-tag-muted',
    ];
    $cls = $classMap[$status] ?? 'pc-tag-muted';
    return '<span class="pc-tag ' . $cls . '">' . $text . '</span>';
}

/**
 * 系统字典 — 从 system_config 读取可配置的键值映射
 * 用法: dict('supplier_type') 返回 ['MEDIA'=>'媒体渠道', ...]
 * 用法: dict('supplier_type', 'MEDIA') 返回 '媒体渠道'
 * REV-40：跨请求缓存（300s）——进程内 static 作 L1，避免同请求内重复读缓存存储；
 * 字典为低频变更配置，300s TTL 可接受（后台修改字典后最多 5 分钟生效）。
 */
function dict(string $name, ?string $key = null): array|string
{
    static $local = [];
    if (!isset($local[$name])) {
        $local[$name] = Cache::remember('dict_' . $name, function () use ($name) {
            $json = \think\facade\Db::name('system_config')
                ->where('config_key', "dict_{$name}")
                ->value('config_value');
            return $json ? json_decode($json, true) ?: [] : [];
        }, 300);
    }
    if ($key !== null) {
        return $local[$name][$key] ?? $key;
    }
    return $local[$name];
}

/**
 * 字典停用集合（v2.40.7）：读 system_config dict_disabled_{name} 配置行，JSON 数组存停用 KEY。
 * 停用语义：仅从「新建/编辑选项选择」隐藏（dict_enabled/dict_options），
 * 浏览/筛选/统计与 label 解析（dict() 全量）不受影响，历史数据照常显示。
 */
function dict_disabled(string $name): array
{
    static $local = [];
    if (!isset($local[$name])) {
        $local[$name] = Cache::remember('dict_disabled_' . $name, function () use ($name) {
            $json = \think\facade\Db::name('system_config')
                ->where('config_key', 'dict_disabled_' . $name)
                ->value('config_value');
            return $json ? (json_decode($json, true) ?: []) : [];
        }, 300);
    }
    return $local[$name];
}

/** 启用项集合（全量减去停用项）：新建/编辑选项下拉、筛选选项用 */
function dict_enabled(string $name): array
{
    $disabled = dict_disabled($name);
    if (!$disabled) {
        return dict($name);
    }
    return array_diff_key(dict($name), array_flip($disabled));
}

/**
 * 选项下拉（仅启用项）；编辑场景当前值若被停用则补回该项，保证下拉不回退空/首项。
 * @param string $current 当前选中编码（编辑表单传值，新建传 ''）
 */
function dict_options(string $name, string $current = ''): array
{
    $opts = dict_enabled($name);
    if ($current !== '' && !isset($opts[$current])) {
        $all = dict($name);
        if (isset($all[$current])) {
            $opts = [$current => $all[$current]] + $opts;
        }
    }
    return $opts;
}

/** 审批状态标签 */
function approval_status_label(string $status): string
{
    $map = [
        'PENDING'  => '<span class="pc-tag pc-tag-warn">审批中</span>',
        'APPROVED' => '<span class="pc-tag pc-tag-ok">已通过</span>',
        'REJECTED' => '<span class="pc-tag pc-tag-danger">已驳回</span>',
        'RECALLED' => '<span class="pc-tag pc-tag-muted">已撤回</span>',
    ];
    return $map[$status] ?? $status;
}

/**
 * 客户跟进记录类型 -> 中文标签（v2.38.14：前端禁止直出英文枚举，统一走本函数）
 * 覆盖 CustomerLogic::addActivity 实际写入的全部 type：CLAIM/TRANSFER/RELEASE/NOTE，
 * 以及 v2.40.0 手动录入的 phone/visit/meeting/wechat；
 * 未命中回退中文「跟进」——**绝不回退英文原始码**（与 dict() 回退原值的行为刻意区分）。
 */
function activity_type_label(?string $type): string
{
    $map = [
        'CLAIM'    => '认领',
        'TRANSFER' => '转移',
        'RELEASE'  => '释放',
        'NOTE'     => '跟进',
        'phone'    => '电话',
        'visit'    => '拜访',
        'meeting'  => '会议',
        'wechat'   => '微信',
    ];
    return $map[$type ?? ''] ?? '跟进';
}

/**
 * 电话号码交互渲染（tel: 拨打 + 复制图标）
 * - 空号返回 '-'，不渲染链接
 * - 移动端：tel: 链接由 WebView 拦截唤起系统/钉钉拨号
 * - PC 端：tel: 链接 + 旁边复制小图标（点击复制到剪贴板，toast 反馈）
 * @param string|null $phone  电话号码
 * @param bool        $copy   是否显示复制图标（PC 端默认 true；移动端列表等紧凑场景可传 false）
 * @return string  HTML 字符串
 */
function phone_link(?string $phone, bool $copy = true): string
{
    $p = trim((string)$phone);
    if ($p === '') return '-';
    $esc = htmlspecialchars($p, ENT_QUOTES);
    // 仅保留数字/+/-/空格，剔除可能注入的字符后用于 tel: 协议
    $tel = preg_replace('/[^\d+\-\s]/', '', $p);
    $telEsc = htmlspecialchars($tel, ENT_QUOTES);
    $html = '<a href="tel:' . $telEsc . '" class="phone-link">' . $esc . '</a>';
    if ($copy) {
        $html .= ' <i class="bi bi-clipboard phone-copy" data-phone="' . $esc . '" title="复制号码" role="button" tabindex="0"></i>';
    }
    return $html;
}

/**
 * 附件文件是否真实存在（v2.38.14）
 * 渲染附件列表时校验 public 下文件是否存在——缺失附件（测试残留/已删文件）显示「文件缺失」不可点击，
 * 避免用户点击直出框架「控制器不存在」错误页（如 /uploads/reg/t.pdf 无文件时被路由解析到 UploadsController）。
 * @param string $url 附件 URL（以 /uploads/ 开头的站内相对路径）
 */
function attachment_exists(string $url): bool
{
    if ($url === '' || $url[0] !== '/') {
        return false;
    }
    // 剥掉可能的查询串
    $path = strtok($url, '?');
    return is_file(app()->getRootPath() . 'public' . $path);
}

/**
 * 合同附件上传：解析允许保存的扩展名（纯函数，供上传控制器与单元测试复用）。
 *
 * 业务要求：合同附件与资料库统一仅允许 PDF / Word(doc,docx) / Excel(xls,xlsx) / JPG / PNG 七类格式
 * （v2.43.6 起两口径一致；本注释随白名单同步，勿再写回旧四类）。
 * - 精确白名单：finfo 检测的真实 MIME 命中 $mimeToExt 时直接采用其扩展名；
 * - 兼容回退：部分 OLE 系 .doc（或低版本 Word 文档）在 finfo/libmagic 下会被标为
 *   x-ole-storage / vnd.ms-office / octet-stream，此时仅当客户端原始扩展名恰为 doc
 *   才按 doc 放行——保证真实 OLE 文档可用，同时拦截「改扩展名伪装」上传。
 *
 * @param string $realMime finfo(FILEINFO_MIME_TYPE) 检测的真实 MIME
 * @param string $origExt  客户端原始扩展名（不含点，大小写不敏感）
 * @return string|null 允许的保存扩展名；null 表示类型不被支持、应拒绝上传
 */
function resolve_attachment_ext(string $realMime, string $origExt): ?string
{
    $origExt = strtolower(trim($origExt));
    // 允许的「真实类型 → 扩展名」映射（正向白名单，拒绝一切未列出的类型，含脚本/HTML）
    $mimeToExt = [
        'application/pdf'            => 'pdf',
        'application/msword'         => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel'   => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'image/jpeg'                 => 'jpg',
        'image/png'                  => 'png',
    ];
    if (isset($mimeToExt[$realMime])) {
        return $mimeToExt[$realMime];
    }
    // 回退映射（兼容）：OLE 系 .doc/.xls（含低版本 Word/Excel 文档）在 finfo/libmagic 下常被标为
    // x-ole-storage / vnd.ms-office；仅当客户端扩展名也是 doc/xls 时视作对应格式，
    // 确保不被伪装的 .doc.exe/.xls.exe 绕过。
    // 注意：octet-stream（未知二进制）不在此列，一律拒绝——不能仅凭扩展名放行未知内容。
    $fallbackMimeToExt = [
        'application/x-ole-storage' => ['doc' => 'doc', 'xls' => 'xls'],
        'application/vnd.ms-office'  => ['doc' => 'doc', 'xls' => 'xls'],
    ];
    if (isset($fallbackMimeToExt[$realMime])) {
        return $fallbackMimeToExt[$realMime][$origExt] ?? null;
    }
    return null;
}

/**
 * 资料库附件「真实文件类型 → 扩展名」正向白名单判定（2026-08-05 自 ResourceController::save 提取，供单元测试覆盖）。
 * 资料库允许 PDF / Word / Excel / JPG / PNG 七类（v2.43.6 起与合同附件口径一致，移除 GIF/WebP）。
 *
 * v2.44.0 收敛：白名单与 OLE 回退统一委托 resolve_attachment_ext()（单一事实来源），
 * 消除两处重复维护的漂移风险（此前两函数 7 类清单 + OLE 回退逐行重复）。
 * 行为与历史实现完全一致：finfo 真实 MIME 精确白名单 → x-ole-storage/vnd.ms-office 按原始扩展名回退 → octet-stream 拒绝。
 *
 * @param string $realMime finfo 读出的真实 MIME
 * @param string $origExt  客户端原始扩展名（不含点，大小写不敏感；OLE 回退判定用，缺省按空处理）
 * @return string|null 匹配到的扩展名；不匹配返回 null（上传应被拒绝）
 */
function resolve_library_attachment_ext(string $realMime, string $origExt = ''): ?string
{
    return resolve_attachment_ext($realMime, $origExt);
}

/** 审批动作标签 */
function approval_action_label(string $action): string
{
    $map = [
        'PENDING'     => '<span class="pc-tag pc-tag-warn">待审批</span>',
        'APPROVED'    => '<span class="pc-tag pc-tag-ok">同意</span>',
        'REJECTED'    => '<span class="pc-tag pc-tag-danger">驳回</span>',
        'TRANSFERRED' => '<span class="pc-tag pc-tag-info">已转交</span>',
        'CC'          => '<span class="pc-tag pc-tag-info">抄送</span>',
        'AUTO_APPROVED' => '<span class="pc-tag pc-tag-ok">自动通过</span>', // 超时自动通过（审批催办兜底）
    ];
    return $map[$action] ?? $action;
}

/**
 * 审计操作类型 -> 中文标签映射（单一事实来源）
 * 覆盖 AuditService::log 实际写入的全部 action 码；审计中心筛选下拉与前端
 * window._auditActions、相对方360「最近动态」均复用此映射，避免页面出现
 * approve_recall / status_change / auto_expire 等英文原始码。
 */
function audit_action_labels(): array
{
    return [
        'create'             => '创建',
        'update'             => '更新',
        'delete'             => '删除',
        'status_change'      => '状态变更',
        'export'             => '导出',
        'batch_archive'      => '批量归档',
        'batch_delete'       => '批量删除',
        'payment_revoke'     => '回款撤销',
        'approve_submit'     => '提交审批',
        'approve_approved'   => '审批通过',
        'approve_rejected'   => '审批驳回',
        'approve_transferred'=> '审批转交',
        'approve_recall'     => '撤回审批',
        'archive'            => '归档',
        'unarchive'          => '取消归档',
        'auto_expire'        => '系统自动到期',
        'invoice_void'       => '发票作废',
        'invoice_red'        => '发票红冲',
        'login'              => '登录',
        'save_user'          => '保存用户',
        'disable_user'       => '禁用用户',
        'handover'           => '离职交接',
        'recycle_restore'    => '回收站恢复',
        'recycle_purge'      => '回收站删除',
        'terminate'          => '终止',
        'restore'            => '恢复',
        'accept'             => '接受',
        'customer_share'     => '客户共享',
        'customer_join_group'=> '客户分组',
        'invoice_issue'      => '开票',
    ];
}

/** 审计操作类型 -> 中文标签（未知码回退原始值，避免页面报错） */
function audit_action_label(string $action): string
{
    return audit_action_labels()[$action] ?? $action;
}

/**
 * 审计目标类型 -> 中文标签映射（单一事实来源）
 * 覆盖 AuditService::log 实际写入的全部 target_type 码（含 project / report_* 等曾被遗漏者）。
 */
function audit_type_labels(): array
{
    return [
        'contract'         => '合同',
        'customer'         => '客户',
        'supplier'         => '供应商',
        'user'             => '用户',
        'approval'         => '审批',
        'invoice'          => '发票',
        'payment'          => '回款',
        'archive'          => '归档',
        'project'          => '项目',
        'report_monthly'   => '月度报表',
        'report_dashboard' => '数据看板',
        'party'            => '相对方',
    ];
}

/** 审计目标类型 -> 中文标签 */
function audit_type_label(string $type): string
{
    return audit_type_labels()[$type] ?? $type;
}

/**
 * 兼容旧版本调用：框架合同层级已下线，所有未删除合同均参与统计。
 */
function exclude_framework_contracts_ids(): array
{
    return [];
}

/**
 * 兼容旧版本调用：不追加任何过滤。
 * @param mixed  $query ThinkPHP Query 对象（引用）
 * @param string $alias 表别名（如 'c'），带别名查询用；空串用 'id'
 */
function exclude_framework_contracts($query, string $alias = ''): void
{
    $excludedIds = exclude_framework_contracts_ids();
    if ($excludedIds) {
        $field = $alias !== '' ? $alias . '.id' : 'id';
        $query->whereNotIn($field, $excludedIds);
    }
}

/** 回款状态标签 */
function payment_status_label(string $status): string
{
    $map = [
        'PENDING' => '<span class="pc-tag pc-tag-warn">待收</span>',
        'PAID'    => '<span class="pc-tag pc-tag-ok">已收</span>',
        'OVERDUE' => '<span class="pc-tag pc-tag-danger">逾期</span>',
    ];
    return $map[$status] ?? $status;
}

/** 合同分类中文名 */
function contract_category_name(string $code): string
{
    $categories = contract_categories();
    return $categories[$code] ?? $code;
}

/** 金额格式化（v2.38.12：全站非发票金额统一整数显示；发票金额保留2位小数） */
function format_money(float $amount): string
{
    return number_format($amount, 0, '.', ',');
}

/** 移动端全局底部导航栏（设计约束：不提供"电脑版"入口；必须含"客户"管理入口）
 *  P1：Tab3 按角色动态替换——部门经理→审批，财务→财务，其他→客户（默认）
 *  @param string $active home|contract|customer|approval|finance|more
 */
function mobile_tabbar(string $active = '', bool $showAdd = false, string $addUrl = ''): string
{
    // 底部导航（固定 4 Tab，产品决策）：工作台/合同/客户/更多
    // 审批/财务/资料库/报表等高频动作从工作台快捷宫格进入（审批页 $tab='' 不高亮，注释已声明从底部移除）；
    // 固定后消除「第 3 Tab 按角色替换（经理→审批、财务→财务）」造成的两个问题：
    //   1) 位置漂移——同一手机换账号后 Tab 顺序变化，肌肉记忆失效
    //   2) 高亮失效——页面 $tab 仅传 home/contract/customer/more，角色 Tab key 永远不匹配，永不高亮
    $tabs = [
        ['home',     '/m',           'bi-grid-1x2',          '工作台'],
        ['contract', '/m/contracts', 'bi-file-earmark-text', '合同'],
        ['customer', '/m/customers', 'bi-people',            '客户'],
        ['more',     '/m/more',      'bi-grid-3x3',          '更多'],
    ];
    $html = '<div class="m-tabbar">';
    foreach ($tabs as $i => $t) {
        // 新建入口在工作台与「更多」均保持稳定位置，避免切换 Tab 后操作入口消失。
        if ($i === 2 && $showAdd) {
            $html .= '<button type="button" class="m-tabbar-add" aria-label="新增" onclick="document.body.classList.toggle(\'fab-open\')"><i class="bi bi-plus-lg"></i></button>';
        }
        $cls = $t[0] === $active ? ' active' : '';
        $html .= '<a href="' . $t[1] . '" class="' . trim($cls) . '"><i class="bi ' . $t[2] . '"></i>' . $t[3] . '</a>';
    }
    return $html . '</div>';
}

/** 判断当前请求是否来自移动端（手机/平板），用于自动分流到移动版界面
 *  平板（iPad 等）与钉钉/微信 WebView 均归入移动端，以使用移动版布局。
 *  @return bool
 */
function is_mobile_request(): bool
{
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return false;
    }
    $patterns = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod',
        'Windows Phone', 'webOS', 'BlackBerry', 'Opera Mini',
        'MicroMessenger',   // 微信内置浏览器（移动端）
        // 注意：不把 'DingTalk' 列入移动判定。钉钉 PC 客户端（Windows/Mac 工作台）UA 含
        // "DingTalk" 但属桌面环境，若命中会被误判为移动端，导致在钉钉打开应用进入移动版页面。
        // 手机钉钉 UA 含 Android/iPhone，仍会被上述规则命中，走移动版；钉钉 PC 端走 PC 版。
    ];
    foreach ($patterns as $p) {
        if (stripos($ua, $p) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * 安全重定向目标校验（CR-43）：仅允许站内相对路径，
 * 即「以 / 开头」且「非协议相对 //」且「不含 :// 协议前缀」，
 * 杜绝开放重定向（open redirect）漏洞；非法目标回退 $default。
 * @param string $url     待校验的重定向地址（通常来自 ?redirect= 查询参数）
 * @param string $default 非法时回退的站内默认地址
 */
function safe_redirect_url(string $url, string $default = '/'): string
{
    $url = (string)$url;
    // P2：反斜杠变体（/\evil.com）在旧版浏览器/部分代理会被归一为 // 协议相对，一并拒绝
    if ($url === '' || $url[0] !== '/' || strncmp($url, '//', 2) === 0
        || strpos($url, '://') !== false || strpos($url, '\\') !== false) {
        return $default;
    }
    return $url;
}

/**
 * 跨数据库兼容的「按月」格式化表达式（CR-56：将方言判断从控制器抽出到统一 helper）
 * 返回可直接用于 SELECT/FIELD 的 SQL 片段，按当前配置的数据库类型选择：
 *   MySQL  -> DATE_FORMAT(col, '%Y-%m')
 *   SQLite -> strftime('%Y-%m', col)
 * 调用方无需再感知 DB 方言，便于后续接入更多数据库类型。
 * @param string $column 需格式化的日期/时间列（应为硬编码字面量，非用户输入）
 */
function db_month_expr(string $column): string
{
    if (config('database.default') === 'mysql') {
        return "DATE_FORMAT({$column}, '%Y-%m')";
    }
    return "strftime('%Y-%m', {$column})";
}

/**
 * 跨数据库兼容的「按季」格式化表达式（与 db_month_expr 同款方言处理）
 *   MySQL  -> CONCAT(YEAR(col), 'Q', QUARTER(col))
 *   SQLite -> 'Q' || ((CAST(strftime('%m', col) AS INT) - 1) / 3 + 1) || '-' || strftime('%Y', col)（统一为 YYYY-Qn，便于排序）
 * @param string $column 日期/时间列（硬编码字面量，非用户输入）
 */
function db_quarter_expr(string $column): string
{
    if (config('database.default') === 'mysql') {
        return "CONCAT(YEAR({$column}), 'Q', QUARTER({$column}))";
    }
    return "CAST(strftime('%Y', {$column}) AS TEXT) || 'Q' || CAST(((CAST(strftime('%m', {$column}) AS INT) - 1) / 3 + 1) AS TEXT)";
}

/**
 * 跨数据库兼容的「按年」格式化表达式（与 db_month_expr 同款方言处理）
 *   MySQL  -> YEAR(col)
 *   SQLite -> strftime('%Y', col)
 * @param string $column 日期/时间列（硬编码字面量，非用户输入）
 */
function db_year_expr(string $column): string
{
    if (config('database.default') === 'mysql') {
        return "YEAR({$column})";
    }
    return "strftime('%Y', {$column})";
}

/**
 * 导出单元格公式注入中和（P2）：Excel/CSV 中值以 = + - @ 开头时会被解析为公式执行，
 * 导出前对这类值前置 ' 使其按文本显示（仅命中恶意前缀，正常数据不受影响）。
 * @param mixed $value 原始单元格值
 * @return string 中和后的字符串
 */
function export_safe_cell($value): string
{
    $s = (string)$value;
    if ($s !== '' && preg_match('/^[=+\-@]/', $s)) {
        return "'" . $s;
    }
    return $s;
}

/**
 * 删除上传目录内的物理文件（P1/P2：realpath 边界校验防目录穿越）。
 * 仅允许 public/uploads/ 下的文件被 unlink；file_url 若被篡改为 ../../config/.env 等路径则安全拒绝。
 * @param string $rel 站内相对路径（形如 /uploads/contracts/202608/xxx.pdf）
 */
function remove_upload_file(string $rel): void
{
    if ($rel === '') { return; }
    $base       = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR;
    $allowedDir = realpath($base . 'uploads');
    if ($allowedDir === false) { return; }
    $real = realpath($base . ltrim($rel, '/'));
    if ($real !== false && is_file($real)
        && strpos($real, $allowedDir . DIRECTORY_SEPARATOR) === 0) {
        @unlink($real);
    }
}

/**
 * 解析周期筛选参数为日期闭区间 [start, end]（含时分），用于移动端财务报表月/季/年筛选。
 * 仅接受白名单周期：month（本月）/ quarter（本季）/ year（本年）；其余（含 'all' / 空）返回 null 表示「不筛选（累计）」。
 * 区间按当前时区（Asia/Shanghai）计算，与系统时间配置一致。
 * @param string $period month|quarter|year|all
 * @return array|null [start, end] 或 null
 */
function period_range(string $period): ?array
{
    $now = time();
    switch ($period) {
        case 'month':
            $start = date('Y-m-01', $now);
            $end   = date('Y-m-t 23:59:59', $now);
            break;
        case 'quarter':
            $q     = (int)date('n', $now);
            $m     = (int)ceil($q / 3) * 3 - 2; // 季度首月
            $y     = (int)date('Y', $now);
            $start = date('Y-m-01', strtotime($y . '-' . $m . '-01'));
            $end   = date('Y-m-t 23:59:59', strtotime($y . '-' . $m . '-01 +2 months'));
            break;
        case 'year':
            $start = date('Y-01-01', $now);
            $end   = date('Y-12-31 23:59:59', $now);
            break;
        default:
            return null;
    }
    // start 用纯日期（Y-m-d）而非带时分秒：合同 effective_date/回款 planned_date 等存储为纯日期字符串，
    // 若 start 带 '00:00:00'，字符串比较 '2026-08-01' < '2026-08-01 00:00:00' 会把「周期首日生效」的记录全部漏计
    // （曾致「本月合同总额恒为 ¥0」）；end 保留 23:59:59，避免 created_at 等带时间字段的月末记录漏计。
    return [$start, $end];
}

/**
 * 校验大陆手机号（1 开头，第二位 3-9，共 11 位）
 * 空串视为「未填写」返回 true（由调用方决定是否必填）
 */
function validate_mobile(string $mobile): bool
{
    if ($mobile === '') {
        return true;
    }
    return (bool) preg_match('/^1[3-9]\d{9}$/', $mobile);
}

/**
 * 校验邮箱格式（空串视为「未填写」返回 true）
 */
function validate_email(string $email): bool
{
    if ($email === '') {
        return true;
    }
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * 校验统一社会信用代码（GB 32100-2015，18 位）
 * 字符集 31 位（0-9 + A-Z 去除 I/O/S/V/Z），末位为校验码
 * 空串视为「未填写」返回 true（由调用方决定是否必填）
 */
function validate_credit_code(string $code): bool
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return true;
    }
    if (strlen($code) !== 18) {
        return false;
    }
    $chars = '0123456789ABCDEFGHJKLMNPQRTUWXY';
    $pos = [];
    for ($i = 0; $i < 31; $i++) {
        $pos[$chars[$i]] = $i;
    }
    $weights = [1, 3, 9, 27, 19, 26, 16, 17, 20, 29, 25, 13, 8, 24, 10, 30, 28];
    $sum = 0;
    for ($i = 0; $i < 17; $i++) {
        if (!isset($pos[$code[$i]])) {
            return false;
        }
        $sum += $pos[$code[$i]] * $weights[$i];
    }
    $c = 31 - ($sum % 31);
    if ($c === 31) {
        $c = 0;
    }
    return $chars[$c] === $code[17];
}

/**
 * 归一化合同关键词：将用户输入的各种分隔符统一为英文逗号，并清洗空项与重复项。
 * 解决「用户手动输入逗号易出错（中文逗号、空格、顿号、分号混用）」导致的
 * 数据存储不整洁、检索口径不一致问题。保存前调用，编辑时复用同样逻辑。
 * 支持的分隔符：中文逗号(，)、顿号(、)、分号(；)、空格/制表符/换行等空白字符。
 * @param string $raw 原始关键词串（如 "续签， 年度框架、重要客户 ；框架"）
 * @return string 规范串（如 "续签,年度框架,重要客户,框架"）
 */
function normalize_keywords(string $raw): string
{
    // ① 各类分隔符统一替换为英文逗号（空白用 \s 覆盖空格/制表符/换行；u 修饰符处理 UTF-8 中文标点）
    $normalized = preg_replace('/[，、；\s]+/u', ',', trim($raw));
    // ② 按逗号拆分，逐项 trim，过滤空串
    $parts = array_filter(array_map('trim', explode(',', $normalized)), function ($v) {
        return $v !== '';
    });
    // ③ 去重（保留首次出现顺序）
    $seen = [];
    $unique = [];
    foreach ($parts as $p) {
        if (!isset($seen[$p])) {
            $seen[$p] = true;
            $unique[] = $p;
        }
    }
    return implode(',', $unique);
}

/**
 * 选项归一化存储：统一为 [{"value","label"}] JSON；非法输入存 []
 * （P2-7：自 AdminController / FormBuilderController 抽出的公共函数，三处实现逐行一致）
 * 兼容两种输入：
 *   - 数组：['a'=>'A'] 或 [['key'=>'a','value'=>'A']] / [['value'=>'a','label'=>'A']] 直接归一化
 *   - JSON 字符串：合法 JSON 数组则解析后归一化；空串/'[]'/'null'/非法 JSON 返回 '[]'
 * @param mixed $raw 数组或 JSON 字符串
 * @return string [{"value","label"},...] 的 JSON 字符串
 */
function normalize_options($raw): string
{
    if (is_array($raw)) {
        $arr = $raw;
    } else {
        $s = trim((string)$raw);
        if ($s === '' || $s === '[]' || $s === 'null') return '[]';
        $decoded = json_decode($s, true);
        $arr = is_array($decoded) ? $decoded : [];
    }
    $out = [];
    foreach ($arr as $k => $v) {
        if (is_array($v)) {
            $val = trim((string)($v['value'] ?? ''));
            if ($val !== '') $out[] = ['value' => $val, 'label' => trim((string)($v['label'] ?? $val))];
        } elseif (is_string($k) && !is_numeric($k)) {
            $out[] = ['value' => trim($k), 'label' => trim((string)$v)];
        } else {
            $out[] = ['value' => trim((string)$v), 'label' => trim((string)$v)];
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

/**
 * 中文拼音首字母（大写）
 * 基于 GB2312 编码区间匹配（无需 intl 扩展/外部依赖）
 * 用于字典编码自动生成等非关键场景，多音字可能不精确但足够使用
 * @param string $str 中文串（如"政府单位"）
 * @return string 首字母拼接（如"ZFDW"），非中文字符原样大写
 */
function pinyin_initials(string $str): string
{
    $str = trim($str);
    if ($str === '') return '';
    $gb = @mb_convert_encoding($str, 'GB2312', 'UTF-8');
    if ($gb === '' || $gb === $str) {
        // 转码失败（纯 ASCII 或含 GB2312 不支持的字符），直接取字母
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $str));
    }
    // GB2312 拼音声母起始编码区间（公开标准数据，无 I/U/V 开头拼音）
    $map = [
        0xB0A1 => 'A', 0xB0C5 => 'B', 0xB2C1 => 'C', 0xB4EE => 'D',
        0xB6EA => 'E', 0xB7A2 => 'F', 0xB8C1 => 'G', 0xB9FE => 'H',
        0xBBF7 => 'J', 0xBFA6 => 'K', 0xC0AC => 'L', 0xC2E8 => 'M',
        0xC4C3 => 'N', 0xC5B6 => 'O', 0xC5BE => 'P', 0xC6DA => 'Q',
        0xC8BB => 'R', 0xC8F6 => 'S', 0xCBFA => 'T', 0xCDDA => 'W',
        0xCEF4 => 'X', 0xD1B9 => 'Y', 0xD4D1 => 'Z',
    ];
    $result = '';
    $len = strlen($gb);
    for ($i = 0; $i < $len; $i++) {
        $b = ord($gb[$i]);
        if ($b > 0x80 && $i + 1 < $len) {
            $code = ($b << 8) | ord($gb[$i + 1]);
            $i++;
            $letter = 'Z'; // 兜底
            foreach ($map as $start => $ch) {
                if ($code >= $start) $letter = $ch;
            }
            $result .= $letter;
        } elseif (($b >= 65 && $b <= 90) || ($b >= 97 && $b <= 122)) {
            $result .= strtoupper(chr($b));
        } elseif ($b >= 48 && $b <= 57) {
            $result .= chr($b);
        }
    }
    return $result !== '' ? $result : 'ITEM';
}
