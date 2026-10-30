<?php

namespace App\Services\CommercialDocuments;

use App\Models\B2bClient;
use App\Models\B2bClientAuditLog;
use App\Models\CommercialDocument;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class B2bSelfServiceInvoice
{
    /**
     * Issue one immutable customer-facing invoice from the manager-confirmed
     * order snapshot. The browser never supplies quantities or prices.
     */
    public function generate(B2bClient $client, User $actor, Order $order): CommercialDocument
    {
        $companyUserIds = $client->members()
            ->where('is_active', true)
            ->pluck('user_id')
            ->push($client->user_id)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        return DB::transaction(function () use ($client, $actor, $order, $companyUserIds): CommercialDocument {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array((int) $locked->user_id, $companyUserIds, true), 404);
            $locked->load(['items.product', 'commercialDocument.items']);

            $this->assertEligible($client, $locked);

            $existing = $locked->commercialDocument;
            if ($existing && $existing->type !== 'invoice') {
                throw ValidationException::withMessages([
                    'invoice' => 'К заказу уже привязан другой документ. Обратитесь к менеджеру.',
                ]);
            }
            if ($existing && $existing->status !== 'draft') {
                if (in_array($existing->status, ['sent', 'accepted', 'paid'], true)) {
                    return $existing->fresh('items');
                }

                throw ValidationException::withMessages([
                    'invoice' => 'Этот счёт нельзя перевыпустить автоматически. Обратитесь к менеджеру.',
                ]);
            }

            $counterparty = $client->counterparty()->first();
            $legalName = trim((string) ($counterparty?->legal_name ?: $locked->company_name ?: $client->company_name));
            $unp = preg_replace('/\D+/', '', (string) ($client->unp ?: $locked->company_unp));
            $legalAddress = trim((string) ($counterparty?->legal_address ?: $locked->company_address));
            $email = trim((string) ($locked->company_email ?: $client->contact_email ?: $actor->email));
            $phone = trim((string) ($client->contact_phone ?: $locked->customer_phone));

            $missing = collect([
                'полное юридическое название' => $legalName,
                'УНП' => preg_match('/^\d{9}$/', $unp) ? $unp : null,
                'юридический адрес' => $legalAddress,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'карточка контрагента' => $counterparty?->id,
            ])->filter(fn ($value): bool => blank($value))->keys()->all();
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'invoice' => 'Для счёта заполните в разделе «Компания и реквизиты»: '.implode(', ', $missing).'.',
                ]);
            }

            $lineSubtotal = round((float) $locked->items->sum(
                fn ($item): float => round((float) $item->price * (int) $item->quantity, 2),
            ), 2);
            $deliveryPrice = round((float) $locked->delivery_price, 2);
            $orderTotal = round((float) $locked->total, 2);
            if ($orderTotal > $lineSubtotal + $deliveryPrice + 0.02) {
                throw ValidationException::withMessages([
                    'invoice' => 'Итог заказа требует проверки менеджером перед формированием счёта.',
                ]);
            }
            $discount = round(max(0, $lineSubtotal + $deliveryPrice - $orderTotal), 2);
            $defermentDays = max(0, (int) data_get($locked->b2b_commercial_terms_snapshot, 'payment_deferment_days', 0));
            $paymentTerms = $locked->payment_type === 'invoice_deferred' && $defermentDays > 0
                ? 'Оплата с отсрочкой '.$defermentDays.' календарных дней в пределах согласованного кредитного лимита.'
                : '100% предоплата по счёту.';
            $deliveryLabel = Order::DELIVERY_TYPES[$locked->delivery_type] ?? $locked->delivery_type;
            $deliveryTerms = collect([
                $deliveryLabel,
                $locked->delivery_address,
                $locked->planned_delivery_date?->format('d.m.Y'),
            ])->filter()->implode(' · ');

            $document = $existing ?: new CommercialDocument;
            $document->fill([
                'type' => 'invoice',
                'status' => 'draft',
                'created_by' => $actor->id,
                'manager_id' => $locked->availability_confirmed_by ?: $locked->manager_id,
                'crm_counterparty_id' => $counterparty->id,
                'crm_contact_id' => $locked->crm_contact_id,
                'client_name' => $client->contact_name ?: $locked->customer_name,
                'client_company' => $client->company_name,
                'client_legal_name' => $legalName,
                'client_email' => $email,
                'client_phone' => $phone,
                'client_unp' => $unp,
                'client_address' => $legalAddress,
                'issued_at' => today(),
                'valid_until' => today()->addDays(5),
                'currency' => 'BYN',
                'discount_total' => $discount,
                'delivery_price' => $deliveryPrice,
                'delivery_to_client' => $locked->delivery_type !== 'pickup',
                'vat_rate' => 20,
                'payment_terms' => $paymentTerms,
                'delivery_terms' => $deliveryTerms ?: 'Способ и срок поставки подтверждены в заказе.',
                'notes' => 'Сформировано партнёром из подтверждённого заказа '.$locked->number.'. Состав и цены зафиксированы заказом.',
            ]);
            $document->save();

            $document->items()->delete();
            foreach ($locked->items as $position => $item) {
                $document->items()->create([
                    'product_id' => $item->product_id,
                    'name' => $item->product_name ?: $item->product?->name ?: 'Товар из заказа',
                    'sku' => $item->product_sku ?: $item->product?->sku,
                    'unit' => $item->product?->unit ?: 'шт.',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'discount_percent' => 0,
                    'vat_rate' => 20,
                    'sort_order' => $position,
                ]);
            }
            $document->recalculate();
            if (abs((float) $document->total - $orderTotal) > 0.02) {
                throw ValidationException::withMessages([
                    'invoice' => 'Сумма счёта не совпала с подтверждённым заказом. Обратитесь к менеджеру.',
                ]);
            }

            $document->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();
            $locked->forceFill([
                'commercial_document_id' => $document->id,
                'crm_counterparty_id' => $counterparty->id,
            ])->saveQuietly();

            B2bClientAuditLog::query()->create([
                'b2b_client_id' => $client->id,
                'actor_user_id' => $actor->id,
                'event' => 'invoice_generated',
                'metadata' => [
                    'order_id' => $locked->id,
                    'order_number' => $locked->number,
                    'document_id' => $document->id,
                    'document_number' => $document->number,
                ],
            ]);

            return $document->fresh('items');
        }, 3);
    }

    private function assertEligible(B2bClient $client, Order $order): void
    {
        if (! in_array($order->payment_type, ['invoice', 'invoice_deferred'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'Самостоятельный счёт доступен только для заказа с оплатой по счёту.',
            ]);
        }
        if (! $order->availability_confirmed_at
            || ! in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered'], true)
            || ! $order->hasConfirmedFulfillmentForAllItems()) {
            throw ValidationException::withMessages([
                'invoice' => 'Счёт станет доступен после подтверждения менеджером наличия и способа исполнения каждой позиции.',
            ]);
        }
        if ($order->items->isEmpty() || $order->items->contains(fn ($item): bool => $item->pricing_type !== 'b2b')) {
            throw ValidationException::withMessages([
                'invoice' => 'Самостоятельный счёт формируется только по подтверждённому B2B-заказу.',
            ]);
        }
        if ($client->status !== 'approved') {
            throw ValidationException::withMessages([
                'invoice' => 'B2B-доступ компании приостановлен. Обратитесь к менеджеру.',
            ]);
        }
    }
}
