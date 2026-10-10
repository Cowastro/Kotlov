<?php

namespace Tests\Unit;

use App\Models\IntegrationSource;
use App\Services\Integrations\IntegrationOrderStatusMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IntegrationOrderStatusMapperTest extends TestCase
{
    #[DataProvider('fallbackOrderStatuses')]
    public function test_it_preserves_safe_default_order_status_recognition(string $raw, ?string $expected): void
    {
        $this->assertSame($expected, (new IntegrationOrderStatusMapper)->orderStatus($raw));
    }

    public static function fallbackOrderStatuses(): array
    {
        return [
            ['Новый', 'new'],
            ['Подтверждён', 'confirmed'],
            ['В комплектации', 'processing'],
            ['Отгружен', 'shipped'],
            ['Доставлен', 'delivered'],
            ['Отменён', 'cancelled'],
            ['Передан логисту', null],
        ];
    }

    public function test_exact_source_rules_override_defaults_and_normalize_whitespace_and_case(): void
    {
        $source = new IntegrationSource([
            'settings' => [
                'order_status_rules' => [
                    ['source' => '  ПРИНЯТ   СКЛАДОМ ', 'target' => 'processing'],
                    ['source' => 'Принят', 'target' => 'processing'],
                ],
                'payment_status_rules' => [
                    ['source' => 'Проведена кассой', 'target' => 'paid'],
                ],
            ],
        ]);
        $mapper = new IntegrationOrderStatusMapper;

        $this->assertSame('processing', $mapper->orderStatus('принят складом', $source));
        $this->assertSame('processing', $mapper->orderStatus('Принят', $source));
        $this->assertSame('paid', $mapper->paymentStatus('проведена   кассой', $source));
    }

    public function test_invalid_configured_target_is_ignored_instead_of_changing_an_order(): void
    {
        $source = new IntegrationSource([
            'settings' => [
                'order_status_rules' => [
                    ['source' => 'Передан логисту', 'target' => 'delete-order'],
                ],
            ],
        ]);

        $this->assertNull((new IntegrationOrderStatusMapper)->orderStatus('Передан логисту', $source));
    }
}
