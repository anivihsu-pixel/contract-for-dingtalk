<?php
// +----------------------------------------------------------------------
// | 流式文件响应（REV-45）：避免超大导出把全部内容拼接进 PHP 内存。
// | 构造时传入临时文件路径；发送阶段由 output() 直接 readfile 流式吐出后删除临时文件。
// +----------------------------------------------------------------------

namespace app\common\response;

use think\Response;

class StreamedFileResponse extends Response
{
    /** @var string 待流式输出的临时文件路径 */
    protected $tmpFile;

    public function __construct(string $tmpFile, int $code = 200, array $header = [])
    {
        $this->tmpFile = $tmpFile;
        $this->code    = $code;
        $this->header  = $header;
        $this->data    = '';
    }

    /**
     * 处理输出：直接以流式方式把临时文件发送给客户端，随后删除临时文件。
     * 返回空字符串，避免父类 sendData 再 echo 一次（文件字节已由 readfile 输出）。
     */
    protected function output($data)
    {
        return '';
    }

    /**
     * 发送响应（BUG-FIX：REV-45 原实现把 readfile 放在 output() 中，而父类 send() 先
     * getContent() 输出文件字节、后检查 headers_sent() 发送 header——body 先于 header 输出，
     * 导致 headers_sent() 恒为 true，Content-Type / Content-Disposition 全部丢失，
     * 浏览器不触发下载而是把 CSV/XLSX 当 HTML 渲染。
     * 修复：重写 send()，先发送状态码与 header（含 cookie），再流式 readfile 输出文件内容）。
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->code);
            foreach ($this->header as $name => $val) {
                header($name . (!is_null($val) ? ':' . $val : ''));
            }
            if ($this->cookie) {
                $this->cookie->save();
            }
        }

        if (!empty($this->tmpFile) && file_exists($this->tmpFile)) {
            // 关闭输出缓冲，确保大文件边读边发，不堆积在内存
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            readfile($this->tmpFile);
            @unlink($this->tmpFile);
        }
    }
}
