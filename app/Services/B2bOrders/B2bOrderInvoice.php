<?php

namespace App\Services\B2bOrders;

use App\Mail\B2bOrderInvoiceIssuedMail;
use App\Models\B2bOrder;
use App\Models\CommercialDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class B2bOrderInvoice
{
    public function issue(B2bOrder $order, User $manager): CommercialDocument
    {
        abort_unless($manager->canManageB2bOrders(), 403);

        $document = DB::transaction(function () use ($order, $manager): CommercialDocument {
            $locked = B2bOrder::query()->lockForUpdate()->with(['items.product', 'client.counterparty', 'commercialDocument.items'])->findOrFail($order->id);
            if ($locked->status !== 'client_confirmed') {
                throw ValidationException::withMessages(['invoice' => 'Счёт можно выставить после подтверждения условий партнёром.']);
            }
            if (! $locked->stockIsSettled()) {
                throw ValidationException::withMessages(['invoice' => 'Сначала подтвердите наличие каждой позиции B2B-заказа.']);
            }
            if ($locked->commercialDocument) return $locked->commercialDocument;

            $client = $locked->client;
            $counterparty = $client->counterparty;
            $document = CommercialDocument::query()->create([
                'type' => 'invoice',
                'status' => 'sent',
                'created_by' => $manager->id,
                'manager_id' => $manager->id,
                'crm_counterparty_id' => $client->counterparty_id,
                'client_name' => $locked->contact_name,
                'client_company' => $client->company_name,
                'client_legal_name' => $counterparty?->legal_name ?: $locked->company_name,
                'client_email' => $locked->company_email ?: $locked->contact_email,
                'client_phone' => $locked->contact_phone,
                'client_unp' => $locked->company_unp ?: $client->unp,
                'client_address' => $counterparty?->legal_address ?: $locked->company_address,
                'issued_at' => today(),
                'valid_until' => today()->addDays(5),
                'currency' => 'BYN',
                'discount_total' => $locked->discount,
                'delivery_price' => $locked->delivery_price,
                'delivery_to_client' => $locked->delivery_type !== 'pickup',
                'vat_rate' => 20,
                'payment_terms' => $locked->payment_type === 'invoice_deferred'
                    ? 'Оплата с отсрочкой '.(int) data_get($locked->commercial_terms_snapshot, 'payment_deferment_days', 0).' календарных дней.'
                    : '100% предоплата по счёту.',
                'delivery_terms' => collect([$locked->delivery_city, $locked->delivery_address])->filter()->implode(', ') ?: 'Самовывоз или согласованная B2B-доставка.',
                'notes' => 'Счёт по B2B-заказу '.$locked->number.'.',
                'sent_at' => now(),
            ]);

            foreach ($locked->items->reject->isExcluded()->values() as $position => $item) {
                $document->items()->create([
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'sku' => $item->product_sku,
                    'unit' => $item->product?->unit ?: 'шт.',
                    'quantity' => $item->confirmed_quantity ?: $item->quantity,
                    'unit_price' => $item->price,
                    'discount_percent' => 0,
                    'vat_rate' => 20,
                    'sort_order' => $position,
                ]);
            }
            $document->recalculate();
            if (abs((float) $document->total - (float) $locked->total) > 0.02) {
                throw ValidationException::withMessages(['invoice' => 'Сумма счёта не совпадает с подтверждённым B2B-заказом.']);
            }

            $locked->forceFill(['commercial_document_id' => $document->id])->save();
            $workflow = app(B2bOrderWorkflow::class);
            $locked = $workflow->transition($locked, 'invoice_issued', $manager, 'Счёт '.$document->number.' выставлен партнёру.');
            $locked->payment_type === 'invoice_deferred'
                ? $workflow->transition($locked, 'credit_approved', $manager, 'Отсрочка по кредитному лимиту: заказ можно комплектовать.')
                : $workflow->transition($locked, 'awaiting_payment', $manager, 'Ожидаем оплату по счёту '.$document->number.'.');

            return $document->fresh('items');
        });

        $this->notifyPartner($order->fresh(['commercialDocument.items', 'client.user']), $document);

        return $document;
    }

    private function notifyPartner(B2bOrder $order, CommercialDocument $document): void
    {
        if ($order->invoice_notified_at !== null) {
            return;
        }

        $recipient = mb_strtolower(trim((string) (
            $document->client_email ?: $order->company_email ?: $order->contact_email ?: $order->client?->user?->email
        )));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $notifiedAt = now();
        $claimed = B2bOrder::query()->whereKey($order->id)->whereNull('invoice_notified_at')
            ->update(['invoice_notified_at' => $notifiedAt]);
        if ($claimed !== 1) {
            return;
        }

        try {
            $order->forceFill(['invoice_notified_at' => $notifiedAt]);
            Mail::mailer('transactional')->to($recipient)->queue(new B2bOrderInvoiceIssuedMail($order));
        } catch (\Throwable $exception) {
            B2bOrder::query()->whereKey($order->id)->where('invoice_notified_at', $notifiedAt)
                ->update(['invoice_notified_at' => null]);
            Log::error('B2B invoice email notification failed.', [
                'b2b_order_id' => $order->id,
                'commercial_document_id' => $document->id,
                'recipient' => $recipient,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
