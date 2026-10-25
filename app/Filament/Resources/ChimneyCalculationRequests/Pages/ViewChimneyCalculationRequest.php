<?php

namespace App\Filament\Resources\ChimneyCalculationRequests\Pages;

use App\Filament\Resources\ChimneyCalculationRequests\ChimneyCalculationRequestResource;
use App\Models\Product;
use App\Services\ChimneyCalculationRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewChimneyCalculationRequest extends ViewRecord
{
    protected static string $resource = ChimneyCalculationRequestResource::class;

    public function getTitle(): string
    {
        return 'Расчёт '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addItem')
                ->label('Добавить элемент')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->schema([
                    Select::make('product_id')
                        ->label('Элемент «Теплов и Сухов»')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => $this->searchChimneyProducts($search))
                        ->getOptionLabelUsing(fn ($value): ?string => $this->productLabel((int) $value))
                        ->required(),
                    TextInput::make('quantity')->label('Количество')->numeric()->minValue(1)->maxValue(99)->default(1)->required(),
                ])
                ->action(function (array $data): void {
                    $items = $this->record->items ?? [];
                    $productId = (int) $data['product_id'];
                    $found = false;
                    foreach ($items as &$item) {
                        if ((int) ($item['product_id'] ?? 0) !== $productId) {
                            continue;
                        }
                        $item['quantity'] = min(99, (int) ($item['quantity'] ?? 1) + (int) $data['quantity']);
                        $found = true;
                        break;
                    }
                    unset($item);
                    if (! $found) {
                        $items[] = ['product_id' => $productId, 'quantity' => (int) $data['quantity']];
                    }
                    $this->saveItems($items, 'Элемент добавлен в расчёт');
                }),
            Action::make('confirm')
                ->label('Подтвердить и создать заказ')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->action(function (ChimneyCalculationRequestService $service): void {
                    $user = auth()->user();
                    $result = $service->confirm($this->record, $user?->name ?? 'Менеджер CRM', $user);
                    $this->record->refresh();
                    Notification::make()->title('Заказ '.$result['order']->number.' создан')->success()->send();
                }),
            Action::make('openOrder')
                ->label('Открыть заказ')
                ->icon('heroicon-o-shopping-cart')
                ->visible(fn (): bool => (bool) $this->record->order_id)
                ->url(fn (): string => url('/admin/orders/'.$this->record->order_id)),
        ];
    }

    public function changeItemQuantity(int $index, int $delta): void
    {
        $items = $this->record->items ?? [];
        if (! isset($items[$index]) || $this->record->status !== 'pending') {
            return;
        }

        $items[$index]['quantity'] = max(1, min(99, (int) ($items[$index]['quantity'] ?? 1) + $delta));
        $this->saveItems($items, 'Количество обновлено');
    }

    public function removeItem(int $index): void
    {
        $items = $this->record->items ?? [];
        if (! isset($items[$index]) || count($items) <= 1 || $this->record->status !== 'pending') {
            return;
        }

        array_splice($items, $index, 1);
        $this->saveItems($items, 'Позиция удалена из расчёта');
    }

    private function saveItems(array $items, string $message): void
    {
        $this->record = app(ChimneyCalculationRequestService::class)->revise($this->record, $items);
        $this->refreshFormData(['items', 'subtotal', 'discount_percent', 'discount', 'total', 'promotion_mode']);
        Notification::make()->title($message)->success()->send();
    }

    private function searchChimneyProducts(string $search): array
    {
        return Product::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(function ($query): void {
                $query->where('name', 'like', '%Теплов%Сухов%')
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', '%Теплов%Сухов%'));
            })
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [$product->id => $this->formatProductLabel($product)])
            ->all();
    }

    private function productLabel(int $id): ?string
    {
        $product = Product::find($id);

        return $product ? $this->formatProductLabel($product) : null;
    }

    private function formatProductLabel(Product $product): string
    {
        return $product->name.' · '.$product->sku.' · '.number_format((float) $product->price, 2, ',', ' ').' BYN';
    }
}
