<?php

namespace App\Services\Orders;

use App\Models\CrmMailThread;
use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SupplierReservationMailService
{
    public function __construct(
        private readonly SupplierRequestMailDraftService $drafts,
        private readonly CrmMailSender $sender,
    ) {}

    public function sendOrderRequest(
        SupplierOrderRequest $request,
        User $user,
        string $recipient,
        ?string $note = null,
    ): CrmMailThread {
        $this->assertManager($user);
        $recipient = $this->validRecipient($recipient);
        if (filled($note)) {
            $request->forceFill(['note' => trim((string) $note)])->save();
        }
        $thread = $this->drafts->sync($request, $user, $note);
        $body = $this->drafts->orderBody($request, $note);

        $thread = $this->drafts->update(
            $thread,
            [$recipient],
            (string) $thread->subject,
            $body,
            $user,
        );

        $this->sender->sendSupplierDraft($thread, $user);

        return $thread->fresh(['mailbox', 'messages', 'supplierOrderRequest']);
    }

    public function prepareProductRequest(
        Product $product,
        int $supplierId,
        User $user,
        int $quantity,
        string $recipient,
        ?string $note = null,
    ): CrmMailThread {
        $this->assertManager($user);
        $recipient = $this->validRecipient($recipient);
        $quantity = max(1, $quantity);
        $snapshot = $this->productSupplierSnapshot($product, $supplierId);

        return $this->drafts->createProductReserveDraft(
            $product,
            $snapshot['supplier'],
            $user,
            $quantity,
            $snapshot['purchase_price'],
            $snapshot['stock_quantity'],
            $snapshot['supplier_article'],
            $recipient,
            $note,
        );
    }

    public function sendProductRequest(
        Product $product,
        int $supplierId,
        User $user,
        int $quantity,
        string $recipient,
        ?string $note = null,
    ): CrmMailThread {
        $thread = $this->prepareProductRequest(
            $product,
            $supplierId,
            $user,
            $quantity,
            $recipient,
            $note,
        );

        $this->sender->sendSupplierDraft($thread, $user);

        return $thread->fresh(['mailbox', 'messages', 'product', 'supplier']);
    }

    /** @return array<int, string> */
    public function productSupplierOptions(Product $product): array
    {
        return $this->productSupplierSnapshots($product)
            ->mapWithKeys(function (array $snapshot): array {
                $details = collect([
                    $snapshot['purchase_price'] !== null
                        ? number_format($snapshot['purchase_price'], 2, ',', ' ').' BYN'
                        : 'цена не указана',
                    $snapshot['stock_quantity'] !== null
                        ? 'остаток '.rtrim(rtrim(number_format($snapshot['stock_quantity'], 3, ',', ' '), '0'), ',')
                        : null,
                ])->filter()->implode(' · ');

                return [$snapshot['supplier']->id => $snapshot['supplier']->name.' · '.$details];
            })
            ->all();
    }

    public function hasProductSupplier(Product $product): bool
    {
        if ($product->relationLoaded('supplierProducts') && $product->supplierProducts
            ->contains(fn (SupplierProduct $item): bool => (bool) $item->supplier?->is_active)) {
            return true;
        }

        if ($product->relationLoaded('integrationProducts') && $product->integrationProducts
            ->contains(fn (IntegrationProduct $item): bool => $item->match_status === 'matched'
                && (bool) $item->source?->supplier?->is_active)) {
            return true;
        }

        return SupplierProduct::query()
            ->where('product_id', $product->id)
            ->whereHas('supplier', fn ($query) => $query->where('is_active', true))
            ->exists()
            || IntegrationProduct::query()
                ->where('product_id', $product->id)
                ->where('match_status', 'matched')
                ->whereHas('source.supplier', fn ($query) => $query->where('is_active', true))
                ->exists();
    }

    public function supplierEmail(int $supplierId): ?string
    {
        return Supplier::query()
            ->with('crmCounterparty.contactPoints')
            ->find($supplierId)
            ?->contactEmailAddresses()[0] ?? null;
    }

    /** @return array{supplier: Supplier, purchase_price: ?float, stock_quantity: ?float, supplier_article: ?string} */
    public function productSupplierSnapshot(Product $product, int $supplierId): array
    {
        $snapshot = $this->productSupplierSnapshots($product)->get($supplierId);

        if (! $snapshot) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Для товара нет активного канала выбранного поставщика.',
            ]);
        }

        return $snapshot;
    }

    /** @return Collection<int, array{supplier: Supplier, purchase_price: ?float, stock_quantity: ?float, supplier_article: ?string}> */
    private function productSupplierSnapshots(Product $product): Collection
    {
        $supplierProducts = SupplierProduct::query()
            ->with('supplier.crmCounterparty.contactPoints')
            ->where('product_id', $product->id)
            ->whereHas('supplier', fn ($query) => $query->where('is_active', true))
            ->get()
            ->mapWithKeys(fn (SupplierProduct $item): array => [
                $item->supplier_id => [
                    'supplier' => $item->supplier,
                    'purchase_price' => $item->price_byn !== null ? (float) $item->price_byn : null,
                    'stock_quantity' => $item->stock_quantity !== null ? (float) $item->stock_quantity : null,
                    'supplier_article' => $item->supplier_article,
                ],
            ]);

        $integrationProducts = IntegrationProduct::query()
            ->with('source.supplier.crmCounterparty.contactPoints')
            ->where('product_id', $product->id)
            ->where('match_status', 'matched')
            ->whereHas('source.supplier', fn ($query) => $query->where('is_active', true))
            ->get();

        foreach ($integrationProducts as $item) {
            $supplier = $item->source?->supplier;
            if (! $supplier || $supplierProducts->has($supplier->id)) {
                continue;
            }
            $supplierProducts->put($supplier->id, [
                'supplier' => $supplier,
                'purchase_price' => $item->price_byn !== null ? (float) $item->price_byn : null,
                'stock_quantity' => $item->stock_quantity !== null ? (float) $item->stock_quantity : null,
                'supplier_article' => $item->external_sku,
            ]);
        }

        return $supplierProducts;
    }

    private function assertManager(User $user): void
    {
        if (! $user->isManager()) {
            throw new RuntimeException('Отправлять заявки поставщикам может только менеджер.');
        }
    }

    private function validRecipient(string $recipient): string
    {
        $recipient = mb_strtolower(trim($recipient));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'recipient' => 'Укажите корректный email поставщика.',
            ]);
        }

        return $recipient;
    }
}
