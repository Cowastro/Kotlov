<?php

namespace App\Filament\Resources\VamExpressDrafts\Pages;

use App\Filament\Resources\VamExpressDrafts\VamExpressDraftResource;
use App\Models\VamExpressDraft;
use App\Services\Shipping\VamExpressDrafts;
use Filament\Resources\Pages\CreateRecord;

class CreateVamExpressDraft extends CreateRecord
{
    protected static bool $canCreateAnother = false;
    protected static string $resource = VamExpressDraftResource::class;

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Сохранить черновик');
    }

    protected function handleRecordCreation(array $data): VamExpressDraft
    {
        return app(VamExpressDrafts::class)->create($data['payload'] ?? [], auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
