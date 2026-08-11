<?php
// +----------------------------------------------------------------------
// | 模板控制器
// +----------------------------------------------------------------------

namespace app\controller;

use think\facade\View;
use app\BaseController;
use app\common\logic\TemplateLogic;
use app\common\logic\ApprovalLogic;

class TemplateController extends BaseController
{
    // P3-1【m-A3】死代码清理：原 index/create/edit 页面路由（route/app.php:63-65）已全部重定向至
    // /admin?tab=template，模板管理改由 AdminController::template 渲染，故此处仅保留后台 AJAX 管理入口。

    /**
     * 保存模板 — 新建/编辑统一入口
     * 含必填校验（名称、编码）、关联审批流（default_flow_id）
     * v2.40.2：守卫由 template:manage 改为 system:user——模板管理入口已收敛至系统管理 tab（system:user 控制），
     * template:manage 权限码已从角色配置移除，避免入口可见而 AJAX 被拦的权限错位
     */
    public function save()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);

        $data = [
            'name'              => $this->getPost('name', ''),
            'code'              => $this->getPost('code', ''),
            'category'          => $this->getPost('category', ''),
            'content'           => $this->getPost('content', ''),
            'fields_schema'     => $this->getPost('fields_schema', '[]'),
            'default_direction' => $this->getPost('default_direction', ''),
            'default_flow_id'   => (int)$this->getPost('default_flow_id', 0),
            'tips'              => $this->getPost('tips', ''),
        ];

        if (empty($data['name']) || empty($data['code'])) {
            return json_error('模板名称和编码不能为空');
        }

        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        } else {
            $data['creator_id'] = $this->userId;
        }
        TemplateLogic::save($id, $data);
        return json_success(null, '保存成功');
    }

    /** 禁用模板（软删除：status → DISABLED），非物理删除 */
    public function delete()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        TemplateLogic::setStatus($id, 'DISABLED');
        return json_success(null, '已禁用');
    }

    /** 发布模板（status → PUBLISHED） */
    public function publish()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        TemplateLogic::setStatus($id, 'PUBLISHED');
        return json_success(null, '已发布');
    }

    /**
     * 恢复已禁用的模板（重新激活为草稿）
     */
    public function restore()
    {
        $this->requirePermission('system:user');
        $id = (int)$this->getPost('id', 0);
        TemplateLogic::setStatus($id, 'DRAFT');
        return json_success(null, '已恢复为草稿');
    }

    /**
     * AJAX: 获取模板内容 — 新建/编辑合同时选择模板后加载正文
     */
    public function getContent()
    {
        // 新建/编辑合同时加载模板正文：合同创建/编辑权限（基础权限，全员默认；v2.40.2 移除 template:manage——已从角色配置废除）
        $this->requireAnyPermission(['contract:create', 'contract:edit']);
        $id = (int)$this->getParam('id', 0);
        if (!$id) {
            return json_error('缺少模板ID');
        }
        $tpl = TemplateLogic::getById($id);
        if (!$tpl) {
            return json_error('模板不存在');
        }
        return json_success([
            'id'      => $tpl['id'],
            'name'    => $tpl['name'],
            // m18：模板正文输出转义（防管理员自触发 Self-XSS），不改变存储逻辑；
            // 若前端以 innerHTML 形式呈现该正文，转义后的实体不会被当作 HTML 执行。
            'content' => htmlspecialchars($tpl['content'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ]);
    }

    /**
     * AJAX: 获取合同类型预设 — 新建合同时选择模板后带回默认分类/方向/建议审批流/必填提醒
     */
    public function getPreset()
    {
        $this->requireAnyPermission(['contract:create', 'contract:edit']);
        $id = (int)$this->getParam('id', 0);
        if (!$id) {
            return json_error('缺少模板ID');
        }
        $tpl = TemplateLogic::getById($id);
        if (!$tpl) {
            return json_error('模板不存在');
        }
        // 解析结构化字段 schema（供新建合同动态渲染）
        $schema = [];
        $raw = trim((string)($tpl['fields_schema'] ?? ''));
        if ($raw !== '' && $raw !== '[]') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $schema = $decoded;
        }
        return json_success([
            'id'               => $tpl['id'],
            'name'             => $tpl['name'],
            'category'         => $tpl['category'] ?? '',
            'direction'        => $tpl['default_direction'] ?? '',
            'trade_attr'       => (int)($tpl['default_trade_attr'] ?? 1),
            'flow_id'          => (int)($tpl['default_flow_id'] ?? 0),
            'tips'             => $tpl['tips'] ?? '',
            'fields_schema'    => $schema,
        ]);
    }
}
