<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\WebpayPaymentAttempt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

class WebpayService
{
    /** @return array{action: string, fields: array<string, string>, items: array<int, array{name: string, quantity: string, price: string}>} */
    public function createPayment(Order $order): array
    {
        $this->assertConfigured();

        $attempt = DB::transaction(function () use ($order): WebpayPaymentAttempt {
            $lockedOrder = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);

            if (! $this->canPay($lockedOrder)) {
                throw new RuntimeException('Этот заказ сейчас нельзя оплатить через WEBPAY.');
            }

            $attempt = $lockedOrder->webpayPaymentAttempts()->create([
                'merchant_order_num' => $this->merchantOrderNumber($lockedOrder),
                'amount' => $this->money($lockedOrder->total),
                'currency' => $this->currency(),
                'environment' => $this->testMode() ? 'sandbox' : 'production',
                'status' => 'pending',
                'seed' => bin2hex(random_bytes(16)),
            ]);

            if ($lockedOrder->payment_type !== 'webpay') {
                $lockedOrder->forceFill(['payment_type' => 'webpay'])->save();
            }

            return $attempt->setRelation('order', $lockedOrder);
        });

        $order = $attempt->order;
        $items = $order->items->values()->map(fn ($item): array => [
            'name' => Str::limit((string) ($item->product_name ?: 'Товар'), 255, ''),
            'quantity' => (string) max(1, (int) $item->quantity),
            'price' => $this->money($item->price),
        ])->all();

        $fields = [
            '*scart' => '',
            'wsb_version' => '2',
            'wsb_language_id' => 'russian',
            'wsb_storeid' => (string) config('services.webpay.store_id'),
            'wsb_store' => Str::limit((string) config('services.webpay.store_name'), 64, ''),
            'wsb_order_num' => $attempt->merchant_order_num,
            'wsb_test' => $this->testMode() ? '1' : '0',
            'wsb_currency_id' => $attempt->currency,
            'wsb_seed' => $attempt->seed,
            'wsb_total' => $this->money($attempt->amount),
            'wsb_return_url' => route('webpay.return'),
            'wsb_cancel_return_url' => route('webpay.cancel'),
            'wsb_notify_url' => route('webpay.notify'),
            'wsb_customer_name' => Str::limit((string) $order->customer_name, 255, ''),
            'wsb_customer_address' => Str::limit($this->customerAddress($order), 255, ''),
            'wsb_email' => Str::limit((string) $order->customer_email, 255, ''),
            'wsb_phone' => Str::limit((string) $order->customer_phone, 32, ''),
        ];

        if ((float) $order->delivery_price > 0) {
            $fields['wsb_shipping_name'] = 'Доставка';
            $fields['wsb_shipping_price'] = $this->money($order->delivery_price);
        }

        if ((float) $order->discount > 0) {
            $fields['wsb_discount_name'] = 'Скидка';
            $fields['wsb_discount_price'] = $this->money($order->discount);
        }

        $fields['wsb_signature'] = $this->orderSignature($fields);

        return [
            'action' => (string) config('services.webpay.payment_url'),
            'fields' => $fields,
            'items' => $items,
        ];
    }

    public function canPay(Order $order): bool
    {
        return $this->configured()
            && $order->availability_confirmed_at !== null
            && in_array($order->status, ['confirmed', 'processing', 'shipped'], true)
            && $order->payment_status !== 'paid'
            && (float) $order->total > 0
            && (! $this->testMode() || $this->sandboxCustomerAllowed($order));
    }

    public function configured(): bool
    {
        return (bool) config('services.webpay.enabled')
            && filled(config('services.webpay.store_id'))
            && filled(config('services.webpay.secret_key'))
            && filled(config('services.webpay.username'))
            && filled(config('services.webpay.password'));
    }

    public function verifyNotificationSignature(array $payload): bool
    {
        $required = [
            'batch_timestamp', 'currency_id', 'amount', 'payment_method', 'order_id',
            'site_order_id', 'transaction_id', 'payment_type', 'rrn', 'wsb_signature',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null) {
                return false;
            }
        }

        $signatureSource = '';
        foreach (array_slice($required, 0, -1) as $key) {
            $signatureSource .= (string) $payload[$key];
        }
        if (filled($payload['card'] ?? null)) {
            $signatureSource .= (string) $payload['card'];
        }
        $signatureSource .= (string) config('services.webpay.secret_key');

        return hash_equals(md5($signatureSource), strtolower((string) $payload['wsb_signature']));
    }

    /**
     * Verify the signed notifier against WEBPAY billing before changing the order.
     *
     * @throws ConnectionException|RuntimeException
     */
    public function acceptNotification(array $payload): WebpayPaymentAttempt
    {
        $this->assertConfigured();

        if (! $this->verifyNotificationSignature($payload)) {
            throw new RuntimeException('WEBPAY notification signature is invalid.');
        }

        $attempt = WebpayPaymentAttempt::query()
            ->where('merchant_order_num', (string) $payload['site_order_id'])
            ->firstOrFail();

        if ($attempt->status === 'paid' && hash_equals((string) $attempt->transaction_id, (string) $payload['transaction_id'])) {
            return $attempt;
        }

        $transaction = $this->getTransaction((string) $payload['transaction_id']);
        $this->assertTransactionMatches($attempt, $payload, $transaction);

        return DB::transaction(function () use ($attempt, $payload, $transaction): WebpayPaymentAttempt {
            $lockedAttempt = WebpayPaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedAttempt->order_id);

            if ($lockedAttempt->status === 'paid') {
                if (! hash_equals((string) $lockedAttempt->transaction_id, (string) $payload['transaction_id'])) {
                    throw new RuntimeException('WEBPAY attempt was already paid by another transaction.');
                }

                return $lockedAttempt;
            }

            if ($this->money($lockedOrder->total) !== $this->money($lockedAttempt->amount)) {
                $lockedAttempt->update([
                    'status' => 'review',
                    'notification_payload' => $this->safeNotificationPayload($payload),
                    'notified_at' => now(),
                ]);

                throw new RuntimeException('Order total changed after WEBPAY payment was started.');
            }

            $lockedAttempt->update([
                'status' => 'paid',
                'transaction_id' => (string) $transaction['transaction_id'],
                'webpay_order_id' => (string) $transaction['order_id'],
                'payment_method' => (string) $transaction['payment_method'],
                'payment_type' => (string) $transaction['payment_type'],
                'rrn' => (string) $transaction['rrn'],
                'notification_payload' => $this->safeNotificationPayload($payload),
                'notified_at' => now(),
                'verified_at' => now(),
            ]);

            $lockedOrder->forceFill([
                'payment_type' => 'webpay',
                'payment_status' => 'paid',
            ])->save();

            return $lockedAttempt->fresh();
        });
    }

    /** @return array<string, string> */
    private function getTransaction(string $transactionId): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<wsb_api_request><command>get_transaction</command><authorization>'
            .'<username>'.$this->xml((string) config('services.webpay.username')).'</username>'
            .'<password>'.md5((string) config('services.webpay.password')).'</password>'
            .'</authorization><fields><transaction_id>'.$this->xml($transactionId).'</transaction_id>'
            .'</fields></wsb_api_request>';

        $response = Http::asForm()
            ->accept('application/xml')
            ->timeout((int) config('services.webpay.timeout', 15))
            ->post((string) config('services.webpay.api_url'), [
                '*API' => '',
                'API_XML_REQUEST' => $xml,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('WEBPAY billing API returned HTTP '.$response->status().'.');
        }

        $document = $this->parseXml($response->body());
        $fields = [];
        foreach (['transaction_id', 'batch_timestamp', 'currency_id', 'amount', 'payment_method', 'payment_type', 'order_id', 'rrn', 'wsb_signature'] as $name) {
            $nodes = $document->xpath('//'.$name);
            $fields[$name] = isset($nodes[0]) ? (string) $nodes[0] : '';
        }

        if (collect($fields)->contains(fn (string $value): bool => $value === '')) {
            throw new RuntimeException('WEBPAY billing API returned an incomplete transaction.');
        }

        $signatureSource = implode('', Arr::only($fields, [
            'transaction_id', 'batch_timestamp', 'currency_id', 'amount', 'payment_method',
            'payment_type', 'order_id', 'rrn',
        ])).(string) config('services.webpay.secret_key');

        if (! hash_equals(md5($signatureSource), strtolower($fields['wsb_signature']))) {
            throw new RuntimeException('WEBPAY billing response signature is invalid.');
        }

        return $fields;
    }

    /** @param array<string, string> $transaction */
    private function assertTransactionMatches(WebpayPaymentAttempt $attempt, array $payload, array $transaction): void
    {
        $matches = hash_equals((string) $payload['transaction_id'], $transaction['transaction_id'])
            && hash_equals((string) $payload['order_id'], $transaction['order_id'])
            && $this->money($payload['amount']) === $this->money($attempt->amount)
            && $this->money($transaction['amount']) === $this->money($attempt->amount)
            && hash_equals($attempt->currency, (string) $payload['currency_id'])
            && hash_equals($attempt->currency, $transaction['currency_id'])
            && in_array((string) $payload['payment_type'], ['1', '4'], true)
            && in_array($transaction['payment_type'], ['1', '4'], true);

        if (! $matches) {
            throw new RuntimeException('WEBPAY transaction does not match the order.');
        }
    }

    private function orderSignature(array $fields): string
    {
        return sha1(
            $fields['wsb_seed']
            .$fields['wsb_storeid']
            .$fields['wsb_order_num']
            .$fields['wsb_test']
            .$fields['wsb_currency_id']
            .$fields['wsb_total']
            .(string) config('services.webpay.secret_key')
        );
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('WEBPAY пока не настроен.');
        }
    }

    private function merchantOrderNumber(Order $order): string
    {
        return 'KTLV-'.$order->id.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(10));
    }

    private function customerAddress(Order $order): string
    {
        return collect([$order->delivery_region, $order->delivery_city, $order->delivery_address])
            ->filter()
            ->unique()
            ->implode(', ');
    }

    private function currency(): string
    {
        return strtoupper((string) config('services.webpay.currency', 'BYN'));
    }

    private function testMode(): bool
    {
        return (bool) config('services.webpay.test_mode', true);
    }

    private function sandboxCustomerAllowed(Order $order): bool
    {
        $allowedEmails = collect(explode(',', (string) config('services.webpay.sandbox_emails', '')))
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter();
        $customerEmail = $order->relationLoaded('user')
            ? $order->user?->email
            : $order->user()->value('email');

        return filled($customerEmail)
            && $allowedEmails->contains(mb_strtolower(trim((string) $customerEmail)));
    }

    private function money(mixed $amount): string
    {
        return number_format(round((float) $amount, 2), 2, '.', '');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($document === false) {
                throw new RuntimeException('WEBPAY billing API returned invalid XML.');
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<string, mixed> */
    private function safeNotificationPayload(array $payload): array
    {
        return Arr::only($payload, [
            'batch_timestamp', 'currency_id', 'amount', 'payment_method', 'order_id',
            'site_order_id', 'transaction_id', 'payment_type', 'rrn', 'action', 'rc', 'approval',
        ]);
    }
}
