<?php
// +----------------------------------------------------------------------
// | 资料库（合同范本 / 开票资料 / 标准条款 / 其他）
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use think\facade\Log;
use app\BaseController;
use app\common\logic\ResourceLogic;

class ResourceController extends BaseController
{
    // 资料分类中文映射
    const CATEGORIES = [
        'TEMPLATE' => '合同范本',
        'INVOICE'  => '开票资料',
        'CLAUSE'   => '标准条款',
        'OTHER'    => '其他',
    ];

    /** 资料库页面 */
    public function index()
    {
        $this->requirePermission('library:view');
        $categories = self::CATEGORIES;
        $companies  = ResourceLogic::getCompanies();
        View::assign('categories', $categories);
        View::assign('companies', $companies);
        // v2.43.6：上传/编辑/删除拆分独立权限码（library:upload/edit/delete），取代原 library:manage
        View::assign('can_upload', $this->hasPermission('library:upload'));
        View::assign('can_edit', $this->hasPermission('library:edit'));
        View::assign('can_delete', $this->hasPermission('library:delete'));
        View::assign('menu_active', 'resource');
        return View::fetch();
    }

    /** 资料详情页（v2.43.5 补丁：PC 列表卡片点击打开详情——此前仅移动端有 /m/resource/<id>） */
    public function detail($id)
    {
        $this->requirePermission('library:view');
        $id   = (int)$id;
        $item = ResourceLogic::findRaw($id);
        if (!$item) {
            throw new \think\exception\HttpException(404, '资料不存在或已删除');
        }
        $cats       = ResourceLogic::categories();
        $companyMap = array_column(ResourceLogic::getCompanies(), 'name', 'id');
        $item['category_name'] = $cats[$item['category']] ?? $item['category'];
        $item['content_arr']   = ResourceLogic::decodedContent($item['content'] ?? '');
        $item['company_name']  = $item['company_id'] > 0 ? ($companyMap[$item['company_id']] ?? '') : '';
        View::assign('item', $item);
        View::assign('invoice_fields', ResourceLogic::$INVOICE_FIELDS);
        // v2.43.6：编辑/删除按独立权限码控制（hasPermission 已短路 is_admin 全量）
        View::assign('can_edit', $this->hasPermission('library:edit'));
        View::assign('can_delete', $this->hasPermission('library:delete'));
        // v2.43.6：详情页编辑弹窗所需下拉数据
        View::assign('categories', self::CATEGORIES);
        View::assign('companies', ResourceLogic::getCompanies());
        View::assign('menu_active', 'resource');
        return View::fetch('resource/detail');
    }

    /** AJAX: 列表（按分类/关联主体筛选） */
    public function list()
    {
        $this->requirePermission('library:view');
        $category = $this->getParam('category', '');
        $companyId = (int)$this->getParam('company_id', 0);
        $keyword = trim($this->getParam('keyword', ''));
        $page = (int)$this->getParam('page', 1);
        $pageSize = (int)$this->getParam('page_size', 20);
        [$page, $pageSize] = ResourceLogic::normalizePage($page, $pageSize);

        $result = ResourceLogic::getList($category, $companyId, $keyword, $page, $pageSize);
        return json_success([
            'list'      => $result['list'],
            'total'     => $result['total'],
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /** AJAX: 上传并保存资料（仅文件上传，分类/说明由表单提供）——v2.43.6：权限拆分为 library:upload */
    public function save()
    {
        $this->requirePermission('library:upload');
        $title    = trim($this->getPost('title', ''));
        $category = strtoupper(trim($this->getPost('category', 'OTHER')));
        $companyId = (int)$this->getPost('company_id', 0);
        $desc     = trim($this->getPost('description', ''));
        if (!isset(self::CATEGORIES[$category])) { $category = 'OTHER'; }
        if ($title === '') { return json_error('请填写资料标题'); }

        // 结构化字段（仅开票资料类使用）：前端传 JSON 字符串，服务端校验后存储
        $content = '';
        if ($category === 'INVOICE') {
            $raw = trim($this->getPost('content', ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    return json_error('开票资料字段格式不正确');
                }
                $content = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }
        }

        // 2026-08-05：file() 在 $_FILES 异常（超限/部分上传/无临时目录等）时抛 Exception，避免 500「服务器内部错误」。
        // 按错误码区分提示：1/2=大小超限，其余=通用失败（与 ContractController::upload 对齐）。
        try {
            $file = request()->file('file');
        } catch (\Throwable $e) {
            $code = $e->getCode();
            if (in_array($code, [1, 2], true)) {
                return json_error('文件过大，超出服务器接收上限，请压缩后再上传');
            }
            return json_error('文件上传失败（服务器接收异常），请重试');
        }
        // 防御：multiple 多文件时 file() 返回 UploadedFile 数组，对其调用方法会触发 Error → 500
        if ($file !== null && !$file instanceof \think\file\UploadedFile) {
            return json_error('一次仅支持上传一个文件');
        }
        // 开票资料允许「纯字段录入」（不传文件）；其他分类或两者皆无时必须上传文件
        if (!$file && $content === '') {
            return json_error('请选择要上传的文件，或填写开票资料字段');
        }

        $url = ''; $name = ''; $size = 0;
        if ($file) {
            $r = $this->handleUploadFile($file);
            if (!$r['ok']) { return json_error($r['msg']); }
            $url = $r['url']; $name = $r['name']; $size = $r['size'];
        }

        $id = ResourceLogic::create([
            'category'   => $category,
            'title'      => $title,
            'file_url'   => $url,
            'file_name'  => $name,
            'file_size'  => $size,
            'content'    => $content,
            'description'=> $desc,
            'company_id' => $companyId,
        ], $this->userId);
        return json_success(['id' => $id, 'url' => $url], '保存成功');
    }

    /** AJAX: 编辑资料（v2.43.6 新增：标题/分类/说明/主体/开票字段可改，文件可选替换）——权限 library:edit */
    public function update()
    {
        $this->requirePermission('library:edit');
        $id = (int)$this->getPost('id', 0);
        if ($id <= 0) { return json_error('参数错误'); }
        $row = ResourceLogic::findRaw($id);
        if (!$row) { return json_error('资料不存在或已删除'); }

        $title    = trim($this->getPost('title', ''));
        $category = strtoupper(trim($this->getPost('category', 'OTHER')));
        $companyId = (int)$this->getPost('company_id', 0);
        $desc     = trim($this->getPost('description', ''));
        if (!isset(self::CATEGORIES[$category])) { $category = 'OTHER'; }
        if ($title === '') { return json_error('请填写资料标题'); }

        $content = '';
        if ($category === 'INVOICE') {
            $raw = trim($this->getPost('content', ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) { return json_error('开票资料字段格式不正确'); }
                $content = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }
        }

        $data = [
            'title'      => $title,
            'category'   => $category,
            'company_id' => $companyId,
            'description'=> $desc,
            'content'    => $content,
        ];

        // 可选替换文件：编辑时传了新文件才替换（校验逻辑与上传一致），并删除旧物理文件
        try {
            $file = request()->file('file');
        } catch (\Throwable $e) {
            $code = $e->getCode();
            if (in_array($code, [1, 2], true)) {
                return json_error('文件过大，超出服务器接收上限，请压缩后再上传');
            }
            return json_error('文件上传失败（服务器接收异常），请重试');
        }
        if ($file !== null && !$file instanceof \think\file\UploadedFile) {
            return json_error('一次仅支持上传一个文件');
        }
        if ($file) {
            $r = $this->handleUploadFile($file);
            if (!$r['ok']) { return json_error($r['msg']); }
            $data['file_url']  = $r['url'];
            $data['file_name'] = $r['name'];
            $data['file_size'] = $r['size'];
            $this->removePhysicalFile((string)($row['file_url'] ?? ''));
        }

        ResourceLogic::update($id, $data);
        return json_success(null, '已保存');
    }

    /**
     * 上传文件统一处理（真实类型白名单校验 + 落盘），供 save/update 复用
     * @return array ['ok'=>true,'url'=>..,'name'=>..,'size'=>..] 或 ['ok'=>false,'msg'=>..]
     */
    private function handleUploadFile(\think\file\UploadedFile $file): array
    {
        // 先取得真实临时路径，供后续 finfo 真实类型校验与大小校验使用；
        // 必须在 $file->move() 之前取得，否则临时文件被移动后 finfo 读取会失败。
        $tmpPath = $file->getRealPath() ?: $file->getPathname();

        // 大小限制 20MB（先校验大小，避免超大文件做无谓的 MIME 解析）
        $maxSize = 20 * 1024 * 1024;
        $size    = @filesize($tmpPath);
        if ($size === false) {
            // 临时文件不可读（已被清理/权限异常）
            @unlink($tmpPath);
            return ['ok' => false, 'msg' => '上传临时文件不可读，请重试'];
        }
        if ($size > $maxSize) {
            @unlink($tmpPath); // 超限自动清理 PHP 上传临时文件，避免残留
            return ['ok' => false, 'msg' => '文件过大，最大 20MB'];
        }

        // 真实文件类型校验——用 finfo 读取文件内容「真实 MIME」
        // （而非信任客户端扩展名与 $file->getMime() 客户端声明 MIME，二者均可通过改扩展名/伪造请求头绕过）。
        // 仅允许白名单内的真实文档/图片类型；保存扩展名以服务端真实类型为准，杜绝「改扩展名伪装」上传可执行脚本。
        try {
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($tmpPath);
        } catch (\Throwable $e) {
            @unlink($tmpPath);
            Log::error('finfo 不可用，拒绝上传', ['error' => $e->getMessage()]);
            return ['ok' => false, 'msg' => '服务器文件校验组件不可用，请联系管理员'];
        }
        // 允许的「真实类型 → 扩展名」正向白名单（拒绝一切未列出的类型，含脚本/HTML/恶意载荷）
        // 白名单收敛为纯函数 resolve_library_attachment_ext()（app/common.php），供单元测试直接覆盖
        // v2.43.7：传客户端原始扩展名（OLE 回退判定 doc/xls 用，与合同附件口径一致）
        $ext = resolve_library_attachment_ext($realMime, $file->getOriginalExtension());
        if ($ext === null) {
            @unlink($tmpPath); // 类型被拒同样清理临时文件（含 $realMime 为空/不可识别）
            return ['ok' => false, 'msg' => '文件真实类型不被支持（' . ($realMime ?: '未知') . '），请转换为 jpg/png/pdf/doc/xls 等标准格式后重新上传'];
        }
        try {
            $saveName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $subDir   = date('Ym');
            $savePath = app()->getRootPath() . 'public/uploads/library/' . $subDir;
            if (!is_dir($savePath)) { mkdir($savePath, 0755, true); }
            $file->move($savePath, $saveName);
            // v2.44.1 P0-1：存储型 XSS 修复——原始文件名入库并回显到视图 <script>，净化尖括号/引号并去控制字符（与 ContractController::upload 口径一致）
            $name = preg_replace('/[<>"]/u', '', (string)$file->getOriginalName());
            $name = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $name);
            $name = trim($name);
            if ($name === '') { $name = 'attachment.' . $ext; }
            return [
                'ok'   => true,
                'url'  => '/uploads/library/' . $subDir . '/' . $saveName,
                'name' => $name,
                'size' => filesize($savePath . DIRECTORY_SEPARATOR . $saveName),
            ];
        } catch (\Throwable $e) {
            @unlink($tmpPath); // move 失败同样清理临时文件
            Log::error('资料库文件落盘失败', ['error' => $e->getMessage()]);
            return ['ok' => false, 'msg' => '文件上传失败，请稍后重试'];
        }
    }

    /** 删除物理文件（realpath 边界校验防目录穿越，与 delete 共用；实现收敛到 common.php remove_upload_file） */
    private function removePhysicalFile(string $rel): void
    {
        remove_upload_file($rel);
    }

    /** AJAX: 删除资料——v2.43.6：权限拆分为 library:delete */
    public function delete()
    {
        $this->requirePermission('library:delete');
        $id = (int)$this->getPost('id', 0);
        if ($id <= 0) { return json_error('参数错误'); }
        $row = ResourceLogic::findRaw($id);
        if ($row) {
            // 尝试删除物理文件（P1：realpath 边界校验防目录穿越——
            // file_url 若被配置恢复/注入写为 ../../config/.env，unlink 可删除 public 边界外任意文件）
            $this->removePhysicalFile((string)($row['file_url'] ?? ''));
            ResourceLogic::delete($id);
        }
        return json_success(null, '已删除');
    }
}
