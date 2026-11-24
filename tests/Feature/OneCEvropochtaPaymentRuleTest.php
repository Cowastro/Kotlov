<?php

namespace Tests\Feature;

use App\Models\OneCOrderProfile;
use App\Models\OneCReference;
use App\Models\Order;
use App\Services\Integrations\OneCBridge\OneCEvropochtaPaymentRule;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCEvropochtaPaymentRuleTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const COUNTERPARTY = 'aaaaaaaa-0000-0000-0000-000000000002';
    private const AGREEMENT = 'aaaaaaaa-0000-0000-0000-000000000003';

    private function fixture(): array
    {
        OneCReference::create(['kind' => 'partner', 'ref_key' => self::PARTNER, 'description' => OneCEvropochtaPaymentRule::CUSTOMER]);
        OneCReference::create(['kind' => 'counterparty', 'ref_key' => self::COUNTERPARTY,
            'description' => OneCEvropochtaPaymentRule::CUSTOMER, 'owner_key' => self::PARTNER]);
        OneCReference::create(['kind' => 'agreement', 'ref_key' => self::AGREEMENT, 'description' => OneCEvropochtaPaymentRule::AGREEMENT]);
        $profile = OneCOrderProfile::create(['name' => 'Розница СБГ бай', 'document' => 'customer', 'customer_mode' => 'retail',
            'is_active' => true, 'is_default' => true,
            'header' => ['Партнер_Key' => 'bbbbbbbb-0000-0000-0000-000000000001',
                'Контрагент_Key' => 'bbbbbbbb-0000-0000-0000-000000000002', 'Соглашение_Key' => self::AGREEMENT],
            'meta' => [], 'row_defaults' => []]);
        $order = new Order(['delivery_type' => 'evropochta', 'payment_type' => 'evropochta_cod', 'payment_status' => 'pending']);
        return [$order, $profile];
    }

    public function test_carrier_collection_uses_exact_partner_counterparty_and_agreement(): void
    {
        [$order, $profile] = $this->fixture();
        $form = app(OneCOrderExport::class)->defaults($order, $profile);
        $this->assertSame(self::PARTNER, $form['h']['Партнер_Key']);
        $this->assertSame(self::COUNTERPARTY, $form['h']['Контрагент_Key']);
        $this->assertSame(self::AGREEMENT, $form['h']['Соглашение_Key']);
        $this->assertSame([], app(OneCEvropochtaPaymentRule::class)->problems($order, $profile, $form));
    }

    public function test_prepaid_europochta_and_our_transport_keep_their_retail_customer(): void
    {
        [$order, $profile] = $this->fixture();
        $order->payment_type = 'online_card';
        $form = app(OneCOrderExport::class)->defaults($order, $profile);
        $this->assertSame($profile->header['Партнер_Key'], $form['h']['Партнер_Key']);
        $order->payment_type = 'cash';
        $order->delivery_type = 'courier';
        $this->assertFalse(app(OneCEvropochtaPaymentRule::class)->applies($order));
        $this->assertSame($profile->header['Контрагент_Key'], app(OneCOrderExport::class)->defaults($order, $profile)['h']['Контрагент_Key']);
    }

    public function test_missing_reference_never_falls_back_to_ordinary_retail(): void
    {
        [$order, $profile] = $this->fixture();
        OneCReference::where('kind', 'counterparty')->delete();
        $form = app(OneCOrderExport::class)->defaults($order, $profile);
        $this->assertNull($form['h']['Партнер_Key']);
        $this->assertNull($form['h']['Контрагент_Key']);
        $this->assertStringContainsString('контрагент', app(OneCEvropochtaPaymentRule::class)->problems($order, $profile, $form)[0]);
    }

    public function test_manual_customer_override_is_rejected_before_export(): void
    {
        [$order, $profile] = $this->fixture();
        $form = app(OneCOrderExport::class)->defaults($order, $profile);
        foreach (OneCEvropochtaPaymentRule::FIELDS as $field) {
            $wrong = $form;
            $wrong['h'][$field] = 'bbbbbbbb-0000-0000-0000-000000000009';
            $errors = app(OneCOrderExport::class)->problems($order, $profile, $wrong);
            $this->assertStringContainsString('Наложенный платёж Европочты', $errors[0]);
        }
    }

    public function test_agreement_uses_the_confirmed_profile_when_names_are_duplicated(): void
    {
        [$order, $profile] = $this->fixture();
        OneCReference::create(['kind' => 'agreement', 'ref_key' => 'cccccccc-0000-0000-0000-000000000003',
            'description' => OneCEvropochtaPaymentRule::AGREEMENT]);
        $this->assertSame(self::AGREEMENT, app(OneCOrderExport::class)->defaults($order, $profile)['h']['Соглашение_Key']);
    }

    public function test_dedicated_carrier_profile_is_selected_only_for_collection_on_receipt(): void
    {
        [$order, $retail] = $this->fixture();
        $carrier = $retail->replicate();
        $carrier->forceFill(['name' => 'Европочта — наложенный платёж', 'is_default' => false,
            'meta' => ['delivery_types' => ['evropochta']]])->save();
        $this->assertSame($carrier->id, app(OneCOrderExport::class)->defaults($order)['profile_id']);
        $order->payment_type = 'online_card';
        $this->assertSame($retail->id, app(OneCOrderExport::class)->defaults($order)['profile_id']);
    }
}
