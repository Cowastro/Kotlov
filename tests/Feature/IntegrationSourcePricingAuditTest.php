<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\IntegrationSources\Pages\EditIntegrationSource;
use App\Filament\Resources\IntegrationSources\RelationManagers\PricingChangesRelationManager;
use App\Models\IntegrationSource;
use App\Models\IntegrationSourcePricingChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class IntegrationSourcePricingAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_rule_changes_are_recorded_with_actor_and_normalized_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'pricing-audit',
            'name' => 'Источник аудита',
            'update_prices' => false,
            'settings' => [
                'price_tax_mode' => 'exclusive',
                'vat_rate' => 20,
                'b2b_enabled' => false,
            ],
        ]);

        $this->actingAs($admin);
        $settings = $source->settings;
        $settings['price_tax_mode'] = 'inclusive';
        $settings['vat_rate'] = 10;
        $settings['b2b_enabled'] = true;
        $settings['partner_name'] = 'Партнёрский поставщик';
        $source->update([
            'update_prices' => true,
            'settings' => $settings,
        ]);

        $change = IntegrationSourcePricingChange::query()->sole();

        $this->assertSame($source->id, $change->integration_source_id);
        $this->assertSame($admin->id, $change->user_id);
        $this->assertSame($admin->name, $change->actor_name);
        $this->assertSame([
            'update_prices',
            'settings.price_tax_mode',
            'settings.vat_rate',
            'settings.b2b_enabled',
            'settings.partner_name',
        ], $change->changed_fields);
        $this->assertFalse($change->before_values['update_prices']);
        $this->assertTrue($change->after_values['update_prices']);
        $this->assertSame('exclusive', $change->before_values['settings.price_tax_mode']);
        $this->assertSame('inclusive', $change->after_values['settings.price_tax_mode']);
        $this->assertSame(20, $change->before_values['settings.vat_rate']);
        $this->assertSame(10, $change->after_values['settings.vat_rate']);
    }

    public function test_unrelated_source_settings_do_not_create_pricing_history(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'pricing-audit-unrelated',
            'name' => 'Другой источник',
            'settings' => ['price_tax_mode' => 'exclusive', 'vat_rate' => 20],
        ]);
        $settings = $source->settings;
        $settings['catalog_interval_minutes'] = 15;

        $source->update(['settings' => $settings]);

        $this->assertDatabaseCount('integration_source_pricing_changes', 0);
    }

    public function test_currency_and_rate_changes_are_recorded(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'pricing-audit-currency',
            'name' => 'Валютный источник',
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
        ]);

        $source->update([
            'price_currency' => 'EUR',
            'price_currency_rate' => 3.45,
        ]);

        $change = IntegrationSourcePricingChange::query()->sole();

        $this->assertSame(['price_currency', 'price_currency_rate'], $change->changed_fields);
        $this->assertSame('BYN', $change->before_values['price_currency']);
        $this->assertSame('EUR', $change->after_values['price_currency']);
        $this->assertEquals(1.0, $change->before_values['price_currency_rate']);
        $this->assertEquals(3.45, $change->after_values['price_currency_rate']);
        $this->assertStringContainsString('Валюта входной цены', $change->changeSummary());
        $this->assertStringContainsString('Курс к BYN', $change->changeSummary());
    }

    public function test_pricing_history_is_immutable(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'pricing-audit-immutable',
            'name' => 'Неизменяемый аудит',
        ]);
        $source->update(['update_prices' => true]);
        $change = IntegrationSourcePricingChange::query()->sole();

        $this->expectException(LogicException::class);
        $change->update(['actor_name' => 'Подмена']);
    }

    public function test_pricing_history_cannot_be_deleted_separately(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'pricing-audit-delete',
            'name' => 'Аудит без удаления',
        ]);
        $source->update(['update_prices' => true]);
        $change = IntegrationSourcePricingChange::query()->sole();

        $this->expectException(LogicException::class);
        $change->delete();
    }

    public function test_admin_edit_screen_exposes_read_only_pricing_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'pricing-audit-screen',
            'name' => 'Источник на экране',
        ]);
        $this->actingAs($admin);
        $source->update(['update_prices' => true]);

        $this->get(IntegrationSourceResource::getUrl('edit', ['record' => $source], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Валюта входной цены')
            ->assertSeeText('Курс к BYN')
            ->assertSeeText('не переписывает историю молча');

        Livewire::actingAs($admin)
            ->test(PricingChangesRelationManager::class, [
                'ownerRecord' => $source,
                'pageClass' => EditIntegrationSource::class,
            ])
            ->assertSuccessful()
            ->assertSeeText('История правил цен и НДС')
            ->assertSeeText('Обновление цен из источника')
            ->assertSeeText($admin->name);

        $this->assertSame(
            [PricingChangesRelationManager::class],
            IntegrationSourceResource::getRelations(),
        );
    }
}
