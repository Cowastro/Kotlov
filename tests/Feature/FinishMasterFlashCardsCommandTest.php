<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishMasterFlashCardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_completes_copy_and_specs_without_replacing_the_verified_photo_or_price(): void
    {
        $this->makeCards();

        $this->artisan('catalog:finish-master-flash-cards', ['--apply' => true])
            ->expectsOutput('metric.master_flash.expected=14')
            ->expectsOutput('metric.master_flash.found=14')
            ->expectsOutput('metric.master_flash.with_images=14')
            ->assertSuccessful();

        $res = Product::query()->where('sku', 'KOTLOV-030303')->firstOrFail();
        $this->assertSame('Мастер-флеш RES №2 угловой 203–280 мм, силикон, синий', $res->name);
        $this->assertSame(['/storage/products/30303/colour-photo.webp'], $res->images);
        $this->assertSame(70.0, (float) $res->price);
        $this->assertStringContainsString('защиты места примыкания', $res->content);
        $this->assertSame('600×600 мм', collect($res->specs)->firstWhere('key', 'Размер основания')['value']);

        $aston = Product::query()->where('sku', 'KOTLOV-030301')->firstOrFail();
        $this->assertSame('ASTON', $aston->brand?->name);
        $this->assertSame(0.0, (float) $aston->price);
        $this->assertStringContainsString('200–280 мм', $aston->short_description);
    }

    public function test_preview_does_not_write_changes(): void
    {
        $this->makeCards();

        $this->artisan('catalog:finish-master-flash-cards')->assertSuccessful();

        $this->assertSame('Черновая карточка', Product::query()->where('sku', 'KOTLOV-030290')->value('name'));
    }

    private function makeCards(): void
    {
        foreach (range(30290, 30303) as $number) {
            Product::query()->create([
                'name' => 'Черновая карточка',
                'slug' => 'master-flash-'.$number,
                'sku' => 'KOTLOV-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'price' => $number === 30301 ? 0 : 70,
                'images' => ["/storage/products/{$number}/colour-photo.webp"],
                'is_active' => true,
            ]);
        }
    }
}
