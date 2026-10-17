<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSubcategoryAlphabeticalOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_subcategories_are_alphabetical_in_filter_and_catalog_grid(): void
    {
        $parent = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Каталог для проверки алфавита',
            'slug' => 'alphabetical-subcategories-test',
            'is_active' => true,
        ]);

        $subcategories = [
            ['name' => 'Фильтры', 'slug' => 'alphabetical-filters-test', 'sort_order' => 1],
            ['name' => 'Гибкие воздуховоды', 'slug' => 'alphabetical-ducts-test', 'sort_order' => 2],
            ['name' => 'Буферные емкости', 'slug' => 'alphabetical-tanks-test', 'sort_order' => 3],
        ];

        foreach ($subcategories as $index => $data) {
            $subcategory = Category::query()->create([
                'parent_id' => $parent->id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sort_order' => $data['sort_order'],
                'is_active' => true,
            ]);

            Product::query()->create([
                'category_id' => $subcategory->id,
                'name' => 'Товар '.$data['name'],
                'slug' => 'alphabetical-product-'.$index,
                'sku' => 'ALPHABETICAL-'.$index,
                'price' => 100 + $index,
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => true,
                'availability_status' => Product::AVAILABILITY_IN_STOCK,
            ]);
        }

        $html = $this->get('/'.$parent->slug)
            ->assertOk()
            ->getContent();

        $expectedOrder = [
            'alphabetical-tanks-test',
            'alphabetical-ducts-test',
            'alphabetical-filters-test',
        ];

        $firstOccurrences = array_map(
            fn (string $slug): int => $this->occurrencePosition($html, $slug, 1),
            $expectedOrder
        );
        $secondOccurrences = array_map(
            fn (string $slug): int => $this->occurrencePosition($html, $slug, 2),
            $expectedOrder
        );

        $this->assertSame($firstOccurrences, collect($firstOccurrences)->sort()->values()->all());
        $this->assertSame($secondOccurrences, collect($secondOccurrences)->sort()->values()->all());
    }

    private function occurrencePosition(string $html, string $needle, int $occurrence): int
    {
        $offset = 0;
        $position = false;

        for ($index = 0; $index < $occurrence; $index++) {
            $position = mb_strpos($html, $needle, $offset);
            $this->assertNotFalse($position, sprintf('Не найдено вхождение %d для «%s».', $occurrence, $needle));
            $offset = $position + mb_strlen($needle);
        }

        return $position;
    }
}
