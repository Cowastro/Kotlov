<?php

namespace App\Filament\Pages;

use App\Models\SupplierReviewDecision;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static ?string $navigationLabel = 'Отчёты импорта';
    protected static ?string $title = 'Отчёты импорта';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.pages.import-reports';

    public string $supplier = '';
    public string $type = '';
    public string $search = '';
    public ?string $selectedFile = null;
    public int $perPage = 100;
    public bool $showAllColumns = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->selectedFile = $this->reports()[0]['relative_path'] ?? null;
    }

    public function updatedSupplier(): void
    {
        $this->selectedFile = $this->reports()[0]['relative_path'] ?? null;
    }

    public function updatedType(): void
    {
        $this->selectedFile = $this->reports()[0]['relative_path'] ?? null;
    }

    public function updatedSearch(): void
    {
        $this->selectedFile ??= $this->reports()[0]['relative_path'] ?? null;
    }

    public function supplierOptions(): array
    {
        return collect($this->allReports())
            ->pluck('supplier')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function typeOptions(): array
    {
        return collect($this->allReports())
            ->pluck('type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function reports(): array
    {
        return array_values(array_filter($this->allReports(), function (array $report): bool {
            if ($this->supplier !== '' && $report['supplier'] !== $this->supplier) {
                return false;
            }

            if ($this->type !== '' && $report['type'] !== $this->type) {
                return false;
            }

            if ($this->search !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $report['supplier'],
                    $report['type'],
                    $report['file_name'],
                    $report['relative_path'],
                ]));

                if (! str_contains($haystack, mb_strtolower($this->search))) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function selectedReport(): ?array
    {
        $reports = $this->reports();
        if ($reports === []) {
            return null;
        }

        foreach ($reports as $report) {
            if ($report['relative_path'] === $this->selectedFile) {
                return $report;
            }
        }

        return $reports[0];
    }

    public function selectedRows(): array
    {
        $report = $this->selectedReport();
        if (! $report) {
            return [];
        }

        return $this->enrichRowsForSimpleReport(
            $this->enrichRowsWithProductSkus(
                array_slice($this->readCsv($report['absolute_path']), 0, $this->perPage)
            )
        );
    }

    public function selectedHeaders(): array
    {
        $rows = $this->selectedRows();
        if ($rows === []) {
            return [];
        }

        $hidden = [
            'kotlov_sku',
            'product_sku',
            'possible_product_sku',
            'matched_product_sku',
        ];

        $preferred = [
            'price_row',
            'price_title',
            'price_article',
            'supplier_title',
            'supplier_sku',
            'supplier_url',
            'source_url',
            'possible_supplier_title',
            'possible_product_title',
            'matched_product_title',
            'title',
            'possible_supplier_product_id',
            'supplier_product_id',
            'possible_product_id',
            'product_id',
            'matched_product_id',
            'brand',
            'old_supplier_price',
            'new_supplier_cost',
            'supplier_price',
            'product_retail_price',
            'old_stock_status',
            'new_stock_status',
            'stock_status',
            'match_type',
            'confidence',
            'action',
            'recommended_action',
            'reason',
            'note',
            'error',
        ];

        $simple = [
            'simple_next_step',
            'price_row',
            'supplier_item',
            'supplier_article_short',
            'price_list_cost',
            'kotlov_item',
            'current_supplier_cost',
            'kotlov_retail',
            'suggested_retail_simple',
            'margin_simple',
            'retail_price_action',
            'report_problem',
        ];

        if (! $this->showAllColumns) {
            return array_values(array_filter(
                $simple,
                fn (string $header): bool => array_key_exists($header, $rows[0])
            ));
        }

        $headers = array_values(array_filter(
            array_keys($rows[0]),
            fn (string $header): bool => ! in_array($header, $hidden, true) && ! in_array($header, $simple, true)
        ));

        $ordered = array_values(array_filter(
            $preferred,
            fn (string $header): bool => in_array($header, $headers, true)
        ));

        $tail = array_values(array_filter(
            $headers,
            fn (string $header): bool => ! in_array($header, $ordered, true)
        ));

        return array_merge($simple, $ordered, $tail);
    }

    public function headerLabel(string $header): string
    {
        $simpleLabels = [
            'simple_next_step' => 'Что сделать',
            'supplier_item' => 'Товар поставщика / прайс',
            'supplier_article_short' => 'Артикул поставщика',
            'price_list_cost' => 'Закупка из прайса',
            'kotlov_item' => 'Товар KOTLOV',
            'current_supplier_cost' => 'Закупка сейчас',
            'kotlov_retail' => 'Розница сайта',
            'suggested_retail_simple' => 'Розница из прайса',
            'margin_simple' => 'Маржа / контроль',
            'retail_price_action' => 'Розница: действие',
            'report_problem' => 'Почему в отчёте',
        ];

        if (isset($simpleLabels[$header])) {
            return $simpleLabels[$header];
        }

        return [
            'price_row' => 'Строка прайса',
            'price_title' => 'Товар в прайсе',
            'price_article' => 'Артикул в прайсе',
            'price_value' => 'Цена в прайсе',
            'price_article_normalized' => 'Артикул норм.',
            'supplier' => 'Поставщик',
            'supplier_title' => 'Товар поставщика',
            'supplier_name' => 'Товар поставщика',
            'supplier_sku' => 'Артикул поставщика',
            'supplier_article' => 'Артикул поставщика',
            'supplier_url' => 'URL поставщика',
            'source_url' => 'URL источника',
            'source_category' => 'Раздел источника',
            'possible_supplier_product_id' => 'ID связки поставщика',
            'supplier_product_id' => 'ID связки поставщика',
            'possible_supplier_title' => 'Товар в связке поставщика',
            'possible_product_id' => 'ID товара KOTLOV',
            'product_id' => 'ID товара KOTLOV',
            'matched_product_id' => 'ID товара KOTLOV',
            'possible_product_title' => 'Товар KOTLOV',
            'matched_product_title' => 'Товар KOTLOV',
            'title' => 'Товар KOTLOV',
            'brand' => 'Бренд',
            'old_supplier_price' => 'Старая закупка',
            'new_supplier_cost' => 'Новая закупка',
            'supplier_price' => 'Цена поставщика',
            'product_retail_price' => 'Розница KOTLOV',
            'old_stock_status' => 'Старое наличие',
            'new_stock_status' => 'Новое наличие',
            'stock_status' => 'Наличие',
            'stock_text' => 'Текст наличия',
            'product_in_stock_before' => 'Товар был в наличии',
            'match_type' => 'Тип совпадения',
            'confidence' => 'Уверенность',
            'ai_confidence' => 'Уверенность AI',
            'ai_decision' => 'Решение AI',
            'action' => 'Действие',
            'recommended_action' => 'Рекомендация',
            'reason' => 'Причина',
            'note' => 'Примечание',
            'error' => 'Ошибка',
            'page' => 'Страница',
            'attributes_count' => 'Характеристик',
            'images_count' => 'Фото',
            'description_length' => 'Длина описания',
        ][$header] ?? Str::of($header)->replace('_', ' ')->title()->toString();
    }

    public function cellValue(string $header, mixed $value): string
    {
        $value = (string) $value;

        if (in_array($header, ['reason', 'note', 'error', 'ai_reason'], true)) {
            return $this->translateReason($value);
        }

        if (in_array($header, ['action', 'recommended_action', 'match_type', 'ai_decision', 'retail_price_action'], true)) {
            return $this->translateCode($value);
        }

        return $value;
    }

    public function downloadSelected(): ?StreamedResponse
    {
        $report = $this->selectedReport();
        if (! $report || ! is_file($report['absolute_path'])) {
            return null;
        }

        return response()->streamDownload(function () use ($report): void {
            readfile($report['absolute_path']);
        }, $report['file_name'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function queueDecision(int $rowIndex, string $decision): void
    {
        if (! in_array($decision, [
            SupplierReviewDecision::DECISION_LINK,
            SupplierReviewDecision::DECISION_UNLINK,
            SupplierReviewDecision::DECISION_IGNORE,
        ], true)) {
            Notification::make()
                ->title('Неизвестное действие')
                ->danger()
                ->send();

            return;
        }

        $report = $this->selectedReport();
        $rows = $this->selectedRows();
        $row = $rows[$rowIndex] ?? null;

        if (! $report || ! $row) {
            Notification::make()
                ->title('Строка отчёта не найдена')
                ->danger()
                ->send();

            return;
        }

        $supplierProductId = $this->supplierProductId($row);
        $productId = $this->productId($row);

        if ($decision === SupplierReviewDecision::DECISION_LINK && ($supplierProductId === '' || $productId === '')) {
            Notification::make()
                ->title('Для связки нужен товар поставщика и товар KOTLOV')
                ->danger()
                ->send();

            return;
        }

        if ($decision === SupplierReviewDecision::DECISION_UNLINK && $supplierProductId === '') {
            Notification::make()
                ->title('Для удаления связи нужен товар поставщика')
                ->danger()
                ->send();

            return;
        }

        $reportRow = $this->reportRow($row, $rowIndex);
        $decisionKey = sha1(implode('|', [
            $report['relative_path'],
            $reportRow,
            $decision,
            $supplierProductId,
            $productId,
        ]));

        $pendingForRow = SupplierReviewDecision::query()
            ->where('report_file', $report['relative_path'])
            ->where('report_row', $reportRow)
            ->where('status', SupplierReviewDecision::STATUS_PENDING)
            ->first();

        if ($pendingForRow) {
            Notification::make()
                ->title('По этой строке уже есть решение в очереди')
                ->body($this->decisionLabel((string) $pendingForRow->decision))
                ->warning()
                ->send();

            return;
        }

        $existing = SupplierReviewDecision::query()->where('decision_key', $decisionKey)->first();
        if ($existing) {
            Notification::make()
                ->title('Решение уже есть в очереди')
                ->body('Статус: ' . $this->decisionStatusLabel((string) $existing->status))
                ->warning()
                ->send();

            return;
        }

        SupplierReviewDecision::query()->create([
            'decision_key' => $decisionKey,
            'supplier_code' => $report['supplier'] !== 'general' ? $report['supplier'] : null,
            'report_file' => $report['relative_path'],
            'report_row' => $reportRow,
            'decision' => $decision,
            'status' => SupplierReviewDecision::STATUS_PENDING,
            'supplier_product_id' => $supplierProductId !== '' ? (int) $supplierProductId : null,
            'product_id' => $productId !== '' ? (int) $productId : null,
            'supplier_title' => $this->supplierTitle($row),
            'supplier_article' => $this->supplierArticle($row),
            'source_url' => $this->sourceUrl($row),
            'reason' => $this->translateReason((string) ($row['reason'] ?? $row['note'] ?? $row['error'] ?? '')),
            'payload' => $row,
        ]);

        Notification::make()
            ->title('Решение добавлено в очередь')
            ->body($this->decisionLabel($decision))
            ->success()
            ->send();
    }

    public function canLink(array $row): bool
    {
        return $this->supplierProductId($row) !== '' && $this->productId($row) !== '';
    }

    public function canUnlink(array $row): bool
    {
        return $this->supplierProductId($row) !== '';
    }

    public function productAdminUrl(array $row): ?string
    {
        $productId = $this->productId($row);
        if ($productId === '' || ! ctype_digit($productId)) {
            return null;
        }

        return url('/admin/products/' . $productId . '/edit');
    }

    public function kotlovSku(array $row): string
    {
        foreach (['kotlov_sku', 'product_sku', 'possible_product_sku', 'matched_product_sku'] as $key) {
            $sku = trim((string) ($row[$key] ?? ''));
            if ($sku !== '') {
                return $sku;
            }
        }

        return '';
    }

    public function sourceUrl(array $row): ?string
    {
        $url = trim((string) ($row['source_url'] ?? ''));
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : null;
    }

    private function translateReason(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $exact = [
            'article matched but title compatibility is low' => 'Артикул совпал, но название товара отличается. Нужно проверить вручную.',
            'similar title requires manual approval' => 'Похожее название, но совпадение не уверенное. Нужно проверить вручную.',
            'no linked BANIA supplier product found' => 'Не найдена связанная позиция поставщика BANIA.',
            'no confident supplier_product match' => 'Нет уверенного совпадения с товаром поставщика.',
            'price list contains another row for the same BANIA supplier_product' => 'В прайсе есть ещё одна строка для той же связки поставщика. Нужно выбрать правильную.',
            'same supplier article is already mapped to another source' => 'Этот артикул поставщика уже привязан к другой ссылке.',
            'same supplier already mapped this product to another source' => 'Этот товар уже привязан к другой ссылке этого поставщика.',
            'same supplier_product' => 'Найдена существующая связка поставщика.',
            'same sku/article' => 'Совпал артикул / SKU.',
            'distinct KARINA variant' => 'Другая модификация KARINA. Не склеивать автоматически.',
            'distinct sauna door variant' => 'Другая модификация двери. Не склеивать автоматически.',
            'high title similarity' => 'Высокое сходство названий.',
            'approved equivalent' => 'Заранее утверждённое совпадение.',
            'similar title' => 'Похожее название.',
            'no match' => 'Совпадение не найдено.',
            'not found in BANIA wholesale price list' => 'Товар не найден в оптовом прайсе BANIA.',
            'No matching row in dynamic BANIA price list' => 'Нет подходящей строки в динамическом прайсе BANIA.',
            'built from supplier_products where supplier cost equals retail and no google price-list link exists' => 'Построено из связок, где закупка равна рознице и нет привязки к строке прайса.',
            'build-only' => 'Отчёт построен без AI-проверки.',
            'AI did not return a response' => 'AI не вернул ответ.',
        ];

        if (isset($exact[$value])) {
            return $exact[$value];
        }

        if (preg_match('/supplier cost ([0-9.]+) is above product retail ([0-9.]+); check price-list match or retail price/i', $value, $match)) {
            return "Закупка {$match[1]} выше розницы {$match[2]}. Проверь строку прайса или розничную цену.";
        }

        if (str_starts_with($value, 'AI returned invalid JSON:')) {
            return 'AI вернул некорректный JSON: ' . trim(Str::after($value, 'AI returned invalid JSON:'));
        }

        return $value;
    }

    private function translateCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return [
            'manual_review' => 'Ручная проверка',
            'manual_review_legacy' => 'Ручная проверка старого товара',
            'cost_above_retail' => 'Закупка выше розницы',
            'supplier_cost_stock_updated' => 'Закупка и наличие обновлены',
            'matched_updated' => 'Найден и обновлён',
            'created' => 'Создан новый товар',
            'create_candidate' => 'Кандидат на создание',
            'skipped_duplicate' => 'Пропущен дубль',
            'skipped_out_of_stock' => 'Пропущен: нет в наличии',
            'skipped_brand' => 'Пропущен: бренд не разрешён',
            'skipped_not_in_price_list' => 'Пропущен: нет в прайсе',
            'skipped_empty_price' => 'Пропущен: нет цены',
            'skipped_unrelated' => 'Пропущен: не относится к текущему импорту',
            'missing_marked_out_of_stock' => 'Нет в прайсе, отмечен как нет в наличии',
            'unchanged' => 'Без изменений',
            'error' => 'Ошибка',
            'article' => 'Совпадение по артикулу',
            'article_ambiguous' => 'Артикул совпал, название спорное',
            'title' => 'Совпадение по названию',
            'title_possible' => 'Похожее название',
            'title_repair_equal_retail' => 'Ремонт закупки по названию',
            'title_repair_sauna_stove_equal_retail' => 'Ремонт закупки банной печи по названию',
            'not_found' => 'Не найдено',
            'supplier_product' => 'Существующая связка поставщика',
            'supplier_product_conflict' => 'Конфликт связки поставщика',
            'sku' => 'Совпадение по SKU',
            'name_brand' => 'Совпадение по названию и бренду',
            'approved_equivalent' => 'Утверждённый эквивалент',
            'fuzzy' => 'Похожее название',
            'none' => 'Нет совпадения',
            'approved_match' => 'AI подтвердил совпадение',
            'different_variant' => 'AI: другая модификация',
            'not_enough_data' => 'AI: недостаточно данных',
            'can_apply_after_review' => 'Можно применить после проверки',
            'keep_manual_review' => 'Оставить в ручной проверке',
            'retail_price_can_sync' => 'Можно обновить розницу',
            'retail_price_unchanged' => 'Розница совпадает',
            'retail_price_missing' => 'Нет розницы в прайсе',
            'retail_current_below_cost' => 'Текущая розница ниже закупки',
            'retail_skipped_no_product' => 'Нет связанного товара',
            'supplier_cost_stock_retail_updated' => 'Закупка, наличие и розница обновлены',
        ][$value] ?? $value;
    }

    private function enrichRowsForSimpleReport(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $priceListCost = $this->firstFilled($row, ['new_supplier_cost', 'price_value', 'new_bania_price']);
            $currentSupplierCost = $this->firstFilled($row, ['old_supplier_price', 'supplier_price']);
            $retailPrice = $this->firstFilled($row, ['product_retail_price', 'old_product_price']);
            $suggestedRetailPrice = $this->firstFilled($row, ['suggested_retail_price']);
            $problem = $this->translateReason((string) $this->firstFilled($row, ['reason', 'note', 'error']));

            $rows[$index] = [
                'simple_next_step' => $this->simpleNextStep($row, $problem),
                'supplier_item' => $this->supplierTitle($row) ?: '',
                'supplier_article_short' => $this->supplierArticle($row) ?: '',
                'price_list_cost' => $priceListCost,
                'kotlov_item' => $this->kotlovTitle($row),
                'current_supplier_cost' => $currentSupplierCost,
                'kotlov_retail' => $retailPrice,
                'suggested_retail_simple' => $suggestedRetailPrice,
                'margin_simple' => $this->marginText($priceListCost, $suggestedRetailPrice !== '' ? $suggestedRetailPrice : $retailPrice),
                'report_problem' => $problem,
            ] + $row;
        }

        return $rows;
    }

    private function firstFilled(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function kotlovTitle(array $row): string
    {
        return $this->firstFilled($row, ['possible_product_title', 'matched_product_title', 'title', 'product_title']);
    }

    private function simpleNextStep(array $row, string $problem): string
    {
        $action = (string) ($row['action'] ?? $row['recommended_action'] ?? '');
        $code = trim(mb_strtolower($action . ' ' . $problem));

        if (str_contains($code, 'cost_above_retail') || str_contains($problem, 'Закупка') && str_contains($problem, 'выше')) {
            return 'Проверить цену: закупка выше розницы';
        }

        if (str_contains($code, 'manual') || str_contains($code, 'ручн')) {
            return 'Проверить сопоставление';
        }

        if (str_contains($code, 'created') || str_contains($code, 'создан')) {
            return 'Новый товар создан';
        }

        if (str_contains($code, 'matched') || str_contains($code, 'updated') || str_contains($code, 'обнов')) {
            return 'Сопоставлено, можно контролировать цены';
        }

        if (str_contains($code, 'not_found') || str_contains($code, 'не найден')) {
            return 'Нужно найти товар или оставить без связи';
        }

        return $this->translateCode($action) ?: 'Проверить строку';
    }

    private function marginText(string $cost, string $retail): string
    {
        $costValue = $this->parseReportMoney($cost);
        $retailValue = $this->parseReportMoney($retail);

        if ($costValue === null || $retailValue === null) {
            return '';
        }

        $margin = $retailValue - $costValue;
        $percent = $retailValue > 0 ? ($margin / $retailValue) * 100 : 0;
        $text = number_format($margin, 2, ',', ' ') . ' BYN / ' . number_format($percent, 1, ',', ' ') . '%';

        if ($margin < 0) {
            return 'Проблема: ' . $text;
        }

        if (abs($margin) < 0.01) {
            return 'Маржи нет: ' . $text;
        }

        return $text;
    }

    private function parseReportMoney(string $value): ?float
    {
        $value = trim(str_replace(["\xc2\xa0", 'BYN', 'byn', ' '], '', $value));
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.\-]/', '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function enrichRowsWithProductSkus(array $rows): array
    {
        $missingIds = [];
        foreach ($rows as $row) {
            if ($this->kotlovSku($row) !== '') {
                continue;
            }

            $productId = $this->productId($row);
            if ($productId !== '' && ctype_digit($productId)) {
                $missingIds[] = (int) $productId;
            }
        }

        $skus = $missingIds !== []
            ? DB::table('products')->whereIn('id', array_values(array_unique($missingIds)))->pluck('sku', 'id')->all()
            : [];

        foreach ($rows as $index => $row) {
            $sku = $this->kotlovSku($row);
            if ($sku === '') {
                $productId = $this->productId($row);
                $sku = $productId !== '' ? (string) ($skus[(int) $productId] ?? '') : '';
            }

            $rows[$index] = ['kotlov_sku' => $sku] + $row;
        }

        return $rows;
    }

    private function productId(array $row): string
    {
        return trim((string) ($row['product_id'] ?? $row['possible_product_id'] ?? $row['matched_product_id'] ?? ''));
    }

    private function supplierProductId(array $row): string
    {
        return trim((string) ($row['supplier_product_id'] ?? $row['possible_supplier_product_id'] ?? $row['matched_supplier_product_id'] ?? ''));
    }

    private function reportRow(array $row, int $rowIndex): string
    {
        foreach (['price_row', 'report_row', 'row', 'line', 'page'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return (string) ($rowIndex + 1);
    }

    private function supplierTitle(array $row): ?string
    {
        foreach (['possible_supplier_title', 'supplier_title', 'supplier_name', 'price_title', 'title'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function supplierArticle(array $row): ?string
    {
        foreach (['price_article', 'supplier_sku', 'supplier_article', 'article'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function decisionLabel(string $decision): string
    {
        return match ($decision) {
            SupplierReviewDecision::DECISION_LINK => 'Связать товар поставщика с товаром KOTLOV',
            SupplierReviewDecision::DECISION_UNLINK => 'Удалить связь товара поставщика',
            SupplierReviewDecision::DECISION_IGNORE => 'Отметить строку как проверенную',
            default => $decision,
        };
    }

    private function decisionStatusLabel(string $status): string
    {
        return match ($status) {
            SupplierReviewDecision::STATUS_PENDING => 'ожидает применения',
            SupplierReviewDecision::STATUS_APPLIED => 'применено',
            SupplierReviewDecision::STATUS_FAILED => 'ошибка',
            default => $status,
        };
    }

    private function allReports(): array
    {
        $root = $this->reportsRoot();
        if (! is_dir($root)) {
            return [];
        }

        $files = glob($root . DIRECTORY_SEPARATOR . '{*,*/*}.csv', GLOB_BRACE) ?: [];
        $reports = [];

        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }

            $relative = str_replace('\\', '/', Str::after($path, $root . DIRECTORY_SEPARATOR));
            $supplier = str_contains($relative, '/') ? Str::before($relative, '/') : 'general';
            $fileName = basename($path);

            $reports[] = [
                'absolute_path' => $path,
                'relative_path' => $relative,
                'supplier' => $supplier,
                'type' => $this->reportType($fileName),
                'file_name' => $fileName,
                'size' => filesize($path) ?: 0,
                'modified_at' => filemtime($path) ?: 0,
                'attention_count' => $this->attentionCount($path),
            ];
        }

        usort($reports, fn (array $left, array $right): int => $right['modified_at'] <=> $left['modified_at']);

        return $reports;
    }

    private function reportsRoot(): string
    {
        return storage_path('app/reports');
    }

    private function reportType(string $fileName): string
    {
        return match (true) {
            str_contains($fileName, 'manual-review') => 'manual-review',
            str_contains($fileName, 'ai-review') => 'ai-review',
            str_contains($fileName, 'archive') => 'archive',
            str_contains($fileName, 'audit') => 'audit',
            str_contains($fileName, 'price-list') => 'price-list',
            str_contains($fileName, 'sync') => 'sync',
            str_contains($fileName, 'import') => 'import',
            default => 'report',
        };
    }

    private function attentionCount(string $path): int
    {
        $count = 0;
        foreach ($this->readCsv($path, 500) as $row) {
            $action = mb_strtolower((string) ($row['action'] ?? $row['recommended_action'] ?? ''));
            $note = mb_strtolower((string) ($row['note'] ?? $row['reason'] ?? ''));

            if (
                str_contains($action, 'manual')
                || str_contains($action, 'error')
                || str_contains($action, 'cost_above_retail')
                || str_contains($action, 'keep_manual_review')
                || str_contains($note, 'manual')
                || str_contains($note, 'check')
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function readCsv(string $path, int $limit = 1000): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            return [];
        }

        $headers = array_map(
            fn ($header): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
            $headers
        );

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (! is_array($values)) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $values[$index] ?? '';
            }
            $rows[] = $row;

            if (count($rows) >= $limit) {
                break;
            }
        }

        fclose($handle);

        return $rows;
    }
}
