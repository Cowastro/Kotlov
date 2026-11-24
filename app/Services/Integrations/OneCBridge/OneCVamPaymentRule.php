<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Models\VamExpressShipment;
use DomainException;

class OneCVamPaymentRule extends OneCCarrierPaymentRule
{
    public const CUSTOMER = 'ВАМэкспрессБай ООО';
    public const AGREEMENT = 'ПЛ-349 от 30.07.2021';
    public const CARRIER = 'ВАМ Экспресс';
    public const FIELDS = ['Партнер_Key', 'Контрагент_Key', 'Соглашение_Key', 'Договор_Key'];
    public const OPERATION = 'ПередачаНаКомиссию';

    public function applies(Order $order): bool
    {
        return $order->isPaymentOnReceipt() && ($order->delivery_type === 'vam_express'
            || ($order->exists && $order->delivery_type === 'transport' && VamExpressShipment::where('order_id', $order->id)->count() === 1));
    }

    public function headers(OneCOrderProfile $profile): array
    {
        $headers = parent::headers($profile);
        $organization = strtolower((string) ($profile->header['Организация_Key'] ?? ''));
        $agreement = $this->references('agreement', self::AGREEMENT)->firstWhere('ref_key', $headers['Соглашение_Key']);
        if (! $agreement || strtolower((string) $agreement->owner_key) !== $headers['Партнер_Key']
            || strtolower((string) ($agreement->data['Организация_Key'] ?? '')) !== $organization) {
            throw new DomainException('Соглашение ПЛ-349 должно принадлежать ВАМэкспрессБай и выбранной организации.');
        }
        $contracts = $this->references('contract', self::AGREEMENT)->filter(fn ($ref): bool =>
            strtolower((string) $ref->owner_key) === $headers['Партнер_Key']
            && strtolower((string) ($ref->data['Контрагент_Key'] ?? '')) === $headers['Контрагент_Key']
            && strtolower((string) ($ref->data['Организация_Key'] ?? '')) === $organization);
        if ($contracts->count() !== 1) throw new DomainException('Для ВАМ Экспресс нужен единственный договор ПЛ-349 выбранной организации и контрагента.');
        return $headers + ['Договор_Key' => strtolower($contracts->first()->ref_key)];
    }

    public function problems(Order $order, ?OneCOrderProfile $profile, array $form): array
    {
        $problems = parent::problems($order, $profile, $form);
        if ($this->applies($order) && $profile && ($profile->header['ХозяйственнаяОперация'] ?? null) !== self::OPERATION) {
            $problems[] = 'Когда деньги получает ВАМ Экспресс, нужна схема «Передача на комиссию» по договору ПЛ-349.';
        }
        return $problems;
    }
}
