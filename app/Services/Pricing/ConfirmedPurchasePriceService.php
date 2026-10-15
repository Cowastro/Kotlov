<?php

namespace App\Services\Pricing;

use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\ProductPurchasePriceSource;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmedPurchasePriceService
{
    /** @param array<string, mixed> $data */
    public function create(Product $product, array $data, User $user): ProductPurchasePriceSource
    {
        $sourcePrice = round((float) ($data['source_price'] ?? 0), 2);
        $currency = mb_strtoupper(trim((string) ($data['currency'] ?? 'BYN')));
        $currencyRate = $currency === 'BYN' ? 1.0 : (float) ($data['currency_rate'] ?? 0);
        $taxMode = (string) ($data['price_tax_mode'] ?? IntegrationSource::PRICE_TAX_INCLUSIVE);
        $vatRate = max(0, (float) ($data['vat_rate'] ?? 0));
        $supplier = filled($data['supplier_id'] ?? null)
            ? Supplier::query()->find((int) $data['supplier_id'])
            : null;
        $supplierName = $supplier?->name ?: trim((string) ($data['supplier_name'] ?? ''));

        $errors = [];
        if ($sourcePrice <= 0) {
            $errors['source_price'] = 'Укажите положительную цену из счёта.';
        }
        if (! in_array($currency, ['BYN', 'RUB', 'EUR', 'USD'], true)) {
            $errors['currency'] = 'Выберите поддерживаемую валюту.';
        }
        if ($currency !== 'BYN' && $currencyRate <= 0) {
            $errors['currency_rate'] = 'Для иностранной валюты укажите курс к BYN.';
        }
        if (! in_array($taxMode, [IntegrationSource::PRICE_TAX_INCLUSIVE, IntegrationSource::PRICE_TAX_EXCLUSIVE], true)) {
            $errors['price_tax_mode'] = 'Укажите, включает ли цена НДС.';
        }
        if ($supplierName === '') {
            $errors['supplier_name'] = 'Выберите поставщика или укажите название компании.';
        }
        if (filled($data['valid_until'] ?? null)
            && filled($data['document_date'] ?? null)
            && (string) $data['valid_until'] < (string) $data['document_date']) {
            $errors['valid_until'] = 'Срок действия не может быть раньше даты счёта.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $priceByn = $sourcePrice * $currencyRate;
        if ($taxMode === IntegrationSource::PRICE_TAX_EXCLUSIVE) {
            $priceByn *= 1 + $vatRate / 100;
        }
        $priceByn = round($priceByn, 2);

        return DB::transaction(function () use (
            $product,
            $data,
            $user,
            $supplier,
            $supplierName,
            $sourcePrice,
            $currency,
            $currencyRate,
            $taxMode,
            $vatRate,
            $priceByn,
        ): ProductPurchasePriceSource {
            $source = ProductPurchasePriceSource::query()->create([
                'product_id' => $product->id,
                'supplier_id' => $supplier?->id,
                'created_by' => $user->id,
                'source_type' => ProductPurchasePriceSource::TYPE_SUPPLIER_INVOICE,
                'supplier_name' => $supplierName,
                'document_number' => filled($data['document_number'] ?? null) ? trim((string) $data['document_number']) : null,
                'document_date' => $data['document_date'] ?? today(),
                'valid_until' => $data['valid_until'] ?? null,
                'source_price' => $sourcePrice,
                'currency' => $currency,
                'currency_rate' => $currencyRate,
                'price_tax_mode' => $taxMode,
                'vat_rate' => $vatRate,
                'price_byn' => $priceByn,
                'attachment_path' => $data['attachment_path'] ?? null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'is_active' => true,
                'confirmed_at' => now(),
            ]);

            $product->forceFill(['requires_price_confirmation' => false])->save();

            return $source;
        });
    }
}
