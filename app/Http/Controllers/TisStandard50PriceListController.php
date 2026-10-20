<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TisStandard50PriceListController extends Controller
{
    public function __invoke(): View
    {
        $products = $this->products();

        return view('pages.tis-standard-50-price-list', [
            'series' => collect([
                'mono' => $this->series($products, 'Моно'),
                'thermo' => $this->series($products, 'Термо'),
            ]),
        ]);
    }

    public function csv(): StreamedResponse
    {
        $products = $this->products();

        return response()->streamDownload(function () use ($products): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Система', 'Элемент', 'Диаметр, мм', 'Цена, BYN', 'Срок поставки', 'Ссылка'], ';');

            foreach ($products as $product) {
                fputcsv($output, [
                    data_get($product->specs, 'Исполнение'),
                    data_get($product->specs, 'Тип элемента'),
                    data_get($product->specs, 'Диаметр'),
                    number_format((float) $product->price, 2, '.', ''),
                    '5–7 рабочих дней',
                    url('/'.$product->category->slug.'/'.$product->slug),
                ], ';');
            }

            fclose($output);
        }, 'tis-standart-50-kotlov-by.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function products(): Collection
    {
        return Product::query()
            ->where(function ($query): void {
                $query->where('sku', 'like', 'TIS50-%')
                    ->orWhereHas('supplierProducts', fn ($supplierProducts) => $supplierProducts
                        ->where('supplier_article', 'like', 'TIS50-%'));
            })
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('price', '>', 0)
            ->withSupplierChannels()
            ->with(['category:id,name,slug', 'brand:id,name,slug'])
            ->orderBy('sort_order')
            ->get();
    }

    /** @return array{title:string,description:string,diameters:Collection,rows:Collection} */
    private function series(Collection $products, string $execution): array
    {
        $items = $products->filter(
            fn (Product $product): bool => data_get($product->specs, 'Исполнение') === $execution
        );

        $diameters = $items
            ->pluck('specs')
            ->map(fn (array $specs): string => (string) ($specs['Диаметр'] ?? ''))
            ->filter()
            ->unique()
            ->sort(fn (string $a, string $b): int => $this->diameterKey($a) <=> $this->diameterKey($b))
            ->values();

        $rows = $items
            ->groupBy(fn (Product $product): string => (string) data_get($product->specs, 'Тип элемента'))
            ->map(function (Collection $variants, string $label): array {
                return [
                    'label' => $label,
                    'sort' => (int) $variants->min('sort_order'),
                    'variants' => $variants->keyBy(fn (Product $product): string => (string) data_get($product->specs, 'Диаметр')),
                ];
            })
            ->sortBy('sort')
            ->values();

        return [
            'title' => $execution === 'Моно'
                ? 'Система ТиС «Стандарт 50» Моно'
                : 'Система ТиС «Стандарт 50» Термо — изоляция 50 мм',
            'description' => $execution === 'Моно'
                ? 'Одностенные элементы из нержавеющей стали AISI 304 для участков дымохода внутри отапливаемых помещений.'
                : 'Двустенные элементы AISI 304 с теплоизоляцией 50 мм для безопасного прохождения холодных зон и наружного монтажа.',
            'diameters' => $diameters,
            'rows' => $rows,
        ];
    }

    /** @return array<int, int> */
    private function diameterKey(string $diameter): array
    {
        return collect(explode('/', $diameter))->map(fn ($value): int => (int) $value)->all();
    }
}
