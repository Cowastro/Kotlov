<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** tools.by CSV: never interpret a delivery lead time as stock. */
class TdKomplektFeed
{
    public const DIRECTORY = 'suppliers/td-komplekt';

    public function load(?string $file = null): array
    {
        if ($file) {
            return $this->parse($file);
        }
        $disk = Storage::disk('local');
        $config = json_decode($disk->get(self::DIRECTORY.'/config.json') ?? '', true);
        $url = (string) ($config['url'] ?? '');
        if (! preg_match('~^https://tools\.by/api/eshop-export/[A-Z0-9]{26}\?format=csv$~D', $url)) {
            throw new RuntimeException('Настройте приватный config.json ТД Комплект: требуется CSV tools.by.');
        }
        $snapshot = self::DIRECTORY.'/latest.csv';
        if ($disk->exists($snapshot) && $disk->lastModified($snapshot) > time() - 1800) {
            return $this->parse($disk->path($snapshot));
        }
        $attempt = self::DIRECTORY.'/last-attempt.txt';
        if ($disk->exists($attempt) && (int) $disk->get($attempt) > time() - 370) {
            throw new RuntimeException('ТД Комплект: повторный запрос разрешён через 7 минут.');
        }
        $disk->put($attempt, (string) time());
        try {
            $response = Http::connectTimeout(15)->timeout(120)->withOptions(['allow_redirects' => false])->get($url);
            if (! $response->successful()) {
                throw new RuntimeException('HTTP '.$response->status());
            }
            $disk->put(self::DIRECTORY.'/incoming.csv', $response->body());
            $rows = $this->parse($disk->path(self::DIRECTORY.'/incoming.csv'));
            if (count($rows) < 100) {
                throw new RuntimeException('Неполная выгрузка: менее 100 строк.');
            }
            if ($disk->exists($snapshot)) {
                $previous = $this->parse($disk->path($snapshot));
                if (count($rows) < count($previous) * .8) {
                    throw new RuntimeException('Выгрузка сократилась более чем на 20% — требуется проверка.');
                }
                $disk->copy($snapshot, self::DIRECTORY.'/previous.csv');
            }
            $disk->move(self::DIRECTORY.'/incoming.csv', $snapshot);

            return $rows;
        } catch (\Throwable $e) {
            // HTTP exceptions contain the credential-bearing URL. Never log it.
            throw new RuntimeException('Не удалось получить или проверить CSV ТД Комплект. Данные не обновлены.');
        }
    }

    public function parse(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('CSV не найден.');
        }
        try {
            $header = fgetcsv($handle, 0, ';', '"', '');
            if (! is_array($header)) {
                throw new RuntimeException('CSV пуст.');
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            foreach (['id', 'okdp', 'name', 'vendor_code', 'brand', 'price', 'price_wholesale', 'warehouse_all', 'media_img'] as $column) {
                if (! in_array($column, $header, true)) {
                    throw new RuntimeException('CSV: отсутствует колонка '.$column);
                }
            }
            $rows = [];
            while (($cells = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                if ($cells === [null]) {
                    continue;
                }
                if (count($cells) !== count($header)) {
                    throw new RuntimeException('Нарушена структура CSV.');
                }
                $raw = array_combine($header, array_map(fn ($v) => trim((string) $v), $cells));
                if (! mb_check_encoding(implode('', $raw), 'UTF-8') || ! ctype_digit($raw['id']) || $raw['name'] === '' || isset($rows[$raw['id']])) {
                    throw new RuntimeException('Некорректная или повторная строка CSV.');
                }
                $retail = self::number($raw['price']);
                $cost = self::number($raw['price_wholesale']);
                if ($retail === null || $cost === null) {
                    throw new RuntimeException('Некорректная цена CSV.');
                }
                $stock = $this->stock($raw['warehouse_all']);
                $rows[$raw['id']] = $raw + [];
                $rows[$raw['id']]['_data'] = ['retail' => $retail, 'wholesale' => $cost] + $stock;
            }
            if ($rows === []) {
                throw new RuntimeException('CSV не содержит товаров.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    public static function number(string $value): ?float
    {
        $value = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], trim($value));

        return preg_match('/^\d+(?:\.\d+)?$/D', $value) ? round((float) $value, 4) : null;
    }

    public function stock(string $value): array
    {
        if (preg_match('/^\d+$/D', $value)) {
            return ['quantity' => (int) $value, 'in_stock' => (int) $value > 0, 'stock_status' => (int) $value > 0 ? 'in_stock' : 'out_of_stock', 'delivery_days' => null];
        }
        if (preg_match('/^(\d+)\s*(?:дней|дня|день)$/uD', $value, $m)) {
            return ['quantity' => null, 'in_stock' => false, 'stock_status' => 'preorder', 'delivery_days' => (int) $m[1]];
        }

        return ['quantity' => null, 'in_stock' => false, 'stock_status' => 'unknown', 'delivery_days' => null];
    }

    public static function key(string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', trim($value)));
    }

    public static function image(string $value): ?string
    {
        return preg_match('~^https://content\.tools\.by/images/products/[^\s<>"\x00]+$~D', $value) ? $value : null;
    }

    public function category(array $row): ?string
    {
        if (TdKomplektPumpCategories::applies($row)) {
            return TdKomplektPumpCategories::category($row);
        }
        $group = mb_strtolower(($row['parentid_name3'] ?? '').' '.($row['parentid_name4'] ?? ''));
        $name = mb_strtolower($row['name']);
        if (str_contains($group, 'распродажа')) {
            return null;
        }
        if (preg_match('/^клапан обратн/u', $name)) {
            return 'obratnye-klapany';
        }
        if (preg_match('/^вентиль\b/u', $name)) {
            return 'ventili-zapornye';
        }
        if (preg_match('/^кран (?:шар|шаровой)/u', $name)) {
            return 'sharovye-krany';
        }
        if (preg_match('/^(?:клипса|опора|хомут)\b/u', $name)) {
            return 'krepleniya-dlya-trub';
        }
        if (str_contains($group, 'резьбовые') && ! str_contains($group, 'pex') && ! str_contains($group, 'полотенцесуш')) {
            if (preg_match('/^(?:ниппель|тройник|угол|угольник|муфта|переход|футорка|контргайка|заглушка|пробка|сгон|бочонок|штуцер|крестовина|удлинитель|соединител|американка|резьба)/u', $name)) {
                return 'rezbovye-fitingi';
            }

            return null;
        }
        if (str_contains($group, 'полипропиленовый')) {
            return preg_match('/^труб[аы]\b/u', $name) ? 'polipropilenovye-truby' : 'fitingi-dlya-polipropilenovykh-trub';
        }
        if (str_contains($group, 'пнд водопровод')) {
            return preg_match('/^труб[аы]\b/u', $name) ? 'polietilenovye-truby' : 'fitingi-dlya-pe-trub';
        }
        if (str_contains($group, 'резьбовые') && str_contains($group, 'pex')) {
            return 'fitingi-dlya-trub-pex';
        }
        if (str_contains($group, 'смесители ') && preg_match('/^смеситель\b/u', $name)) {
            return 'smesiteli';
        }
        if (str_contains($group, 'смесители ') && ! preg_match('/^смеситель\b/u', $name)) {
            return null;
        }
        if (str_contains($group, 'подводка гибкая')) {
            return 'gibkaya-podvodka-dlya-vody';
        }
        if (str_contains($group, 'внутренней канализации')) {
            return 'vnutrennyaya-kanalizatsiya';
        }
        if (str_contains($group, 'наружной канализации')) {
            return 'naruzhnaya-kanalizatsiya';
        }
        if (str_contains($group, 'коллекторные шкафы')) {
            return 'kollektornye-shkafy';
        }
        if (str_contains($group, 'радиаторы стальные')) {
            return 'stalnye-radiatory';
        }
        if (str_contains($group, 'радиаторы: комплектующие')) {
            return 'radiatornaya-armatura';
        }
        if (str_contains($group, 'радиаторы алюминиевые') && str_contains($name, 'биметалл')) {
            return 'bimetallicheskie-radiatory';
        }
        if (str_contains($group, 'радиаторы алюминиевые') && str_contains($name, 'алюмин')) {
            return 'alyuminievye-radiatory';
        }
        if (str_contains($group, 'крепления для труб')) {
            return 'krepleniya-dlya-trub';
        }
        if (str_contains($group, 'теплоизоляция')) {
            return 'teploizolyatsiya-dlya-trub';
        }
        if (str_contains($group, 'теплоноситель')) {
            return 'teplonositeli';
        }
        if (str_contains($group, 'прокладки') || str_contains($group, 'манжеты переходные')) {
            return 'uplotnitelnye-materialy';
        }
        if (str_contains($group, 'расходные материалы') && preg_match('/^(?:фум|нить|лён|лен\b|пакля)/u', $name)) {
            return 'uplotnitelnye-materialy';
        }
        if (str_contains($group, 'трубки защитные')) {
            return 'truby-zashchitnye';
        }
        if (str_contains($group, 'люки садовые') || str_contains($group, 'система водоотведения ecoteck')) {
            return 'lyuki-i-dozhdepriemniki';
        }
        if (str_contains($group, 'мойки из искусственного') || str_contains($group, 'унитазы и биде')) {
            return 'santekhnicheskaya-posuda';
        }
        if (str_contains($group, 'инсталляции')) {
            return 'installyatsii-i-armatura-bachkov';
        }
        if (str_contains($group, 'водоотводящие желоба') || str_contains($group, 'сливные трапы')) {
            return 'sifony-i-trapy';
        }
        if (str_contains($group, 'водосливная') && preg_match('/^(?:арматура|механизм|кнопка|инсталляция|бачок)\b/u', $name)) {
            return 'installyatsii-i-armatura-bachkov';
        }
        if (str_contains($group, 'водосливная') && preg_match('/^(?:сифон|трап)\b/u', $name)) {
            return 'sifony-i-trapy';
        }
        if (str_contains($group, 'водосливная')) {
            return 'komplektuyushchie-dlya-santekhniki';
        }
        if (str_contains($group, 'картриджи для очистки') || str_contains($group, 'колбы для картриджей')) {
            return 'filtry';
        }
        if (str_contains($group, 'коллекторные группы') && ! str_contains($group, 'комплектующие')) {
            return 'gidravlicheskie-razdeliteli-i-kollektory';
        }
        if (str_contains($group, 'шланги дренажные')) {
            return 'komplektuyushchie-dlya-nasosov';
        }
        if (str_contains($group, 'резьбовые латунные фитинги для полотенцесушителей')) {
            return 'rezbovye-fitingi';
        }
        if (str_contains(mb_strtolower($row['parentid_name2'] ?? ''), 'водонагреватели') && preg_match('/^водонагреватель накопительный\b/u', $name)) {
            return 'electric';
        }
        if (str_contains($group, 'запчасти для смесителей') || str_contains($group, 'аксессуары для смесителей') || str_contains($group, 'комплектующие для')) {
            return 'komplektuyushchie-dlya-santekhniki';
        }
        if (str_contains($group, 'инструмент для') || str_contains($group, 'аппараты для сварки')) {
            return 'instrumenty-dlya-montazha';
        }

        return null;
    }

    public function specs(array $row, int $version = 2): array
    {
        $specs = ['Артикул производителя' => $row['vendor_code'], 'Штрихкод' => $row['barcode'] ?? '', 'Бренд' => $row['brand'], 'Страна производства' => $row['country'] ?? ''];
        $description = $row['description'] ?? '';
        if ($version >= 2) {
            $description = preg_replace('/<br\s*\/?\s*>/iu', "\n", $description);
        }
        $pattern = $version >= 2 ? '/^\s*([^:]{2,80}?)(?::|\s+[-–—]\s+)\s*(.{1,300})$/u' : '/^\s*([^:]{2,80}):\s*(.{1,300})$/u';
        foreach (preg_split('/\r?\n/u', strip_tags($description)) ?: [] as $line) {
            if (preg_match($pattern, trim($line), $m)) {
                $specs[trim($m[1])] = rtrim(trim($m[2]), ',');
            }
        }
        // Supplier groups contain counterexamples (steel taps in the brass group).
        // A specific product's own description wins over all category labels.
        if (! isset($specs['Материал'])) {
            $name = mb_strtolower($row['name']);
            if (str_contains($name, 'чугун')) {
                $specs['Материал'] = 'Чугун';
            } elseif (str_contains($name, 'латун')) {
                $specs['Материал'] = 'Латунь';
            }
        }

        return collect($specs)->filter(fn ($v) => $v !== '')->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all();
    }

    /** warehouse_all remains the total: never add these breakdowns to it. */
    public function fulfillmentDetails(array $row): array
    {
        $warehouses = [];
        foreach (['warehouse_main' => 'Основной склад', 'warehouse_add' => 'Дополнительный склад', 'warehouse_add2' => 'Дополнительный склад 2', 'warehouse_rb' => 'Склад РБ'] as $field => $label) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $warehouses[] = ['code' => $field, 'name' => $label, 'text' => $value] + $this->stock($value);
            }
        }
        $date = trim((string) ($row['delivery'] ?? ''));
        $validDate = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);

        $total = $this->stock($row['warehouse_all'] ?? '')['quantity'];
        $known = array_column($warehouses, 'quantity');
        $sum = array_sum($known);
        $conflict = $total !== null && $warehouses !== []
            && ($sum > $total || (! in_array(null, $known, true) && $sum !== $total));

        return ['warehouses' => $warehouses, 'delivery_date' => $validDate ? $date : null,
            'unit' => trim($row['prop_unit'] ?? '') ?: 'шт.', 'quantity_conflict' => $conflict];
    }

    public static function video(string $url): ?string
    {
        return preg_match('~^https://(?:www\.)?youtube\.com/embed/[A-Za-z0-9_-]{6,}(?:\?[^\s<>"\x00]*)?$~D', $url) ? $url : null;
    }

    public function catalogFields(array $row, int $version = 3): array
    {
        $service = [];
        foreach (['Минимальный заказ' => 'prop_quantity_min', 'Кратность заказа' => 'prop_multiplicity', 'В коробке' => 'prop_multiplicity_box', 'Единица продажи' => 'prop_unit', 'Производитель' => 'prop_manufacturer', 'Импортёр' => 'prop_importer'] as $key => $field) {
            if (! empty($row[$field])) {
                $service[] = ['key' => $key, 'value' => $row[$field]];
            }
        }
        if ($version >= 3) {
            foreach (['Назначение' => 'prop_purpose', 'Срок годности / хранения' => 'prop_shelf_life', 'Гарантия' => 'prop_warranty'] as $key => $field) {
                $value = trim((string) ($row[$field] ?? ''));
                // Numeric warranty units are unspecified in CSV; keep them in the manager's source data.
                if ($value !== '' && ($field !== 'prop_warranty' || preg_match('/[\pL]/u', $value))) {
                    $service[] = ['key' => $key, 'value' => $value];
                }
            }
        }
        $description = $row['description'] ?? '';
        if ($version >= 3) {
            $description = preg_replace('/<br\s*\/?\s*>/iu', "\n", $description);
        }
        $description = trim(strip_tags($description));

        $fields = ['images' => array_values(array_filter([self::image($row['media_img'] ?? '')])), 'specs' => $this->specs($row, $version), 'service_info' => $service,
            'content' => $description !== '' ? '<p>'.nl2br(e($description)).'</p>' : null, 'short_description' => Str::limit(strip_tags($row['description_short'] ?? ''), 300, ''), 'unit' => ($row['prop_unit'] ?? '') ?: 'шт.'];
        if ($version >= 3) {
            $fields['video_url'] = self::video($row['media_video'] ?? '');
        }

        return $fields;
    }
}
