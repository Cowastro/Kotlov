<?php

namespace App\Filament\Resources\CityStorefronts\Pages;

use App\Filament\Resources\CityStorefronts\CityStorefrontResource;
use App\Services\CityStorefrontManager;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditCityStorefront extends EditRecord
{
    protected static string $resource = CityStorefrontResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(CityStorefrontManager::class)->update($record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn ($messages, $field): array => ['data.'.$field => $messages])->all());
        }
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('open')->label('Открыть сайт')->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn (): string => $this->record->storefrontUrl())->openUrlInNewTab()
            ->visible(fn (): bool => $this->record->storefront_enabled)];
    }
}
