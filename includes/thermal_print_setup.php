<?php
/**
 * مقاس ونسخ الملصق الحراري.
 * اختياري قبل الاستدعاء: $thermalSizes, $thermalDefaultSize, $copiesMax
 */
if (empty($thermalSizes) || !is_array($thermalSizes)) {
    $thermalSizes = [
        '50x25' => ['w' => 50, 'h' => 25, 'name_key' => 'thermal.size_50x25'],
        '44x22' => ['w' => 44, 'h' => 22, 'name_key' => 'thermal.size_44x22'],
    ];
}
$thermalDefaultSize = (string) ($thermalDefaultSize ?? '50x25');
$size = strtolower(trim((string) ($_GET['size'] ?? $_GET['tpl'] ?? '')));
if ($size === '22x40' || $size === '22x44') {
    $size = '44x22';
}
if (!isset($thermalSizes[$size])) {
    $size = isset($thermalSizes[$thermalDefaultSize]) ? $thermalDefaultSize : (string) array_key_first($thermalSizes);
}
$copiesMax = (int) ($copiesMax ?? 20);
if ($copiesMax < 1) {
    $copiesMax = 1;
}
$copies = (int) ($_GET['copies'] ?? 1);
if ($copies < 1) {
    $copies = 1;
}
if ($copies > $copiesMax) {
    $copies = $copiesMax;
}
$pageWmm = (int) $thermalSizes[$size]['w'];
$pageHmm = (int) $thermalSizes[$size]['h'];
$pageW = $pageWmm . 'mm';
$pageH = $pageHmm . 'mm';
