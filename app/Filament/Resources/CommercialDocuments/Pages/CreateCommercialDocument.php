<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCommercialDocument extends CreateRecord
{
    protected static string $resource = CommercialDocumentResource::class;

    public function mount(): void
    {
        parent::mount();
        $prefill = session()->pull('commercial_document_prefill', []);
        $productIds = collect(session()->pull('commercial_document_product_ids', []))->map(fn ($id): int => (int) $id)->filter()->unique();
        if ($productIds->isEmpty()) {
            if ($prefill !== []) {
                $this->form->fill(array_merge($this->data, $prefill));
            }

            return;
        }

        $items = Product::query()->whereIn('id', $productIds)->get()->map(fn (Product $product): array => [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit ?: 'шт.',
            'quantity' => 1,
            'unit_price' => (float) $product->price,
            'discount_percent' => 0,
            'vat_rate' => 20,
            'line_total' => (float) $product->price,
        ])->all();

        $this->form->fill(array_merge($this->data, $prefill, ['items' => $items]));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);
        $record->recalculate();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return CommercialDocumentResource::getUrl('view', ['record' => $this->record]);
    }
}
