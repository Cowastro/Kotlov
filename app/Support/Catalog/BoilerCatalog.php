<?php

namespace App\Support\Catalog;

use App\Models\Product;

class BoilerCatalog extends SpecificationCatalog
{
    public const NAMES = [
        'kotly' => 'Котлы отопления',
        'gazovye' => 'Газовые котлы',
        'elektricheskie' => 'Электрические котлы',
        'tverdotoplivnye' => 'Твердотопливные котлы',
        'kotly-na-pelletah' => 'Пеллетные котлы',
    ];

    public const LANDINGS = [
        'gazovye' => [
            'slug' => 'nastennye-dvuhkonturnye',
            'title' => 'Настенные двухконтурные газовые котлы',
            'short' => 'Настенные двухконтурные',
            'description' => 'Модели для отопления и горячей воды. Сравните мощность, камеру сгорания, цены и наличие.',
            'filters' => ['mount' => 'wall', 'circuits' => 'two'],
        ],
        'tverdotoplivnye' => [
            'slug' => 'na-drovah',
            'title' => 'Твердотопливные котлы на дровах',
            'short' => 'Котлы на дровах',
            'description' => 'Модели с дровами в перечне топлива. Сравните мощность, площадь обогрева, материал топки, цены и наличие.',
            'filters' => ['fuel' => 'wood'],
        ],
    ];

    public function __construct(private BoilerSpecifications $specifications) {}

    protected function dimensions(string $slug): array
    {
        return $this->specifications->dimensions($slug);
    }

    public function productFacts(Product $product, string $slug, array $dimensions): array
    {
        return $this->specifications->catalogFacts($product, $slug, $dimensions);
    }
}
