<?php

namespace App\Filament\Resources\OneCOrderProfiles\Pages;

use App\Filament\Resources\OneCOrderProfiles\OneCOrderProfileResource;
use App\Filament\Resources\Orders\Actions\OneCOrderExportAction;
use App\Services\Integrations\OneCBridge\OneCReferences;
use Filament\Resources\Pages\EditRecord;

class EditOneCOrderProfile extends EditRecord
{
    protected static string $resource = OneCOrderProfileResource::class;

    /** The 1C fields go to Latin form keys and back (Livewire keeps form keys in Latin). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $header = (array) ($data['header'] ?? []);
        foreach (OneCOrderExportAction::KEYS as $alias => $field) {
            $value = strtolower((string) ($header[$field] ?? ''));
            $data['f'][$alias] = $value !== '' && $value !== OneCReferences::ZERO ? $value : null;
        }
        $data['status'] = $header['Статус'] ?? null;
        $data['delivery'] = $header['СпособДоставки'] ?? null;
        $data['with_vat'] = (bool) ($header['ЦенаВключаетНДС'] ?? false);
        $data['vat'] = $data['row_defaults']['СтавкаНДС_Key'] ?? null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $header = (array) $this->record->header;
        foreach (OneCOrderExportAction::KEYS as $alias => $field) {
            if (array_key_exists($field, $header) || filled($data['f'][$alias] ?? null)) {
                $header[$field] = ($data['f'][$alias] ?? null) ?: OneCReferences::ZERO;
            }
        }
        foreach (['Статус' => 'status', 'СпособДоставки' => 'delivery'] as $field => $key) {
            if (filled($data[$key] ?? null)) {
                $header[$field] = $data[$key];
            }
        }
        $header['ЦенаВключаетНДС'] = (bool) ($data['with_vat'] ?? false);
        $row = (array) $this->record->row_defaults;
        if (filled($data['vat'] ?? null)) {
            $row['СтавкаНДС_Key'] = $data['vat'];
        }
        unset($data['f'], $data['status'], $data['delivery'], $data['with_vat'], $data['vat']);

        return array_merge($data, ['header' => $header, 'row_defaults' => $row]);
    }
}
