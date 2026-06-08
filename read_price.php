<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('C:/Users/dzian/Downloads/24.02.2026 ПРАЙС БЕРЕЗКА ОПТ (1).xlsx');
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, false);

echo "Всего строк: " . count($rows) . "\n";
echo "Листов: " . $spreadsheet->getSheetCount() . "\n\n";

// Первые 15 строк
for ($i = 0; $i < 15; $i++) {
    $row = $rows[$i] ?? [];
    $line = [];
    foreach ($row as $j => $v) {
        if ($v !== null && trim((string)$v) !== '') {
            $line[] = "[$j]=" . mb_substr((string)$v, 0, 40);
        }
    }
    if ($line) echo "row $i: " . implode(' | ', $line) . "\n";
}
