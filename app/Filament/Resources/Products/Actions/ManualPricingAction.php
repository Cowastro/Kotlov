<?php

namespace App\Filament\Resources\Products\Actions;

use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

final class ManualPricingAction
{
    public static function make(): Action
    {
        return Action::make('manual_prices')
            ->label('Задать цены вручную')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (Product $record): bool => self::isAvailableFor($record))
            ->fillForm(fn (Product $record): array => [
                'manual_purchase_price_byn' => $record->manual_purchase_price_byn,
                'retail_price' => $record->price,
            ])
            ->form([
                TextInput::make('manual_purchase_price_byn')
                    ->label('Закупочная цена вручную')
                    ->helperText('Цена закупки в BYN. Поставщик при этом не создаётся.')
                    ->prefix('BYN')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),
                TextInput::make('retail_price')
                    ->label('Цена на сайте')
                    ->prefix('BYN')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),
            ])
            ->modalHeading(fn (Product $record): string => 'Ручные цены · '.$record->name)
            ->modalDescription('Используйте для товара без поставщика и канала импорта. Цена закупки будет участвовать в расчёте маржи и коммерческих предложений.')
            ->modalSubmitActionLabel('Сохранить цены')
            ->action(function (Product $record, array $data): void {
                abort_unless(auth()->user() instanceof User && auth()->user()->isManager(), 403);

                $record->loadMissing(['supplierProducts', 'integrationProducts.source']);
                if (self::hasSupplierChannel($record)) {
                    Notification::make()
                        ->danger()
                        ->title('Ручной режим недоступен')
                        ->body('У товара уже появился поставщик или активный канал импорта.')
                        ->send();

                    return;
                }

                $record->forceFill([
                    'manual_purchase_price_byn' => round((float) $data['manual_purchase_price_byn'], 2),
                    'price' => round((float) $data['retail_price'], 2),
                    'manual_price_updated_by' => auth()->id(),
                    'manual_price_updated_at' => now(),
                    'requires_price_confirmation' => false,
                ])->save();

                Notification::make()
                    ->success()
                    ->title('Ручные цены сохранены')
                    ->body('Поставщик не создан. Закупочная цена отмечена как введённая вручную.')
                    ->send();
            });
    }

    private static function isAvailableFor(Product $product): bool
    {
        $user = auth()->user();
        $product->loadMissing(['supplierProducts', 'integrationProducts.source']);

        return $user instanceof User
            && $user->isManager()
            && (float) $product->price > 0
            && ! self::hasSupplierChannel($product);
    }

    private static function hasSupplierChannel(Product $product): bool
    {
        return $product->supplierProducts->isNotEmpty()
            || $product->integrationProducts->contains(
                fn ($offer): bool => $offer->match_status === 'matched' && $offer->source?->is_active === true,
            );
    }
}
