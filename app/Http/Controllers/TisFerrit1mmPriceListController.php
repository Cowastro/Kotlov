<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TisFerrit1mmPriceListController extends Controller
{
    public function __invoke(): View
    {
        $products = $this->products();

        return view('pages.tis-ferrit-1mm-price-list', [
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
                    $this->catalogueValue($product, 'execution', 'Исполнение'),
                    $this->catalogueValue($product, 'element', 'Тип элемента'),
                    $this->catalogueValue($product, 'diameter', 'Диаметр'),
                    number_format((float) $product->price, 2, '.', ''),
                    '5–7 рабочих дней',
                    url('/'.$product->category->slug.'/'.$product->slug),
                ], ';');
            }

            fclose($output);
        }, 'tis-ferrit-1-mm-kotlov-by.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function products(): Collection
    {
        return Product::query()
            ->where(function ($query): void {
                $query->where('sku', 'like', 'TISF1-%')
                    ->orWhereHas('supplierProducts', fn ($supplierProducts) => $supplierProducts
                        ->where('supplier_article', 'like', 'TISF1-%'));
            })
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('price', '>', 0)
            ->withSupplierChannels()
            ->with([
                'category:id,name,slug',
                'brand:id,name,slug',
                'supplierProducts' => fn ($query) => $query->where('supplier_article', 'like', 'TISF1-%'),
            ])
            ->orderBy('sort_order')
            ->get();
    }

    /** @return array{title:string,description:string,diameters:Collection,rows:Collection} */
    private function series(Collection $products, string $execution): array
    {
        $items = $products->filter(
            fn (Product $product): bool => $this->catalogueValue($product, 'execution', 'Исполнение') === $execution
        );

        $diameters = $items
            ->map(fn (Product $product): string => $this->catalogueValue($product, 'diameter', 'Диаметр'))
            ->filter()
            ->unique()
            ->sort(fn (string $a, string $b): int => $this->diameterKey($a) <=> $this->diameterKey($b))
            ->values();

        $rows = $items
            ->groupBy(fn (Product $product): string => $this->catalogueValue($product, 'element', 'Тип элемента'))
            ->map(function (Collection $variants, string $label): array {
                return [
                    'label' => $label,
                    'sort' => (int) $variants->min('sort_order'),
                    'variants' => $variants->keyBy(fn (Product $product): string => $this->catalogueValue($product, 'diameter', 'Диаметр')),
                ];
            })
            ->sortBy('sort')
            ->values();

        return [
            'title' => $execution === 'Моно'
                ? 'Система ТиС «Феррит 1 мм» Моно'
                : 'Система ТиС «Феррит 1 мм» Термо — керамоволокно',
            'description' => $execution === 'Моно'
                ? 'Одностенные элементы из жаростойкой нержавеющей стали AISI 430 толщиной 1 мм для банных и отопительных печей.'
                : 'Двустенные элементы AISI 430 с керамоволоконной изоляцией для холодных участков и прохода через перекрытия.',
            'diameters' => $diameters,
            'rows' => $rows,
        ];
    }

    /** @return array<int, int> */
    private function diameterKey(string $diameter): array
    {
        return collect(explode('/', $diameter))->map(fn ($value): int => (int) $value)->all();
    }

    private function catalogueValue(Product $product, string $rawKey, string $specKey): string
    {
        $channel = $product->supplierProducts->first();
        if ($rawKey === 'execution') {
            $series = (string) data_get($channel?->raw, 'series');
            if (str_contains($series, 'Моно')) {
                return 'Моно';
            }
            if (str_contains($series, 'Термо')) {
                return 'Термо';
            }
        }

        return trim((string) (data_get($channel?->raw, $rawKey) ?: data_get($product->specs, $specKey)));
    }
}
