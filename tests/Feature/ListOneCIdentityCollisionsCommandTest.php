<?php

namespace Tests\Feature;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ListOneCIdentityCollisionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SAFE = '/^(metric\.onec_collisions\.[a-z_]+=[0-9]+|audit\.onec_collisions\.(group=[0-9]+:[a-z_]+~[a-z0-9-]+(,[a-z_]+~[a-z0-9-]+)*:rows=[0-9]+:cards=[0-9]+:char=[01]|row=[0-9]+:[0-9]+:[a-z0-9-]+:[a-z0-9-]+:[a-z_]+:-?[0-9]+:[0-9]+:[0-9]+))$/';

    public function test_rows_sharing_an_article_are_listed_as_lines_the_workflow_lets_through(): void
    {
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C', 'driver' => 'commerceml', 'is_active' => true]);
        foreach ([['a-1', 'УТ-1', 'Котёл Тест 20'], ['a-2', 'УТ-2', 'Котёл Тест 20 (дубль)']] as [$id, $code, $name]) {
            IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => $id, 'external_code' => $code,
                'external_sku' => 'KT-20', 'name' => $name, 'match_status' => 'unmatched', 'stock_quantity' => 2]);
        }
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'b-1', 'external_code' => 'УТ-3',
            'external_sku' => 'OTHER-1', 'name' => 'Другой', 'match_status' => 'unmatched']);

        Artisan::call('integration:identity-collisions');
        $lines = array_values(array_filter(preg_split('/\R/', trim(Artisan::output()))));

        foreach ($lines as $line) {
            $this->assertMatchesRegularExpression(self::SAFE, $line);
        }
        $output = implode("\n", $lines);
        $this->assertStringContainsString('audit.onec_collisions.group=1:sku~kt-20:rows=2:cards=0:char=0', $output);
        $this->assertStringContainsString(':ut-2:kotel-test-20-dubl:unmatched:2:0:0', $output);
        $this->assertStringContainsString('metric.onec_collisions.groups=1', $output);
        $this->assertStringContainsString('metric.onec_collisions.stocked_rows=2', $output);
    }
}
