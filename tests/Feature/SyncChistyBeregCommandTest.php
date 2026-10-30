<?php

namespace Tests\Feature;

use App\Filament\Resources\Suppliers\Pages\ViewSupplier;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Suppliers\ChistyBeregPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

class SyncChistyBeregCommandTest extends TestCase
{
    use RefreshDatabase;

    private function workbook(array $rows): string
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Иерархия')->fromArray([['Отчет']]);
        $list = $book->createSheet()->setTitle('Список');
        $list->fromArray(array_merge([
            [null, 'Отчет', 'Цены сформированы без учета 125 Постановления'],
            [],
            [null, 'Код', 'Категория', 'Бренд', 'Номенклатура', 'Код производителя', 'Единица измерения', 'Кол-во в упаковке', 'Остаток', 'Резерв', 'Свободно', 'Цена ОПТ с НДC, BYN', 'РРЦ с НДС, BYN'],
        ], $rows));
        $path = tempnam(sys_get_temp_dir(), 'cb').'.xls';
        (new Xls($book))->save($path);

        return $path;
    }

    public function test_rows_become_offers_linked_only_by_a_sure_key_of_the_same_brand(): void
    {
        $category = Category::query()->create(['name' => 'Баки', 'slug' => 'membrannye-baki', 'is_active' => true]);
        $flamco = Brand::query()->create(['name' => 'FLAMCO', 'slug' => 'flamco-cb', 'is_active' => true]);
        $other = Brand::query()->create(['name' => 'Wester', 'slug' => 'wester-cb', 'is_active' => true]);
        $byCode = Product::query()->create(['category_id' => $category->id, 'brand_id' => $flamco->id, 'name' => 'Бак Flamco Flexcon R 25', 'slug' => 'flexcon-25', 'sku' => 'FL-16253', 'price' => 0, 'is_active' => true]);
        $byName = Product::query()->create(['category_id' => $category->id, 'name' => 'Кронштейн настенный для расширительного бака 3/4" регулируемый Россия', 'slug' => 'kronshtein', 'price' => 0, 'is_active' => true]);
        $otherBrand = Product::query()->create(['category_id' => $category->id, 'brand_id' => $other->id, 'name' => 'Бак Wester WRV 50', 'slug' => 'wrv-50', 'sku' => 'WRV-50', 'price' => 300, 'is_active' => true]);
        $supplier = Supplier::query()->where('code', 'chisty_bereg')->firstOrFail();

        $path = $this->workbook([
            [null, '00111885', 'Баки расширительные мембранные', 'FLAMCO', 'Бак мембранный Flexcon R 25 л', 'FL-16253', 'шт.', null, 5, 1, 4, 80.5, 99.9],
            [null, '00142022', 'Баки расширительные мембранные', null, 'Кронштейн настенный для расширительного бака 3/4" регулируемый Россия', 'кррб-3/4', 'шт.', null, 16, 2, 14, 12.7, 14.54],
            [null, '00150000', 'Баки расширительные мембранные', 'REFLEX', 'Бак Reflex 50', 'WRV-50', 'шт.', null, 2, 0, 2, 200, 250],
        ]);

        $this->artisan('supplier:sync-chisty-bereg', ['--file' => $path])
            ->expectsOutputToContain('metric.chisty_bereg.maker_code=1')
            ->expectsOutputToContain('metric.chisty_bereg.exact_name=1')
            ->expectsOutputToContain('metric.chisty_bereg.unlinked=1')
            ->assertSuccessful();
        $this->assertSame(0, SupplierProduct::query()->where('supplier_id', $supplier->id)->count());

        $this->artisan('supplier:sync-chisty-bereg', ['--file' => $path, '--apply' => true])->assertSuccessful();

        $offer = SupplierProduct::query()->where('supplier_article', '00111885')->firstOrFail();
        $this->assertSame($byCode->id, $offer->product_id);
        $this->assertSame(80.5, (float) $offer->price_byn);
        $this->assertSame(4.0, (float) $offer->stock_quantity);
        $this->assertSame('FL-16253', data_get($offer->raw, 'maker_code'));
        $this->assertSame(99.9, (float) $byCode->fresh()->price);
        $this->assertSame($byName->id, SupplierProduct::query()->where('supplier_article', '00142022')->value('product_id'));
        // The same manufacturer code on a card of another brand is not a link.
        $this->assertNull(SupplierProduct::query()->where('supplier_article', '00150000')->value('product_id'));
        $this->assertSame(300.0, (float) $otherBrand->fresh()->price);

        // Marks of the background jobs survive the next file.
        $kept = SupplierProduct::query()->where('supplier_article', '00142022')->firstOrFail();
        $kept->forceFill(['raw' => array_merge((array) $kept->raw, ['photo_searched_at' => '2026-10-30 06:00:00', 'specs_refilled_at' => '2026-10-30 06:00:00'])])->save();

        // Tomorrow the row is gone from the file: no stock.
        $tomorrow = $this->workbook([[null, '00142022', 'Баки расширительные мембранные', null, 'Кронштейн настенный для расширительного бака 3/4" регулируемый Россия', 'кррб-3/4', 'шт.', null, 10, 0, 10, 12.7, 14.54]]);
        $this->artisan('supplier:sync-chisty-bereg', ['--file' => $tomorrow, '--apply' => true])
            ->expectsOutputToContain('metric.chisty_bereg.gone_from_file=2')
            ->assertSuccessful();
        $this->assertFalse((bool) $offer->fresh()->in_stock);
        $this->assertSame('2026-10-30 06:00:00', data_get($kept->fresh()->raw, 'photo_searched_at'));
        $this->assertSame('2026-10-30 06:00:00', data_get($kept->fresh()->raw, 'specs_refilled_at'));
        $this->assertSame($byCode->id, $offer->fresh()->product_id);
        @unlink($path);
        @unlink($tomorrow);
    }

    public function test_the_morning_file_is_uploaded_on_the_supplier_page_and_synced_once(): void
    {
        Storage::fake('local');
        $supplier = Supplier::query()->where('code', 'chisty_bereg')->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $path = $this->workbook([[null, '00142022', 'Баки', null, 'Кронштейн настенный', 'кррб-3/4', 'шт.', null, 16, 2, 14, 12.7, 14.54]]);

        Livewire::actingAs($admin)
            ->test(ViewSupplier::class, ['record' => $supplier->getRouteKey()])
            ->callAction('chisty_bereg_stock', ['file' => UploadedFile::fake()->createWithContent('cb.xls', (string) file_get_contents($path))])
            ->assertHasNoActionErrors();
        @unlink($path);

        $this->assertNotNull(ChistyBeregPriceList::storedPath());
        $this->artisan('supplier:sync-chisty-bereg', ['--apply' => true, '--if-new' => true])->expectsOutputToContain('metric.chisty_bereg.offers=1')->assertSuccessful();
        $this->artisan('supplier:sync-chisty-bereg', ['--apply' => true, '--if-new' => true])->expectsOutputToContain('metric.chisty_bereg.new_file=0')->assertSuccessful();
    }

    public function test_the_one_card_of_the_brand_with_the_same_model_is_suggested_not_linked(): void
    {
        $this->assertSame(
            \App\Console\Commands\SyncChistyBeregCommand::modelKey('Насос циркуляционный IMP Pumps GHN 25/60-180', 'imppumps'),
            \App\Console\Commands\SyncChistyBeregCommand::modelKey('Циркуляционный насос IMP PUMPS GHN 25/60-180', 'imppumps'),
        );
        $category = Category::query()->create(['name' => 'Насосы', 'slug' => 'tsirkulyatsionnyie', 'is_active' => true]);
        $imp = Brand::query()->create(['name' => 'IMP Pumps', 'slug' => 'imp-pumps-cb', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'brand_id' => $imp->id, 'name' => 'Циркуляционный насос IMP PUMPS GHN 25/60-180', 'slug' => 'ghn-25-60', 'price' => 400, 'is_active' => true]);
        Product::query()->create(['category_id' => $category->id, 'brand_id' => $imp->id, 'name' => 'Циркуляционный насос IMP PUMPS GHN 25/80-180', 'slug' => 'ghn-25-80', 'price' => 450, 'is_active' => true]);
        $path = $this->workbook([[null, '00120001', 'Насосы', 'IMP Pumps', 'Насос циркуляционный IMP Pumps GHN 25/60-180', '979521725', 'шт.', null, 3, 0, 3, 300, 380]]);

        $this->artisan('supplier:sync-chisty-bereg', ['--file' => $path, '--apply' => true])->assertSuccessful();
        @unlink($path);

        $offer = SupplierProduct::query()->where('supplier_article', '00120001')->firstOrFail();
        $this->assertNull($offer->product_id);
        $this->assertSame('suggested', $offer->match_status);
        $this->assertSame($card->id, data_get($offer->raw, 'suggested_product_id'));
        $this->assertSame(400.0, (float) $card->fresh()->price);

        // A manager confirms the suggestion on the supplier page.
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->where('code', 'chisty_bereg')->firstOrFail();
        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Suppliers\RelationManagers\SupplierProductsRelationManager::class, ['ownerRecord' => $supplier, 'pageClass' => ViewSupplier::class])
            ->filterTable('match_status', 'suggested')
            ->assertCanSeeTableRecords([$offer])
            ->callTableAction('confirmSuggestion', $offer);
        $this->assertSame($card->id, $offer->fresh()->product_id);
        $this->assertSame('manual_admin', $offer->fresh()->match_confidence);
    }
}
