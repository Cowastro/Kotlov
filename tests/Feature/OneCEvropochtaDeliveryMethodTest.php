<?php

namespace Tests\Feature;

use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Models\VamExpressShipment;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCEvropochtaDeliveryMethodTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $method = 'evropochta'): array
    {
        $order = Order::create(['number' => 'EURO-TRACK', 'status' => 'processing', 'customer_name' => 'Клиент',
            'customer_phone' => '+375291110000', 'delivery_type' => $method, 'payment_type' => 'online_card',
            'payment_status' => 'paid', 'subtotal' => 10, 'total' => 10]);
        $order->forceFill(['evropochta_tracking_number' => 'TEST-TRACK-123',
            'delivery_city' => 'Гродно', 'delivery_address' => 'Адрес получателя, 5',
            'evropochta_quote' => ['parcel' => ['store_id_start' => 612],
                'origin' => ['id' => 612, 'address' => 'г. Минск, ул. Селицкого, 87, №612']]])->save();
        $profile = OneCOrderProfile::create(['name' => 'Розница', 'document' => 'customer', 'customer_mode' => 'retail',
            'is_active' => true, 'is_default' => true, 'header' => ['СпособДоставки' => 'ДоКлиента'], 'row_defaults' => [],
            'meta' => ['keys' => ['СпособДоставки'], 'address' => ['АдресДоставки'], 'info' => ['ДополнительнаяИнформацияПоДоставке']]]);

        return [$order, $profile, app(OneCOrderExport::class)];
    }

    public function test_delivery_info_keeps_buyer_and_separate_recipient_for_one_c(): void
    {
        [$order, $profile, $export] = $this->fixture();
        $quote = $order->evropochta_quote;
        $quote['recipient'] = ['receiver_name' => 'Надежда', 'receiver_surname' => 'Петровская',
            'receiver_patronymic_name' => 'Ивановна', 'receiver_phone_number' => '375336525724'];
        $order->update(['evropochta_quote' => $quote]);
        $info = $export->defaults($order, $profile)['info'];
        $this->assertStringContainsString('Клиент: Клиент', $info);
        $this->assertStringContainsString('Получатель: Петровская Надежда Ивановна', $info);
        $this->assertStringContainsString('Телефон: 375336525724', $info);
    }

    public function test_europochta_defaults_to_our_transport_to_the_carrier_even_for_prepayment(): void
    {
        [$order, $profile, $export] = $this->fixture();
        $form = $export->defaults($order, $profile);
        $this->assertSame('ДоКлиента', $form['delivery']);
        $this->assertSame('ДоКлиента', $export->body($order, $profile, $form)['СпособДоставки']);
        $this->assertSame('ДоКлиента', $profile->fresh()->header['СпособДоставки']);
        $this->assertSame('г. Минск, ул. Селицкого, 87, №612 · TEST-TRACK-123', $form['address']);
        $this->assertStringNotContainsString('Гродно', $form['address']);
        $this->assertStringContainsString('Адрес получателя: Гродно, Адрес получателя, 5', $form['info']);
        $this->assertStringContainsString('Клиент: Клиент', $form['info']);
        $this->assertStringContainsString('+375291110000', $form['info']);
        $this->assertStringContainsString('К оплате 0,00р.', $form['info']);
        foreach ([0, 1, 2, 3, 4] as $variant) {
            $body = $export->body($order, $profile, $form, $variant);
            $this->assertSame($form['address'], $body['АдресДоставки']);
            $this->assertSame('ДоКлиента', $body['СпособДоставки']);
            $this->assertStringContainsString('Гродно', $body['ДополнительнаяИнформацияПоДоставке']);
        }
    }

    public function test_track_is_filled_in_form_and_document_without_duplicate_append(): void
    {
        [$order, $profile, $export] = $this->fixture();
        $form = $export->defaults($order, $profile);
        foreach (['info', 'comment'] as $field) {
            $this->assertSame(1, substr_count($form[$field], 'TEST-TRACK-123'));
        }
        $body = $export->body($order, $profile, $form);
        foreach (['ДополнительнаяИнформацияПоДоставке', 'Комментарий'] as $field) {
            $this->assertSame(1, substr_count($body[$field], 'TEST-TRACK-123'));
        }
        $body = $export->body($order, $profile, ['comment' => 'Мой комментарий'], 4);
        $this->assertStringContainsString('TEST-TRACK-123', $body['Комментарий']);
        $this->assertSame('ДоКлиента', $body['СпособДоставки']);
    }

    public function test_other_delivery_methods_preserve_their_defaults_and_do_not_use_old_europochta_track(): void
    {
        [$order, $profile, $export] = $this->fixture('courier');
        $form = $export->defaults($order, $profile);
        $this->assertSame('ДоКлиента', $form['delivery']);
        $this->assertStringNotContainsString('TEST-TRACK-123', $form['comment']);
        $order->delivery_type = 'pickup';
        $this->assertSame('Самовывоз', $export->defaults($order, $profile)['delivery']);
    }

    public function test_vam_and_legacy_linked_transport_use_the_sending_terminal_and_keep_recipient_details(): void
    {
        [$order, $profile, $export] = $this->fixture('vam_express');
        $shipment = VamExpressShipment::create(['remote_id' => '7bb972d', 'display_id' => '7bb972d']);
        $shipment->forceFill(['order_id' => $order->id])->save();
        $order->update(['payment_type' => 'cash', 'payment_status' => 'pending']);
        foreach (['vam_express', 'transport'] as $method) {
            $order->update(['delivery_type' => $method]);
            $form = $export->defaults($order, $profile);
            $this->assertSame('ДоКлиента', $form['delivery']);
            $this->assertSame('г. Минск, ул. Западная, 19а · 7bb972d', $form['address']);
            $this->assertStringContainsString('Гродно', $form['info']);
            $this->assertStringContainsString('К оплате 10,00р.', $form['info']);
            $this->assertSame(1, substr_count($form['info'], '7bb972d'));
            $this->assertStringNotContainsString('TEST-TRACK-123', $form['info']);
            $body = $export->body($order, $profile, $form, 4);
            $this->assertSame($form['address'], $body['АдресДоставки']);
            $this->assertStringContainsString('7bb972d', $body['Комментарий']);
        }
    }

    public function test_missing_or_changed_sending_branch_never_falls_back_to_the_recipient_or_global_origin(): void
    {
        [$order, $profile, $export] = $this->fixture();
        config(['services.evropochta.token' => null]);
        $quote = $order->evropochta_quote;
        $quote['parcel']['store_id_start'] = 999;
        $order->update(['evropochta_quote' => $quote]);
        $form = $export->defaults($order, $profile);
        $this->assertSame('', $form['address']);
        $this->assertStringContainsString('Не определён терминал', implode(' ', $export->problems($order, $profile, $form)));
    }
}
