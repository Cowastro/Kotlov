<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Models\Product;
use App\Services\CommercialDocuments\CommercialDocumentClientResolver;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCommercialDocument extends CreateRecord
{
    protected static string $resource = CommercialDocumentResource::class;

    public bool $createMailDraft = false;

    public bool $aiAssisted = false;

    public function mount(): void
    {
        parent::mount();
        $this->createMailDraft = (bool) session()->pull('commercial_document_create_mail_draft', false);
        $this->aiAssisted = $this->createMailDraft;
        $prefill = session()->pull('commercial_document_prefill', []);
        $productIds = collect(session()->pull('commercial_document_product_ids', []))->map(fn ($id): int => (int) $id)->filter()->unique();
        $quantities = session()->pull('commercial_document_product_quantities', []);
        $defaultDiscount = min(100, max(0, (float) ($prefill['client_default_discount_percent'] ?? 0)));
        if ($productIds->isEmpty()) {
            if ($prefill !== []) {
                $this->form->fill(array_merge($this->data, $prefill));
            }

            return;
        }

        $items = Product::query()->whereIn('id', $productIds)->get()->map(function (Product $product) use ($quantities, $defaultDiscount): array {
            $quantity = max(0.001, (float) ($quantities[$product->id] ?? 1));

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit ?: 'шт.',
                'quantity' => $quantity,
                'unit_price' => (float) $product->price,
                'discount_percent' => $defaultDiscount,
                'vat_rate' => 20,
                'line_total' => round((float) $product->price * $quantity * (1 - ($defaultDiscount / 100)), 2),
            ];
        })->all();

        $this->form->fill(array_merge($this->data, $prefill));

        // A relationship repeater reloads its state from the (still unsaved)
        // document while the form is being filled. On the create page that
        // relationship is empty, so passing `items` to fill() is overwritten
        // by a blank default row. Hydrate the prepared rows afterwards.
        $itemsRepeater = $this->form->getComponentByStatePath('items');
        if ($itemsRepeater instanceof Repeater) {
            $hydratedItems = [];
            foreach ($items as $item) {
                $hydratedItems[$itemsRepeater->generateUuid() ?? count($hydratedItems)] = $item;
            }

            $itemsRepeater->rawState($hydratedItems);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);
        if ($this->aiAssisted) {
            $record->forceFill(['ai_assisted' => true])->save();
        }

        return $record;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        app(CommercialDocumentClientResolver::class)->sync($record);
        $record->recalculate();
        $record->refresh();

        if ($this->createMailDraft && filter_var((string) $record->client_email, FILTER_VALIDATE_EMAIL)) {
            try {
                app(CommercialDocumentWorkflow::class)->createMailDraft($record, auth()->user());
                Notification::make()
                    ->success()
                    ->title('КП и письмо готовы')
                    ->body('PDF приложен к черновику письма. Проверьте его и отправьте клиенту.')
                    ->send();
            } catch (\Throwable $exception) {
                Notification::make()->warning()->title('КП создано, письмо не сформировано')
                    ->body($exception->getMessage())->send();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return CommercialDocumentResource::getUrl('view', ['record' => $this->record]);
    }
}
