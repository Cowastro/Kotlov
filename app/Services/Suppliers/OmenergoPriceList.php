<?php

namespace App\Services\Suppliers;

use RuntimeException;

class OmenergoPriceList
{
    public const SHEET_URL = 'https://docs.google.com/spreadsheets/d/1drIsDi5VSX4NCZgc4XiCjf95XkQcZw7jRIufriq0rSA/edit?gid=1161827566#gid=1161827566';

    public const CSV_URL = 'https://docs.google.com/spreadsheets/d/1drIsDi5VSX4NCZgc4XiCjf95XkQcZw7jRIufriq0rSA/export?format=csv&gid=1161827566';

    public function parse(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);
        $header = fgetcsv($stream, 0, ',', '"', '');
        if (! is_array($header) || array_slice($header, 0, 3) !== ['Артикул', 'Категория', 'Наименование каталожное']
            || ($header[10] ?? '') !== 'Наличие' || ($header[11] ?? '') !== 'МРЦ' || ($header[12] ?? '') !== 'Д2') {
            throw new RuntimeException('Omenergo: unexpected price list headers; nothing may be applied.');
        }
        $result = [];
        $seen = [];
        $line = 1;
        while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if (count($cells) < 13) {
                throw new RuntimeException("Omenergo: truncated row {$line}.");
            }
            $cells = array_map(fn ($v) => trim(str_replace("\xc2\xa0", ' ', (string) $v)), $cells);
            $article = $this->articleKey($cells[0]);
            if ($article === '' || isset($seen[$article]) || $cells[2] === '' || ! in_array($cells[4], ['Энергия', 'Энерготех'], true)) {
                throw new RuntimeException("Omenergo: missing/duplicate article or unsupported brand in row {$line}.");
            }
            $seen[$article] = true;
            $stock = $this->stock($cells[10]);
            $retail = $this->money($cells[11]);
            $dealer = $this->money($cells[12]);
            if ($retail === null || $dealer === null || $dealer > $retail) {
                throw new RuntimeException("Omenergo: invalid MRC/D2 in row {$line}.");
            }
            $description = $cells[8];
            $conflict = false;
            // The supplied ACH 10000 line says 8000 VA in its description.
            if (preg_match('/(?:ACH|АСН)\s*[- ]?(\d+)/iu', $cells[2], $model)
                && preg_match('/(\d+)\s*ВА/iu', $description, $power)
                && (int) $model[1] !== (int) $power[1]) {
                $conflict = true;
                $description = preg_replace('/\d+\s*ВА\s*[,.;]?/iu', '', $description);
            }
            $result[] = [
                'sheet_row' => $line, 'article' => $cells[0], 'article_key' => $article,
                'category' => $cells[1], 'name' => $cells[2], 'invoice_name' => $cells[3],
                'brand' => $cells[4], 'country' => $cells[5], 'warranty_months' => (int) $cells[6],
                'weight_kg' => is_numeric(str_replace(' ', '', $cells[7])) ? (float) str_replace(' ', '', $cells[7]) / 1000 : null,
                'description' => $description, 'kit' => $cells[9], 'stock_text' => $cells[10],
                'retail' => $retail, 'dealer' => $dealer, 'technical_conflict' => $conflict,
            ] + $stock;
        }
        fclose($stream);
        if ($result === []) {
            throw new RuntimeException('Omenergo: empty price list.');
        }

        return $result;
    }

    public function stock(string $value): array
    {
        return match (trim($value)) {
            '*' => ['in_stock' => false, 'quantity' => null, 'stock_status' => 'check'],
            '**', '***' => ['in_stock' => true, 'quantity' => null, 'stock_status' => 'available_without_quantity'],
            'Нет в наличии' => ['in_stock' => false, 'quantity' => 0, 'stock_status' => 'out_of_stock'],
            'Под заказ' => ['in_stock' => false, 'quantity' => null, 'stock_status' => 'on_order'],
            default => throw new RuntimeException('Omenergo: unknown stock marker.'),
        };
    }

    public function money(string $value): ?float
    {
        $value = str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], trim($value));

        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    public function articleKey(string $value): string
    {
        return str_replace('Е', 'E', mb_strtoupper(preg_replace('/\s+/u', '', trim($value))));
    }

    public function nameKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/\b(?:стабилизатор|напряжения|сетевой|фильтр|со|с|цифровым|дисплеем)\b/iu', ' ', $value);
        $value = strtr($value, ['асн' => 'ach', 'ё' => 'е']);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }

    public function productName(array $row): string
    {
        if (str_contains(mb_strtolower($row['category']), 'стабилизаторы напряжения')
            && ! str_contains(mb_strtolower($row['name']), 'стабилизатор')) {
            return 'Стабилизатор напряжения '.$row['name'];
        }

        return $row['name'];
    }

    public function specs(array $row): array
    {
        $specs = [
            ['key' => 'Торговая марка', 'value' => $row['brand']],
            ['key' => 'Страна производства', 'value' => $row['country']],
            ['key' => 'Гарантия', 'value' => $row['warranty_months'].' мес.'],
            ['key' => 'Комплектация', 'value' => $row['kit']],
        ];
        if ($row['weight_kg'] !== null) {
            $specs[] = ['key' => 'Вес', 'value' => (string) $row['weight_kg'], 'unit' => 'кг'];
        }
        if ($row['description'] !== '') {
            $specs[] = ['key' => 'Технические особенности', 'value' => $row['description']];
        }

        return $specs;
    }

    public function content(array $row): string
    {
        $html = '<h2>'.e($this->productName($row)).'</h2><p>'.e($row['description']).'</p>';
        $html .= '<h3>Комплект поставки</h3><p>'.e($row['kit']).'</p>';
        $html .= '<h3>Гарантия и происхождение</h3><p>Страна производства: '.e($row['country']).'. Гарантия: '.$row['warranty_months'].' мес.</p>';
        if (str_contains(mb_strtolower($row['category']), 'стабилизаторы напряжения')) {
            $html .= '<h3>Что проверить перед покупкой</h3><ul><li>Фазность сети и способ подключения нагрузки.</li><li>Рабочий диапазон входного напряжения и требования оборудования к точности.</li><li>Полную мощность в ВА, активную мощность в Вт и пусковые токи подключаемой техники: эти величины нельзя считать взаимозаменяемыми.</li><li>Условия установки и допустимую температуру по руководству конкретной модели.</li></ul>';
        }
        $html .= '<p>Поможем проверить совместимость перед заказом. Наличие и срок поставки подтверждаются менеджером.</p>';

        return $html;
    }
}
