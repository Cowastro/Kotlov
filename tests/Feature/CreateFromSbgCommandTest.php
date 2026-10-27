<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\Marketplaces\DealByClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateFromSbgCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_row_linked_to_a_wrong_card_moves_to_a_card_made_from_sbg(): void
    {
        $category = Category::query()->create(['name' => 'Аксессуары для бани', 'slug' => 'aksessuary-dlya-bani', 'is_active' => true]);
        $stove = Product::query()->create(['category_id' => $category->id, 'name' => 'Печь-каменка Царь-печи Емеля', 'slug' => 'pech-emelya', 'price' => 934, 'is_active' => true]);
        $source = IntegrationSource::query()->firstOrCreate(['code' => 'onec'], ['name' => '1С']);
        $item = IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'x-9074', 'external_code' => 'УТ-00009074',
            'name' => 'Каменка Емеля', 'product_id' => $stove->id, 'match_status' => 'matched', 'match_method' => 'manual_search']);
        $this->mock(DealByClient::class, fn ($mock) => $mock->shouldReceive('listProducts')->andReturn(['products' => [
            ['id' => 1, 'sku' => '4603789200959', 'name' => 'Каменка навесная Емеля', 'price' => 104, 'description' => '<p>Навесная каменка.</p>',
                'main_image' => 'https://images.deal.by/1_w328_h250_kamenka.jpg'],
        ]]));
        Http::fake(['images.deal.by/*' => Http::response(str_repeat('x', 10), 200, ['Content-Type' => 'image/jpeg'])]);

        $this->artisan('integration:create-from-sbg', ['pairs' => ['УТ-00009074:4603789200959:aksessuary-dlya-bani']])
            ->expectsOutputToContain('audit.create_from_sbg.skipped=ut-00009074:linked')
            ->assertSuccessful();

        $this->artisan('integration:create-from-sbg', ['pairs' => ['УТ-00009074:4603789200959:aksessuary-dlya-bani'], '--relink' => true, '--apply' => true])
            ->expectsOutputToContain("audit.create_from_sbg.card={$item->id}:kamenka-navesnaia-emelia:104.00:aksessuary-dlya-bani:1")
            ->expectsOutputToContain('metric.create_from_sbg.created=1')
            ->assertSuccessful();

        $card = Product::query()->find($item->fresh()->product_id);
        $this->assertNotSame($stove->id, $card->id);
        $this->assertSame('Каменка навесная Емеля', $card->name);
        $this->assertSame(104.0, (float) $card->price);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }
}
