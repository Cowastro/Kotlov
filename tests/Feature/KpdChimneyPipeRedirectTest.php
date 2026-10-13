<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpdChimneyPipeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_inactive_mislabeled_shiber_url_redirects_to_the_exact_kpd_pipe(): void
    {
        $legacyCategory = Category::query()->firstOrCreate(
            ['slug' => 'dymohody-nerzhaveyushchie'],
            ['name' => 'Дымоходы', 'parent_id' => 0],
        );
        $canonicalCategory = Category::query()->firstOrCreate(
            ['slug' => 'truby-mono'],
            ['name' => 'Трубы одностенные', 'parent_id' => 0],
        );
        $legacy = Product::query()->updateOrCreate(
            ['sku' => 'KOTLOV-022436'],
            [
                'category_id' => $legacyCategory->id,
                'name' => 'КПД КПД ЧЕРНЫЙ Шибер 1000мм 2мм ф120',
                'slug' => 'kpd-kpd-cernyi-siber-1000mm-2mm-f120',
                'price' => 138,
                'is_active' => false,
                'is_archived' => false,
            ],
        );
        Product::query()->updateOrCreate(
            ['sku' => 'KOTLOV-022646'],
            [
                'category_id' => $canonicalCategory->id,
                'name' => 'КПД ЧЁРНЫЙ Труба дымоходная 1000 мм, Ø120, 2 мм',
                'slug' => 'kpd-cernyi-truba-dymoxodnaia-1000-mm-o120-2-mm',
                'price' => 99,
                'is_active' => true,
                'is_archived' => false,
            ],
        );

        $migration = require database_path(
            'migrations/2026_10_13_180000_redirect_inactive_kpd_shiber_to_exact_pipe.php'
        );
        $migration->up();

        $legacy->refresh();
        $this->assertFalse((bool) $legacy->is_active);
        $this->assertTrue((bool) $legacy->is_archived);
        $this->assertFalse((bool) $legacy->in_stock);
        $this->assertSame('out_of_stock', $legacy->availability_status);
        $this->assertNull($legacy->stock_qty);
        $this->assertDatabaseHas('redirects', [
            'from_url' => '/dymohody-nerzhaveyushchie/kpd-kpd-cernyi-siber-1000mm-2mm-f120',
            'to_url' => '/truby-mono/kpd-cernyi-truba-dymoxodnaia-1000-mm-o120-2-mm',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/dymohody-nerzhaveyushchie/kpd-kpd-cernyi-siber-1000mm-2mm-f120')
            ->assertRedirect('/truby-mono/kpd-cernyi-truba-dymoxodnaia-1000-mm-o120-2-mm')
            ->assertStatus(301);
    }
}
