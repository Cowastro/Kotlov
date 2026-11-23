<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Orders\OrderItemAnalogReplacer;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\ValidationException;

final class FindOrderItemAnalogAction
{
    public static function make(): Action
    {
        return self::build(false);
    }

    public static function forItem(): Action
    {
        return self::build(true)->iconButton()->tooltip('Заменить позицию');
    }

    public static function allowed(Order $order): bool
    {
        return auth()->user()?->is_active && auth()->user()?->isManager() && OrderResource::canEdit($order)
            && in_array($order->status, ['new', 'checking'], true) && $order->archived_at === null && $order->payment_status !== 'paid';
    }

    private static function build(bool $itemScope): Action
    {
        return Action::make($itemScope ? 'replaceOrderItem' : 'findAnalog')
            ->label('Заменить позицию')->icon('heroicon-o-arrows-right-left')->color('warning')->button()
            ->visible(fn ($record): bool => self::allowed(self::order($record)))
            ->modalHeading('Заменить товар в заказе')->modalWidth('3xl')
            ->modalDescription('Количество сохранится. Проверьте совместимость и согласуйте цену с клиентом. Наличие и поставщик новой позиции проверяются заново.')
            ->schema([
                Select::make('order_item_id')->label('Позиция заказа')->hidden($itemScope)->dehydrated()->dehydratedWhenHidden()
                    ->options(fn ($record): array => self::order($record)->items->mapWithKeys(fn ($item) => [$item->id => $item->product_name.' · '.$item->quantity.' шт.'])->all())
                    ->default(fn ($record): ?int => $record instanceof OrderItem ? $record->id : $record->items->first()?->id)
                    ->live()->afterStateUpdated(function ($state, $record, Set $set): void {
                        $set('replacement_product_id', null);
                        $set('expected_product_id', self::item($record, (int) $state)?->product_id);
                    })->required(),
                Hidden::make('expected_product_id')->default(fn ($record) => $record instanceof OrderItem ? $record->product_id : $record->items->first()?->product_id),
                Select::make('replacement_product_id')->label('Новый товар')->placeholder('Название, наш или заводской артикул')
                    ->options(fn ($record, Get $get): array => self::options($record, (int) $get('order_item_id'), ''))
                    ->getSearchResultsUsing(fn (string $search, $record, Get $get): array => self::options($record, (int) $get('order_item_id'), $search))
                    ->getOptionLabelUsing(fn ($value): ?string => ($product = Product::withSupplierChannels()->find($value)) ? OrderForm::productOptionLabel($product) : null)
                    ->searchable()->live()->required()->helperText('Сначала товары той же категории. Поиск — по всему каталогу.'),
                Radio::make('price_policy')->label('Цена для клиента')->options(['catalog' => 'Цена новой карточки', 'keep' => 'Сохранить согласованную цену заказа'])->default('catalog')->required()->live(),
                Placeholder::make('replacement_preview')->label('До и после')->content(function ($record, Get $get): string {
                    $item = self::item($record, (int) $get('order_item_id'));
                    $product = Product::find($get('replacement_product_id'));
                    if (! $item) {
                        return 'Выберите позицию заказа.';
                    }
                    $old = (float) $item->price;
                    if (! $product) {
                        return $item->product_sku.' · '.$item->quantity.' шт. × '.number_format($old, 2, ',', ' ').' BYN. Выберите новый товар.';
                    }
                    $new = $get('price_policy') === 'keep' ? $old : (float) $product->price;
                    $delta = ($new - $old) * $item->quantity;

                    return $item->product_sku.' → '.$product->sku.' · '.$item->quantity.' шт. Цена: '.number_format($old, 2, ',', ' ').' → '.number_format($new, 2, ',', ' ')
                        .' BYN. Сумма позиции: '.number_format($old * $item->quantity, 2, ',', ' ').' → '.number_format($new * $item->quantity, 2, ',', ' ')
                        .' BYN. Изменение заказа: '.($delta > 0 ? '+' : '').number_format($delta, 2, ',', ' ').' BYN.';
                }),
                Textarea::make('note')->label('Причина замены / что согласовали с клиентом')->maxLength(500)->rows(2),
            ])->modalSubmitActionLabel('Заменить и пересчитать')
            ->action(function ($record, array $data, $livewire): void {
                try {
                    $result = app(OrderItemAnalogReplacer::class)->replace(self::order($record), (int) $data['order_item_id'], (int) $data['replacement_product_id'],
                        auth()->user(), ($data['price_policy'] ?? 'catalog') === 'keep', $data['note'] ?? null, isset($data['expected_product_id']) ? (int) $data['expected_product_id'] : null);
                } catch (DomainException $e) {
                    throw ValidationException::withMessages(['replacement_product_id' => $e->getMessage()]);
                }
                $record->refresh();
                if (method_exists($livewire, 'getRecord')) {
                    $livewire->getRecord()->refresh();
                }
                Notification::make()->success()->title('Позиция заменена')->body('«'.$result['old_name'].'» → «'.$result['new_name'].'». Проверьте наличие и закупочную цену новой позиции.')->send();
            });
    }

    private static function order(Order|OrderItem $record): Order
    {
        return $record instanceof OrderItem ? $record->order : $record;
    }

    private static function item(Order|OrderItem $record, int $itemId): ?OrderItem
    {
        return self::order($record)->items->firstWhere('id', $itemId);
    }

    private static function options(Order|OrderItem $record, int $itemId, string $search): array
    {
        $item = self::item($record, $itemId);
        if (! $item) {
            return [];
        }
        $options = OrderForm::searchProductOptions($search, categoryId: trim($search) === '' ? $item->product?->category_id : null);
        unset($options[$item->product_id]);

        return $options;
    }
}
