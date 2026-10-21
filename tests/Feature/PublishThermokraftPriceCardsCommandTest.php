<?php

namespace Tests\Feature;

use App\Console\Commands\PublishThermokraftPriceCardsCommand;
use App\Console\Commands\SyncThermokraftCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PublishThermokraftPriceCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_missing_price_cards_once_and_the_sync_links_them(): void
    {
        $items = json_decode((string) file_get_contents(resource_path(PublishThermokraftPriceCardsCommand::DATA_FILE)), true);
        foreach (collect($items)->pluck('category_slugs')->map(fn ($slugs) => $slugs[0])->unique() as $slug) {
            Category::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => 0, 'is_active' => true]);
        }
        $wilo = Brand::query()->create(['name' => 'WILO', 'slug' => 'wilo', 'is_active' => true]);
        $boilers = Category::query()->where('slug', 'tverdotoplivnye')->sole();
        $existing = Product::query()->create([
            'category_id' => $boilers->id, 'is_active' => true, 'price' => 3000,
            'name' => 'Greolit Котел Greolit DEEP plus 20 кВт с автоматикой', 'slug' => 'greolit-kotel-greolit-deep-plus-20-kvt-s-avtomatikoi',
        ]);
        $before = Product::query()->count();

        $this->artisan('catalog:publish-thermokraft-price-cards')
            ->expectsOutputToContain('metric.thermokraft_cards.create='.count($items))
            ->expectsOutputToContain('audit.thermokraft_cards.item=1:create:tverdotoplivnye:new:')
            ->assertSuccessful();
        $this->assertSame($before, Product::query()->count());

        $this->artisan('catalog:publish-thermokraft-price-cards --apply')->assertSuccessful();
        $this->artisan('catalog:publish-thermokraft-price-cards --apply')
            ->expectsOutputToContain('metric.thermokraft_cards.present='.count($items))
            ->assertSuccessful();
        $this->assertSame($before + count($items), Product::query()->count());

        $pump = Product::query()->where('slug', 'cirkulyacionnyi-nasos-wilo-star-rs-25-4-s-gaikami')->sole();
        $this->assertSame($wilo->id, $pump->brand_id);
        $this->assertSame('410.00', (string) $pump->price);
        $tank = Product::query()->where('slug', 'rasshiritelnyi-bak-reflex-ng-8')->sole();
        $this->assertSame('7230107', $tank->sku);
        $this->assertSame('reflex', $tank->brand->slug);
        $this->assertNotEmpty($tank->images);

        $classify = new ReflectionMethod(SyncThermokraftCommand::class, 'classify');
        [$rows] = $classify->invoke(new SyncThermokraftCommand, [
            $this->row('Печь отопительная OGNIVO Mini', 'Термокрафт'),
            $this->row('Котел Greolit DEEP PLUS 20 кВт с автоматикой', 'Greolite'),
        ]);
        $this->assertSame(Product::query()->where('slug', 'pech-otopitelnaya-termokraft-ognivo-mini')->value('id'), $rows[0]['product_id']);
        $this->assertSame('card_alias', $rows[0]['match_reason']);
        $this->assertSame($existing->id, $rows[1]['product_id']);
    }

    private function row(string $name, string $brand): array
    {
        return ['name' => $name, 'brand' => $brand, 'article_key' => 'X'.md5($name), 'barcode' => '', 'valid_price' => true, 'in_stock' => false];
    }
}
