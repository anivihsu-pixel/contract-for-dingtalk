<?php
// +----------------------------------------------------------------------
// | 轻量 XLSX 导出（REV-27）：不依赖任何第三方库，使用 ZipArchive 生成合法 .xlsx。
// | 采用「内联字符串(inlineStr)」单元格，避免共享字符串表复杂度；中文字符安全转义。
// | 用法：return \app\common\helper\XlsxHelper::export($headers, $rows, '文件名.xlsx');
// +----------------------------------------------------------------------

namespace app\common\helper;

use app\common\response\StreamedFileResponse;

class XlsxHelper
{
    /**
     * 生成 .xlsx 临时文件并返回路径（调用方以 StreamedFileResponse 流式输出后删除）
     * @param array               $headers 表头
     * @param iterable|callable   $rows    数据行：数组/生成器（逐行 foreach），
     *                                    或回调式生产者（P2-14：接收 sink 回调，把每行喂给 sink，
     *                                    与 ContractLogic::eachExportRow 的 chunk 回调同构，内存恒定）
     */
    public static function buildTempFile(array $headers, iterable|callable $rows): string
    {
        // 前置检查：ZipArchive 是 .xlsx (OOXML) 的硬依赖，缺失时给出明确错误（P0 修复）
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('服务器缺少 PHP zip 扩展（ZipArchive），无法生成 XLSX，请改用 CSV 导出或启用 zip 扩展');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('无法创建 xlsx 临时文件');
        }
        // PHP 8.4：tempnam 会预创建空文件，ZipArchive::open 以空文件为目标已废弃（Deprecated），
        // 先删除空文件，让 ZipArchive::CREATE 自行创建，避免弃用告警污染日志
        @unlink($tmp);

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('无法打开 xlsx 临时文件');
        }

        // 1) 内容类型声明
        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        // 2) 包关系
        $zip->addFromString('_rels/.rels', self::rootRelsXml());
        // 3) 工作簿
        $zip->addFromString('xl/workbook.xml', self::workbookXml());
        // 4) 工作簿关系
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml());
        // 5) 工作表（写入临时文件后 addFile，避免超大数据全量驻留内存）
        $sheetTmp = tempnam(sys_get_temp_dir(), 'xsheet_');
        $fp = fopen($sheetTmp, 'w');
        if (!$fp) {
            $zip->close();
            throw new \RuntimeException('无法创建 xlsx 工作表临时文件');
        }
        fwrite($fp, self::sheetHeaderXml());
        $r = 1;
        // 表头行
        self::writeRow($fp, $r++, $headers);
        // 数据行（逐行写出，内存恒定）
        if (is_callable($rows)) {
            // 回调式生产者：chunk 边查边喂 sink，避免把整批数据收集进内存
            $rows(function (array $row) use ($fp, &$r) {
                self::writeRow($fp, $r++, $row);
            });
        } else {
            foreach ($rows as $row) {
                self::writeRow($fp, $r++, $row);
            }
        }
        fwrite($fp, self::sheetFooterXml());
        fclose($fp);
        $zip->addFile($sheetTmp, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($sheetTmp);

        return $tmp;
    }

    /**
     * 直接返回流式响应（供控制器 return）
     * P0 修复：ZipArchive 不可用时自动降级为 CSV 导出，避免 500 白页。
     * 降级时文件名后缀改为 .csv，Content-Type 改为 text/csv，控制器无需改动。
     */
    public static function export(array $headers, array $rows, string $filename)
    {
        return self::exportFrom($headers, $rows, $filename);
    }

    /**
     * 流式导出（P2-14【M-A4】：替代全量数组驻留，超大导出内存恒定）。
     * $rows 可为数组/生成器（iterable）或回调式生产者（callable，接收 sink 逐行喂入）；
     * ZipArchive 缺失时同样降级为「逐行写临时文件」的 CSV，避免降级路径全量驻留。
     */
    public static function exportFrom(array $headers, iterable|callable $rows, string $filename)
    {
        try {
            $tmp = self::buildTempFile($headers, $rows);
            return new StreamedFileResponse($tmp, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\RuntimeException $e) {
            // 降级：XLSX 依赖缺失时改用 CSV（UTF-8 BOM 保证 Excel 中文不乱码）；
            // 注：buildTempFile 在消费迭代器前即抛出（ZipArchive 前置检查/临时文件失败），迭代器仍可完整复用
            $csvFilename = preg_replace('/\.xlsx$/i', '.csv', $filename);
            $tmp = tempnam(sys_get_temp_dir(), 'xcsv_');
            if ($tmp === false) {
                throw new \RuntimeException('无法创建 CSV 临时文件');
            }
            $fp = fopen($tmp, 'w');
            if (!$fp) {
                throw new \RuntimeException('无法创建 CSV 临时文件');
            }
            fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM
            fwrite($fp, self::csvRow($headers));
            if (is_callable($rows)) {
                $rows(function (array $row) use ($fp) {
                    fwrite($fp, self::csvRow($row));
                });
            } else {
                foreach ($rows as $row) {
                    fwrite($fp, self::csvRow($row));
                }
            }
            fclose($fp);
            return new StreamedFileResponse($tmp, 200, [
                'Content-Type'        => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $csvFilename . '"',
            ]);
        }
    }

    /** CSV 行构造（逗号分隔，双引号转义）；P2：单元格先过公式注入中和（= + - @ 前缀前置 '） */
    private static function csvRow(array $cells): string
    {
        $out = [];
        foreach ($cells as $c) {
            $s = export_safe_cell($c);
            // 含逗号/引号/换行的字段需用双引号包裹并把内部双引号翻倍
            if (preg_match('/[",\r\n]/', $s)) {
                $s = '"' . str_replace('"', '""', $s) . '"';
            }
            $out[] = $s;
        }
        return implode(',', $out) . "\n";
    }

    // ---------------- 内部 XML 构造 ----------------

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    private static function sheetHeaderXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';
    }

    private static function sheetFooterXml(): string
    {
        return '</sheetData></worksheet>';
    }

    /** 写出一行（内联字符串单元格） */
    private static function writeRow($fp, int $rowIndex, array $cells): void
    {
        $xml = '<row r="' . $rowIndex . '">';
        $col = 0;
        foreach ($cells as $value) {
            $col++;
            $ref = self::colLetter($col) . $rowIndex;
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is>'
                . '<t xml:space="preserve">' . self::esc($value) . '</t>'
                . '</is></c>';
        }
        $xml .= '</row>';
        fwrite($fp, $xml);
    }

    /** 列序号 -> Excel 列字母（1=A, 27=AA） */
    private static function colLetter(int $idx): string
    {
        $s = '';
        while ($idx > 0) {
            $rem = ($idx - 1) % 26;
            $s = chr(65 + $rem) . $s;
            $idx = intval(($idx - 1) / 26);
        }
        return $s;
    }

    /** XML 安全转义（含引号与非法控制字符）；P2：先过公式注入中和（inlineStr 纵深） */
    private static function esc($value): string
    {
        // 公式注入中和：= + - @ 开头前置 '（XLSX inlineStr 本不会执行公式，此处为纵深防御）
        $s = export_safe_cell($value);
        // 去除不可打印控制字符（除制表/换行外），避免 XML 非法
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
