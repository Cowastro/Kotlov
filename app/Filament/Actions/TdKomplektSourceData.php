<?php

namespace App\Filament\Actions;

use App\Models\SupplierProduct;
use Filament\Actions\Action;

class TdKomplektSourceData
{
    private const LABELS = [
        'id' => 'ID предложения', 'okdp' => 'Код каталога', 'vendor_code' => 'Артикул производителя',
        'name' => 'Название', 'brand' => 'Бренд', 'barcode' => 'Штрихкод', 'barcode_old' => 'Предыдущий штрихкод',
        'price' => 'Розничная цена, BYN', 'price_wholesale' => 'Закупочная цена, BYN',
        'price_recommended' => 'Рекомендованная цена', 'price_recommended_713' => 'Рекомендованная цена 713',
        'warehouse_all' => 'Общее наличие / срок', 'warehouse_main' => 'Основной склад',
        'warehouse_add' => 'Дополнительный склад', 'warehouse_add2' => 'Дополнительный склад 2', 'warehouse_rb' => 'Склад РБ',
        'delivery' => 'Дата поставки в выгрузке', 'vat' => 'НДС', 'country' => 'Страна производства',
        'description_short' => 'Краткое описание', 'description' => 'Описание / характеристики',
        'opt_markup_kompl' => 'Наценка поставщика', 'prop_new' => 'Новинка',
        'prop_purpose' => 'Назначение', 'prop_warranty' => 'Гарантия (значение поставщика)',
        'prop_shelf_life' => 'Срок годности / хранения', 'prop_quantity_min' => 'Минимальный заказ',
        'prop_length' => 'Длина (значение поставщика)', 'prop_width' => 'Ширина (значение поставщика)',
        'prop_height' => 'Высота (значение поставщика)', 'prop_weight_gross' => 'Вес брутто (значение поставщика)',
        'prop_unit' => 'Единица продажи', 'prop_multiplicity' => 'Кратность заказа', 'prop_multiplicity_box' => 'Количество в коробке',
        'prop_tnved' => 'Код ТН ВЭД', 'prop_codecustom' => 'Таможенный код',
        'prop_manufacturer' => 'Производитель', 'prop_importer' => 'Импортёр',
        'prop_713' => 'Признак 713', 'prop_125' => 'Признак 125', 'prop_trace' => 'Признак прослеживаемости',
        'prop_control_mark' => 'Контрольная маркировка', 'prop_promo_price' => 'Акционная цена поставщика',
        'prop_promo_date_finish' => 'Окончание акции поставщика', 'media_img' => 'Фотография', 'media_video' => 'Видео',
    ];

    public static function make(): Action
    {
        return Action::make('tdKomplektSourceData')->label('Все данные ТД Комплект')
            ->icon('heroicon-o-document-text')
            ->visible(fn (SupplierProduct $record): bool => $record->supplier?->code === 'td_komplekt')
            ->modalHeading('Данные выгрузки ТД Комплект')
            ->modalContent(fn (SupplierProduct $record) => view('filament.td-komplekt-source-data', ['record' => $record, 'fields' => self::fields($record->raw ?? [])]))
            ->modalSubmitAction(false)->modalCancelActionLabel('Закрыть')->slideOver();
    }

    public static function fields(array $raw): array
    {
        $result = [];
        foreach ($raw as $field => $value) {
            // Generated matching metadata is not a field of the supplier feed.
            if (! is_string($value) || str_starts_with($field, 'identity_') || str_starts_with($field, '_')) {
                continue;
            }
            $label = self::LABELS[$field] ?? null;
            if (! $label && preg_match('/^parentid(_name)?([1-4])$/', $field, $match)) {
                $label = ($match[1] ? 'Раздел уровня ' : 'ID раздела уровня ').$match[2];
            }
            if ($field === 'source') {
                continue;
            }
            $result[] = ['field' => $field, 'label' => $label ?? $field, 'value' => $value];
        }

        return $result;
    }
}
