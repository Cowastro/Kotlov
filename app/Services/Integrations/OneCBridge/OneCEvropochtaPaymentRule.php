<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCOrderProfile;
use App\Models\OneCReference;
use App\Models\Order;
use DomainException;

class OneCEvropochtaPaymentRule
{
    public const CUSTOMER = 'Розничный покупатель Европочта';
    public const AGREEMENT = 'СБГ бай (Розничный покупатель)';
    public const FIELDS = ['Партнер_Key', 'Контрагент_Key', 'Соглашение_Key'];

    public function applies(Order $order): bool
    {
        return $order->delivery_type === 'evropochta' && $order->isPaymentOnReceipt();
    }

    private function normalized(string $text): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($text)) ?? '';
    }

    private function references(string $kind, string $name)
    {
        return OneCReference::where('kind', $kind)->where('deletion_mark', false)->where('is_folder', false)->get()
            ->filter(fn (OneCReference $ref): bool => $this->normalized($ref->description) === $this->normalized($name));
    }

    /** Exact keys from the loaded 1C catalog; never substitute the ordinary retail customer. */
    public function headers(OneCOrderProfile $profile): array
    {
        $partners = $this->references('partner', self::CUSTOMER);
        if ($partners->count() !== 1) {
            throw new DomainException('Для наложенного платежа Европочты нужен единственный клиент 1С «'.self::CUSTOMER.'». Обновите справочники 1С.');
        }
        $partner = $partners->first();
        $counterparties = $this->references('counterparty', self::CUSTOMER)
            ->filter(fn (OneCReference $ref): bool => strtolower((string) $ref->owner_key) === strtolower($partner->ref_key));
        if ($counterparties->count() !== 1) {
            throw new DomainException('Для наложенного платежа Европочты нужен единственный контрагент 1С «'.self::CUSTOMER.'», связанный с этим клиентом. Обновите справочники 1С.');
        }
        $agreements = $this->references('agreement', self::AGREEMENT)->keyBy(fn (OneCReference $ref): string => strtolower($ref->ref_key));
        $default = OneCOrderProfile::where('document', OneCOrderProfile::CUSTOMER_ORDER)->where('is_active', true)
            ->where('customer_mode', OneCOrderProfile::RETAIL)->where('is_default', true)->first();
        $agreement = $agreements->get(strtolower((string) ($profile->header['Соглашение_Key'] ?? '')))
            ?? $agreements->get(strtolower((string) ($default?->header['Соглашение_Key'] ?? '')))
            ?? ($agreements->count() === 1 ? $agreements->first() : null);
        if (! $agreement) {
            throw new DomainException('Выберите в розничной схеме единственное соглашение «'.self::AGREEMENT.'».');
        }
        return ['Партнер_Key' => strtolower($partner->ref_key), 'Контрагент_Key' => strtolower($counterparties->first()->ref_key),
            'Соглашение_Key' => strtolower($agreement->ref_key)];
    }

    public function problems(Order $order, ?OneCOrderProfile $profile, array $form): array
    {
        if (! $this->applies($order) || ! $profile) return [];
        try {
            $expected = $this->headers($profile);
        } catch (DomainException $exception) {
            return [$exception->getMessage()];
        }
        foreach ($expected as $field => $key) {
            if (strtolower((string) ($form['h'][$field] ?? '')) !== $key) {
                return ['Наложенный платёж Европочты: клиент и контрагент — «'.self::CUSTOMER.'», соглашение — «'.self::AGREEMENT.'». Исправьте реквизиты перед отправкой.'];
            }
        }
        return [];
    }
}
