<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FixStubPhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            File::delete($file);
        }
        parent::tearDown();
    }

    public function test_a_copied_bare_file_name_is_relinked_and_a_card_without_file_takes_the_next_diameter(): void
    {
        $category = Category::query()->create(['name' => 'Переходы', 'slug' => 'perehody-adaptery-dymohod', 'is_active' => true]);
        $card = fn (int $id, string $name, array $images) => Product::query()->forceCreate([
            'id' => $id, 'category_id' => $category->id, 'name' => $name, 'slug' => 'stub-test-'.$id, 'sku' => 'KOTLOV-'.$id,
            'price' => 30, 'is_active' => true, 'is_archived' => false, 'images' => $images, 'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        // An old card keeps its file under its own id; a new card got the bare name copied.
        $old = $card(990517, 'Теплов и Сухов Конус Термо КТ-Р 430-0.5/430 D115/200', ['konus-stub-test.jpeg']);
        $this->file($old->imageUrl(0));
        $copied = $card(990528, 'Теплов и Сухов Конус Термо КТ-Р 430-0.5/430 D120/200', ['konus-stub-test.jpeg']);
        $lost = $card(990529, 'Теплов и Сухов Конус Термо КТ-Р 430-0.5/430 D130/200', ['gone-stub-test.jpeg']);

        $this->artisan('catalog:fix-stub-photos', ['--ids' => [$old->id, $copied->id, $lost->id]])
            ->expectsOutputToContain('metric.stub_photos.stub=2')
            ->assertSuccessful();
        $this->assertSame(['konus-stub-test.jpeg'], $copied->fresh()->images);

        $this->artisan('catalog:fix-stub-photos', ['--ids' => [$old->id, $copied->id, $lost->id], '--apply' => true])
            ->expectsOutputToContain("audit.stub_photos.card={$copied->id}:stub-test-{$copied->id}:relinked")
            ->expectsOutputToContain("audit.stub_photos.card={$lost->id}:stub-test-{$lost->id}:sibling")
            ->assertSuccessful();

        $portable = 'product/'.substr($old->imageUrl(0), strlen('/proxy-image/product/'));
        $this->assertSame([$portable], $copied->fresh()->images);
        $this->assertSame([$portable], $lost->fresh()->images);
        $this->assertSame('/proxy-image/'.$portable, $lost->fresh()->imageUrl(0));
    }

    private function file(string $url): void
    {
        $path = public_path('images/'.substr($url, strlen('/proxy-image/')));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'jpeg');
        $this->files[] = $path;
    }
}
