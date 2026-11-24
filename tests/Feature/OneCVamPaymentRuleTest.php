<?php

namespace Tests\Feature;

use App\Models\OneCOrderProfile;
use App\Models\OneCReference;
use App\Models\Order;
use App\Services\Integrations\OneCBridge\OneCVamPaymentRule;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCVamPaymentRuleTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const COUNTERPARTY = 'aaaaaaaa-0000-0000-0000-000000000002';
    private const AGREEMENT = 'aaaaaaaa-0000-0000-0000-000000000003';
    private const CONTRACT = 'aaaaaaaa-0000-0000-0000-000000000004';
    private const ORG = 'aaaaaaaa-0000-0000-0000-000000000005';

    private function fixture(): array
    {
        foreach ([['partner', self::PARTNER], ['counterparty', self::COUNTERPARTY]] as [$kind, $key]) {
            OneCReference::create(['kind' => $kind, 'ref_key' => $key, 'description' => OneCVamPaymentRule::CUSTOMER,
                'owner_key' => $kind === 'counterparty' ? self::PARTNER : null]);
        }
        foreach ([['agreement', self::AGREEMENT], ['contract', self::CONTRACT]] as [$kind, $key]) {
            OneCReference::create(['kind' => $kind, 'ref_key' => $key, 'description' => OneCVamPaymentRule::AGREEMENT,
                'owner_key' => self::PARTNER, 'data' => ['Организация_Key' => self::ORG, 'Контрагент_Key' => self::COUNTERPARTY]]);
        }
        $profile = OneCOrderProfile::create(['name' => 'ВАМ Экспресс', 'document' => 'customer', 'customer_mode' => 'retail',
            'is_active' => true, 'is_default' => false, 'header' => ['Партнер_Key' => self::PARTNER,
                'Контрагент_Key' => self::COUNTERPARTY, 'Соглашение_Key' => self::AGREEMENT, 'Договор_Key' => self::CONTRACT,
                'Организация_Key' => self::ORG, 'ХозяйственнаяОперация' => OneCVamPaymentRule::OPERATION],
            'meta' => ['delivery_types' => ['vam_express']], 'row_defaults' => []]);
        return [new Order(['delivery_type' => 'vam_express', 'payment_type' => 'cash', 'payment_status' => 'pending']), $profile];
    }

    public function test_collection_uses_vam_customer_agreement_contract_and_commission(): void
    {
        [$order, $profile] = $this->fixture();
        $form = app(OneCOrderExport::class)->defaults($order, $profile);
        foreach (['Партнер_Key' => self::PARTNER, 'Контрагент_Key' => self::COUNTERPARTY,
            'Соглашение_Key' => self::AGREEMENT, 'Договор_Key' => self::CONTRACT] as $field => $key) {
            $this->assertSame($key, $form['h'][$field]);
        }
        $this->assertSame([], app(OneCVamPaymentRule::class)->problems($order, $profile, $form));
    }

    public function test_wrong_contract_and_sale_operation_cannot_be_sent(): void
    {
        [$order, $profile] = $this->fixture();
        $form = app(OneCOrderExport::class)->defaults($order, $profile);
        $form['h']['Договор_Key'] = 'bbbbbbbb-0000-0000-0000-000000000001';
        $this->assertNotEmpty(app(OneCVamPaymentRule::class)->problems($order, $profile, $form));
        $profile->header = array_replace($profile->header, ['ХозяйственнаяОперация' => 'РеализацияКлиенту']);
        $this->assertStringContainsString('Передача на комиссию', implode(' ', app(OneCVamPaymentRule::class)->problems($order, $profile, $form)));
    }

    public function test_prepayment_and_unrelated_carrier_do_not_use_commission(): void
    {
        [$order] = $this->fixture();
        $order->payment_type = 'online_card';
        $this->assertFalse(app(OneCVamPaymentRule::class)->applies($order));
        $order->payment_type = 'cash';
        $order->delivery_type = 'transport';
        $this->assertFalse(app(OneCVamPaymentRule::class)->applies($order));
    }

    public function test_contract_of_another_organization_does_not_fall_back_to_retail(): void
    {
        [$order, $profile] = $this->fixture();
        OneCReference::where('kind', 'contract')->update(['data' => json_encode(['Организация_Key' => 'other', 'Контрагент_Key' => self::COUNTERPARTY])]);
        $form = app(OneCOrderExport::class)->defaults($order, $profile);
        $this->assertNull($form['h']['Договор_Key']);
        $this->assertStringContainsString('договор ПЛ-349', implode(' ', app(OneCVamPaymentRule::class)->problems($order, $profile, $form)));
    }

    public function test_profile_selection_keeps_prepayment_in_ordinary_retail(): void
    {
        [$order, $vam] = $this->fixture();
        $retail = OneCOrderProfile::create(['name' => 'Розница', 'document' => 'customer', 'customer_mode' => 'retail',
            'is_active' => true, 'is_default' => true, 'header' => ['ХозяйственнаяОперация' => 'РеализацияКлиенту'],
            'meta' => [], 'row_defaults' => []]);
        $this->assertSame($vam->id, app(OneCOrderExport::class)->defaults($order)['profile_id']);
        $order->payment_type = 'online_card';
        $this->assertSame($retail->id, app(OneCOrderExport::class)->defaults($order)['profile_id']);
    }

    public function test_legacy_transport_only_uses_vam_when_a_shipment_is_linked(): void
    {
        [$order, $vam] = $this->fixture();
        $order->forceFill(['number' => 'VAM-LEGACY', 'customer_name' => 'Клиент', 'customer_phone' => '+375291110000',
            'status' => 'processing', 'delivery_type' => 'transport', 'subtotal' => 10, 'total' => 10])->save();
        $this->assertFalse(app(OneCVamPaymentRule::class)->applies($order));
        $shipment = \App\Models\VamExpressShipment::create(['remote_id' => 'abcdef0', 'display_id' => 'abcdef0']);
        $shipment->forceFill(['order_id' => $order->id])->save();
        $this->assertTrue(app(OneCVamPaymentRule::class)->applies($order));
        $this->assertSame($vam->id, app(OneCOrderExport::class)->defaults($order)['profile_id']);
    }
}
