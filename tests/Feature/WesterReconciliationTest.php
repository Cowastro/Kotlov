<?php

namespace Tests\Feature;

use App\Console\Commands\ReconcileWesterDuplicatesCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WesterReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function cards(): void
    {
        $brand = Brand::create(['name' => 'Wester', 'slug' => 'wester-review']);
        $category = Category::firstOrCreate(['slug' => 'membrannye-baki'], ['name' => 'Баки', 'parent_id' => 0]);
        foreach (ReconcileWesterDuplicatesCommand::PAIRS as $keep => [$duplicate, $model]) {
            foreach ([$keep, $duplicate] as $id) {
                Product::forceCreate(['id' => $id, 'brand_id' => $brand->id, 'category_id' => $category->id,
                    'sku' => 'BEREG-'.$model, 'name' => 'Бак мембранный Wester '.$model,
                    'slug' => 'wester-review-'.$id, 'is_active' => true, 'price' => 100]);
            }
        }
    }

    public function test_review_is_dry_by_default_and_apply_preserves_records_and_redirects_idempotently(): void
    {
        Storage::fake('local');
        $this->cards();
        $this->artisan('catalog:reconcile-wester-duplicates')->assertSuccessful();
        $this->assertFalse(Product::findOrFail(30814)->is_archived);
        $this->artisan('catalog:reconcile-wester-duplicates', ['--apply' => true])->assertSuccessful();
        foreach (ReconcileWesterDuplicatesCommand::PAIRS as $keep => [$duplicate]) {
            $this->assertFalse(Product::findOrFail($keep)->is_archived);
            $this->assertTrue(Product::findOrFail($duplicate)->is_archived);
            $this->assertDatabaseHas('redirects', ['from_url' => '/membrannye-baki/wester-review-'.$duplicate,
                'to_url' => '/membrannye-baki/wester-review-'.$keep, 'status_code' => 301]);
        }
        $this->assertCount(1, Storage::disk('local')->allFiles('suppliers/td-komplekt'));
        $this->artisan('catalog:reconcile-wester-duplicates', ['--apply' => true])->assertSuccessful();
        $this->assertCount(1, Storage::disk('local')->allFiles('suppliers/td-komplekt'));
    }

    public function test_changed_model_aborts_the_entire_batch(): void
    {
        $this->cards();
        Product::findOrFail(30966)->update(['name' => 'Бак Wester WAV100']);
        $this->artisan('catalog:reconcile-wester-duplicates', ['--apply' => true])->assertFailed();
        $this->assertFalse(Product::findOrFail(30814)->is_archived);
    }
}
