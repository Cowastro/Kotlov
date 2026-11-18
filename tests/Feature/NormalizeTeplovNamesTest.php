<?php

namespace Tests\Feature;

use App\Console\Commands\SyncTeplovSukhovRetailPricesCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class NormalizeTeplovNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_are_renamed_after_a_review_and_can_be_restored(): void
    {
        Storage::fake('local');
        $brand = $this->brand();
        $category = Category::create(['name' => 'Колена и отводы моно', 'slug' => 'tis-names-elbows', 'is_active' => true]);
        $card = fn (string $name, string $slug) => Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => $name, 'slug' => $slug,
            'h1' => $name, 'price' => 50, 'is_active' => true, 'is_archived' => false]);
        $elbow = $card('Отвод моно ТиС ОМ-Р 45*, 430, 0.8, Ø 150', 'tis-elbow-150');
        $tank = $card('Бак навесной ТиС Стандарт 80 горизонтальный (201-0,8)', 'tis-tank');
        $apronA = $card('Фартук Теплов и сухов 180', 'tis-apron-a');
        $apronB = $card('ТиС Фартук D 180 (М)', 'tis-apron-b');
        $linked = $card('ТиС Отвод 87* ОМ-Р 430-0.5 D150 (3S)', 'tis-elbow-87');
        $teplov = Supplier::query()->updateOrCreate(['code' => 'teplov'], ['name' => 'Теплов и Сухов', 'currency' => 'BYN', 'is_active' => true]);
        // The price list row of the 87° elbow: the new name still passes the link check.
        SupplierProduct::create(['supplier_id' => $teplov->id, 'product_id' => $linked->id, 'supplier_article' => 'TS.1', 'match_status' => 'matched',
            'match_confidence' => 'verified_name_dimensions', 'supplier_name' => 'Отвод 87° ОМ-Р 430-0.5 D150 (3S)']);

        $this->artisan('catalog:normalize-teplov-names', ['--export' => 'tis-names-test'])
            ->expectsOutputToContain('metric.teplov_names.rename=2')
            ->expectsOutputToContain('metric.teplov_names.possible_duplicate=2')
            ->expectsOutputToContain('metric.teplov_names.not_recognised=1')
            ->expectsOutputToContain('audit.teplov_names.export=exports/tis-names-test.xlsx')
            ->assertSuccessful();
        $this->assertSame('Отвод моно ТиС ОМ-Р 45*, 430, 0.8, Ø 150', $elbow->fresh()->name);
        $rows = IOFactory::load(public_path('exports/tis-names-test.xlsx'))->getActiveSheet()->toArray();
        $this->assertSame(['ID', 'Раздел', 'Сейчас', 'Станет', 'Что делаем'], $rows[0]);
        $row = collect($rows)->first(fn (array $row) => (int) $row[0] === $elbow->id);
        $this->assertSame(['Колена и отводы моно', 'Отвод моно ТиС ОМ-Р 45*, 430, 0.8, Ø 150', 'Отвод Моно Теплов и Сухов ОМ-Р 45°, 430-0,8, Ø 150', 'переименовать'], array_slice($row, 1));

        $this->artisan('catalog:normalize-teplov-names', ['--apply' => true, '--export' => 'tis-names-test'])
            ->expectsOutputToContain('metric.teplov_names.updated=2')->assertSuccessful();
        $this->assertSame('Отвод Моно Теплов и Сухов ОМ-Р 45°, 430-0,8, Ø 150', $elbow->fresh()->name);
        $this->assertSame('Отвод Моно Теплов и Сухов ОМ-Р 45°, 430-0,8, Ø 150', $elbow->fresh()->h1);
        $this->assertSame('tis-elbow-150', $elbow->fresh()->slug);
        $this->assertSame('Отвод Моно Теплов и Сухов ОМ-Р 87°, 430-0,5, Ø 150', $linked->fresh()->name);
        $this->assertSame('Фартук Теплов и сухов 180', $apronA->fresh()->name);
        $this->assertSame('Бак навесной ТиС Стандарт 80 горизонтальный (201-0,8)', $tank->fresh()->name);

        $backup = basename(Storage::disk('local')->files('catalog')[0]);
        $this->artisan('catalog:normalize-teplov-names', ['--restore' => $backup])->expectsOutputToContain('metric.teplov_names.restored=2')->assertSuccessful();
        $this->assertSame('Отвод моно ТиС ОМ-Р 45*, 430, 0.8, Ø 150', $elbow->fresh()->name);
        $this->assertSame('ТиС Фартук D 180 (М)', $apronB->fresh()->name);
        @unlink(public_path('exports/tis-names-test.xlsx'));
    }

    public function test_a_card_whose_price_list_link_would_break_keeps_its_name(): void
    {
        $brand = $this->brand();
        $category = Category::create(['name' => 'Шиберы', 'slug' => 'tis-names-gates', 'is_active' => true]);
        $gate = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Шибер моно ТиС ШМ(М)-Р 430, 0.8, Ø 120',
            'slug' => 'tis-gate', 'price' => 50, 'is_active' => true, 'is_archived' => false]);
        $teplov = Supplier::query()->updateOrCreate(['code' => 'teplov'], ['name' => 'Теплов и Сухов', 'currency' => 'BYN', 'is_active' => true]);
        SupplierProduct::create(['supplier_id' => $teplov->id, 'product_id' => $gate->id, 'supplier_article' => 'TS.2', 'match_status' => 'matched',
            'match_confidence' => 'verified_name_dimensions', 'supplier_name' => 'Шибер ШМ(М)-Р 430-0.8 D120']);
        // The price list check passes for the old name only.
        $this->mock(SyncTeplovSukhovRetailPricesCommand::class, fn ($mock) => $mock->shouldIgnoreMissing()->shouldReceive('cardNameMatches')
            ->andReturnUsing(fn (string $card) => ! str_contains($card, 'Теплов и Сухов')));

        $this->artisan('catalog:normalize-teplov-names', ['--apply' => true, '--export' => 'tis-names-test2'])
            ->expectsOutputToContain('metric.teplov_names.keeps_price_link=1')
            ->expectsOutputToContain('metric.teplov_names.updated=0')->assertSuccessful();
        @unlink(public_path('exports/tis-names-test2.xlsx'));
        $this->assertSame('Шибер моно ТиС ШМ(М)-Р 430, 0.8, Ø 120', $gate->fresh()->name);
    }

    /** The brand of the migrations, without the cards they made. */
    private function brand(): Brand
    {
        $brand = Brand::query()->firstOrCreate(['name' => 'Теплов и Сухов'], ['slug' => 'tis-names-test']);
        $ids = Product::query()->where('brand_id', $brand->id)->pluck('id');
        SupplierProduct::query()->whereIn('product_id', $ids)->delete();
        Product::query()->whereIn('id', $ids)->delete();

        return $brand;
    }
}
