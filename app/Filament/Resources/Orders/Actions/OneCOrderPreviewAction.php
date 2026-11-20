<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Services\Integrations\OneCOrderReadiness;
use App\Services\Integrations\OrderIntegrationRouter;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

/** «Что уйдёт в 1С»: the lines with their 1C codes, the counterparty and why the order may not go. Read-only. */
class OneCOrderPreviewAction
{
    public static function make(): Action
    {
        return Action::make('onecPreview')
            ->label('Что уйдёт в 1С')
            ->icon('heroicon-o-document-magnifying-glass')
            ->color('gray')
            ->visible(fn (Order $record): bool => self::source() !== null)
            ->modalHeading('Проверка заказа перед передачей в 1С')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрыть')
            ->modalContent(fn (Order $record): HtmlString => self::render($record));
    }

    private static function source(): ?IntegrationSource
    {
        return IntegrationSource::query()->where('code', 'onec')->where('is_active', true)->first();
    }

    private static function render(Order $order): HtmlString
    {
        $source = self::source();
        $result = app(OneCOrderReadiness::class)->check($order, $source);
        $items = app(OrderIntegrationRouter::class)->itemsForSource($order, $source);
        $codes = IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->whereIn('external_id', array_values($result['external_ids']))->pluck('external_code', 'external_id');
        $e = fn ($value): string => e((string) $value);

        $html = $result['problems'] === []
            ? '<p style="margin:0 0 12px;color:#15803d;font-weight:600">Заказ готов к передаче в 1С.</p>'
            : '<div style="margin:0 0 12px;padding:10px 12px;border-radius:8px;background:#fef2f2;color:#991b1b"><strong>Заказ не уйдёт в 1С:</strong><ul style="margin:6px 0 0;padding-left:18px">'
                .collect($result['problems'])->map(fn ($p) => '<li>'.$e($p).'</li>')->implode('').'</ul></div>';

        $partner = $result['partner'];
        $html .= '<p style="margin:0 0 8px"><strong>Контрагент в 1С:</strong> '.$e($partner['name'])
            .($partner['tax_id'] !== '' ? ' · УНП '.$e($partner['tax_id']) : '')
            .($partner['b2b'] ? '' : ' <span style="color:#6b7280">(покупатель — в реквизитах заказа)</span>').'</p>';

        $rows = $items->map(function ($item) use ($result, $codes, $e): string {
            $id = $result['external_ids'][$item->id] ?? null;
            $code = $id ? ($codes[$id] ?? 'ID '.$id) : '<span style="color:#b91c1c">не уйдёт</span>';

            return '<tr><td style="padding:4px 8px">'.$e($item->product_name).'</td><td style="padding:4px 8px;white-space:nowrap">'
                .($id ? $e($code) : $code).'</td><td style="padding:4px 8px;text-align:right">'.$e($item->quantity).'</td>'
                .'<td style="padding:4px 8px;text-align:right;white-space:nowrap">'.$e(number_format((float) $item->total, 2, ',', ' ')).'</td></tr>';
        })->implode('');
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="text-align:left;color:#6b7280">'
            .'<th style="padding:4px 8px">Позиция</th><th style="padding:4px 8px">Товар 1С</th><th style="padding:4px 8px;text-align:right">Кол-во</th><th style="padding:4px 8px;text-align:right">Сумма</th></tr></thead><tbody>'
            .($rows ?: '<tr><td colspan="4" style="padding:4px 8px;color:#6b7280">Нет позиций для 1С</td></tr>').'</tbody></table>';

        return new HtmlString($html);
    }
}
