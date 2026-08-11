<?php
// +----------------------------------------------------------------------
// | 附件预览代理 — 强制 Content-Disposition: inline 避免浏览器弹窗下载
// +----------------------------------------------------------------------

namespace app\controller;

use app\BaseController;
use think\facade\Session;
use think\facade\Log;
use app\common\logic\AuthLogic;
use app\common\logic\ContractLogic;
use app\common\logic\ResourceLogic;

class PreviewController extends BaseController
{
    /**
     * 内嵌预览附件
     * GET /preview?p=/uploads/contracts/202607/xxx.pdf
     * 读取 public/ 目录下的文件并以 inline 方式输出，浏览器内嵌显示而非下载。
     *
     * 安全说明（修复 CR-01 数据越权预览）：
     * 1) 认证由全局 Auth 中间件统一把关（preview 已从免认证白名单移除），
     *    匿名请求会被中间件拦截（AJAX 返回 401、普通页面跳转登录页）；
     * 2) 本方法在路径穿越防护之后，额外做「数据权限」校验，
     *    仅允许访问当前用户有权查看的合同附件或共享资料库文件，
     *    杜绝匿名/越权下载他人合同附件。
     */
    public function index()
    {
        // 读取文件相对路径并做安全校验（防止目录穿越）
        $path = $this->getParam('p', '');
        if (empty($path)) {
            throw new \think\exception\HttpException(400, '缺少文件路径');
        }

        // 仅允许 public/uploads/ 下的文件，防目录穿越攻击
        $base        = app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR;
        $allowedDir  = realpath($base . 'uploads');
        // m16：目录边界比较（非简单前缀匹配）——解析真实路径后必须严格落在允许的附件根目录内，
        // 且其后必须紧跟目录分隔符，避免 /public/uploads_evil 之类被前缀误放行。
        if ($allowedDir === false) {
            throw new \think\exception\HttpException(404, '文件不存在');
        }
        $real = realpath($base . ltrim($path, '/'));
        if ($real === false
            || !is_file($real)
            || strpos($real, $allowedDir . DIRECTORY_SEPARATOR) !== 0) {
            throw new \think\exception\HttpException(404, '文件不存在');
        }

        // 预览签名令牌（#3 修复）：移动端文档预览被 WebView 甩到外部浏览器/查看器时，
        // 顶层导航无法携带 JWT 头、外部上下文也无会话 Cookie；以路径绑定的短期令牌免会话鉴权，
        // 避免外部浏览器要求重新登录合同管理。令牌由 Auth 中间件先行校验，此处再次校验（纵深防御）。
        $previewToken = $this->getParam('t', '');
        $tokenOk      = $previewToken !== '' && $this->validatePreviewToken($path, $previewToken);

        // 确保当前登录用户已装载到 Session（供数据权限判定使用，详见 ensureUserLoaded）
        $this->ensureUserLoaded();

        // 鉴权：有会话走数据权限校验；无会话但令牌有效则放行（令牌本身即授权，且路径绑定不可越权）
        if (!Session::has('user_id') && !$tokenOk) {
            throw new \think\exception\HttpException(401, '未登录');
        }
        if (Session::has('user_id') && !$tokenOk && !$this->canPreview($path)) {
            Log::warning('越权预览附件被拦截', [
                'user_id' => Session::get('user_id'),
                'path'    => $path,
                'ip'      => request()->ip(),
            ]);
            throw new \think\exception\HttpException(403, '无权限访问该文件');
        }

        // 根据扩展名确定 Content-Type（覆盖上传白名单内可内嵌展示的格式：
        // 合同附件 PDF / Word / JPG / PNG；资料库另含 GIF / WebP / TXT；Excel 不支持内嵌，保持 octet-stream 下载）
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'txt'  => 'text/plain; charset=utf-8',
        ];
        $contentType = $mimeMap[$ext] ?? 'application/octet-stream';

        // P3a-5：使用 ThinkPHP Response 对象输出文件，避免 header()+exit 绕过框架后置处理
        // force(false) → Content-Disposition 不带 attachment，浏览器内嵌显示（inline）而非下载
        // 文件名优先用「业务原始文件名」（合同附件 file_url JSON 的 name / 资料库 file_name）：
        // 移动端 WebView 对 iframe/原生下载按 Content-Disposition filename 命名，
        // 用物理名（Ymd_His_随机）或缺省时下载器会回退 URL pathname 段「preview」→ 下载文件错存为 preview.htm
        $displayName = $this->resolveDisplayName($path) ?: basename($real);
        return download($real, $displayName)
            ->force(false)
            ->expire(3600)
            ->mimeType($contentType);
    }

    /**
     * 解析文件的业务原始文件名（用于 Content-Disposition filename）
     * - 合同附件：file_url(JSON 数组) 中精确匹配 url 的 name 字段
     * - 资料库：file_name 字段
     * - 查不到（孤儿文件）返回 null，调用方回退物理文件名
     * v2.44.1 P1：返回前净化——剥离 CR/LF/Tab 与控制字符、剔除双引号，防止
     * Content-Disposition 响应头注入（PHP header() 默认不拦截 CRLF）；
     * 与 ReportController::safeExportName 同口径，净化后为空则回退「attachment」。
     * @param string $path 文件相对路径
     */
    private function resolveDisplayName(string $path): ?string
    {
        $name = null;
        // ① 资料库：共享资料，file_name 即业务名
        $lib = ResourceLogic::findByFileUrl($path);
        if ($lib && !empty($lib['file_name'])) {
            $name = $lib['file_name'];
        } else {
            // ② 合同附件：与 canPreview 同源的预筛选 + 精确匹配
            $contracts = ContractLogic::findByAttachmentPath($this->escapeLike(basename($path)));
            foreach ($contracts as $c) {
                foreach (json_decode($c['file_url'] ?? '', true) ?: [] as $item) {
                    if (isset($item['url']) && $item['url'] === $path && !empty($item['name'])) {
                        $name = $item['name'];
                        break 2;
                    }
                }
            }
        }
        if ($name === null) {
            return null;
        }
        // 净化：剔除控制字符（含 CRLF 响应头注入）与双引号；回退安全名
        $clean = (string)preg_replace('/[\x00-\x1F\x7F"]/', '', (string)$name);
        $clean = trim($clean);
        return $clean !== '' ? $clean : 'attachment';
    }

    /**
     * 判断当前登录用户是否可预览该文件
     * - 合同附件：精确匹配 file_url(JSON 数组) 中的 url，且需满足数据权限(owner/dept/all)
     * - 资料库文件：共享参考资料，已登录即可预览（与资料库列表权限一致）
     * - 其余孤儿文件：默认拒绝（最小权限原则）
     *
     * @param string $path 请求的文件相对路径（与库中 file_url 精确比对）
     */
    private function canPreview(string $path): bool
    {
        // ① 合同附件：在 file_url(JSON 数组) 中精确匹配请求路径
        // 注意：file_url 经 json_encode 后会将 "/" 转义为 "\/"，故 LIKE 预筛选必须用
        // 不会被转义的「文件名」(basename) 而非完整路径，避免漏匹配应用写入的附件。
        $contracts = ContractLogic::findByAttachmentPath($this->escapeLike(basename($path)));
        foreach ($contracts as $c) {
            $list = json_decode($c['file_url'] ?? '', true) ?: [];
            foreach ($list as $item) {
                // 精确比对 url，避免 LIKE 预筛选的误命中
                if (isset($item['url']) && $item['url'] === $path) {
                    return AuthLogic::canAccessRecord((int)$c['owner_id'], (int)($c['dept_id'] ?? 0));
                }
            }
        }

        // ② 资料库文件：共享参考资料，任意已登录用户均可预览
        $lib = ResourceLogic::findByFileUrl($path);
        if ($lib) {
            return true;
        }

        // ③ 既不在合同也不在资料库的孤儿文件：默认拒绝
        return false;
    }

    /**
     * LIKE 通配符转义，避免路径中的 % 干扰预筛选。
     * 注：不转义下划线 _ —— 上传保存的文件名格式为 Ymd_His_xxx，
     * 转义 _ 会导致 LIKE 在部分 MySQL 配置下匹配失败，预筛选返回 0 条，
     * canPreview 直接判 false → 附件预览 403。
     * basename 仅用于 LIKE 预筛选，精确匹配由 canPreview 内的 === 比较保证，
     * 故 _ 保持原样不会影响正确性（最多多预筛几条记录）。
     */
    private function escapeLike(string $value): string
    {
        return str_replace('%', '\\%', $value);
    }

    /**
     * 校验预览签名令牌（包装 common.php 的全局 helper）
     * @param string $path  文件相对路径
     * @param string $token base64(exp.signature)
     */
    private function validatePreviewToken(string $path, string $token): bool
    {
        if (!function_exists('validate_preview_token')) {
            return false;
        }
        return validate_preview_token($path, $token);
    }

    /**
     * 确保当前用户已装载到 Session
     * AuthLogic::canAccessRecord 依赖 Session::get('user') 判定数据范围；
     * Cookie 登录通道下该值通常已由登录流程装载，此处做兜底，
     * 保证 JWT / Cookie 双通道下数据权限判定均准确（避免空 user 误判为可访问）。
     */
    private function ensureUserLoaded(): void
    {
        if (!Session::has('user') && Session::has('user_id')) {
            AuthLogic::ensureSession((int)Session::get('user_id'));
        }
    }
}
