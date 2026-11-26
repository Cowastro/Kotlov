<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\BoilerAutomationGroups as Groups;
use App\Support\Catalog\WaterHeaterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BufferTankVolumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tanks_without_a_volume_spec_get_their_model_volume(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => WaterHeaterCatalog::BUFFER], ['name' => 'Буферные ёмкости', 'parent_id' => 0, 'is_active' => true]);
        $nado = Product::query()->create(['name' => 'Буферная ёмкость Drazice NADO 1000 /140 v1', 'slug' => 'bufernaia-emkost-drazice-nado-1000-140-v1', 'category_id' => $category->id, 'price' => 100, 'is_active' => true,
            'specs' => [['key' => 'Объем бойлера ГВ', 'value' => '1,43 м2']]]);
        $kept = Product::query()->create(['name' => 'Буферная ёмкость Aquastic AQ PT 500 ErP', 'slug' => 'bufernaia-emkost-aquastic-aq-pt-500-erp', 'category_id' => $category->id, 'price' => 100, 'is_active' => true,
            'specs' => [['key' => 'Объем', 'value' => '480 л']]]);

        (require database_path('migrations/2026_11_30_120000_add_buffer_tank_volumes.php'))->up();

        $catalog = app(WaterHeaterCatalog::class);
        $facts = $catalog->productFacts($nado->fresh()->load('category', 'allAttributeValues'), WaterHeaterCatalog::BUFFER, $catalog->dimensions(WaterHeaterCatalog::BUFFER));
        $this->assertSame(['to-1000'], $facts['options']['volume']);
        $this->assertSame([['key' => 'Объем', 'value' => '480 л']], $kept->fresh()->specs);
        $this->assertSame('Со встроенным баком ГВС', Groups::match($nado->name, Groups::BUFFER_TYPES));
        $this->assertSame('Холодоаккумуляторы', Groups::match('Холодоаккумулятор S-TANK CT 1000', Groups::BUFFER_TYPES));
        $this->assertSame('Буферные ёмкости и теплоаккумуляторы', Groups::match('Буферная емкость S-TANK AT-500', Groups::BUFFER_TYPES));
    }
}
