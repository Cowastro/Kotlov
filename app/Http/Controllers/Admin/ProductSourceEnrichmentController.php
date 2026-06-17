<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductSourceEnrichmentController extends Controller
{
    public function apply(string $token, Request $request, ProductSourceEnricher $enricher): RedirectResponse
    {
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (! is_array($payload)) {
            Notification::make()
                ->danger()
                ->title('Предпросмотр устарел')
                ->body('Запустите проверку еще раз: сохраненный вариант уже недоступен.')
                ->send();

            return redirect('/admin/products');
        }

        $this->ensureCurrentAdminOwnsPreview($payload, $request);
        Cache::forget($key);

        $products = Product::query()
            ->whereKey($payload['product_ids'] ?? [])
            ->get();

        $processed = 0;
        $errors = [];
        $totals = [
            'images_found' => 0,
            'images_saved' => 0,
            'specs_found' => 0,
            'attribute_values_saved' => 0,
            'service_found' => 0,
            'content_found' => 0,
            'short_description_found' => 0,
        ];

        foreach ($products as $product) {
            try {
                $result = $enricher->enrich($product, (string) ($payload['source_url'] ?? ''), (array) ($payload['options'] ?? []));

                foreach ($totals as $name => $value) {
                    $totals[$name] += (int) ($result[$name] ?? 0);
                }

                foreach (($result['errors'] ?? []) as $error) {
                    $errors[] = ($product->sku ?: $product->id) . ': ' . $error;
                }

                $processed++;
            } catch (\Throwable $e) {
                $errors[] = ($product->sku ?: $product->id) . ': ' . $e->getMessage();
            }
        }

        $summary = [
            'Фото: найдено ' . $totals['images_found'] . ', сохранено ' . $totals['images_saved'],
            'Описание: полное ' . $totals['content_found'] . ', короткое ' . $totals['short_description_found'],
            'Характеристики: найдено ' . $totals['specs_found'] . ', записано в атрибуты ' . $totals['attribute_values_saved'],
            'Сервис: найдено строк ' . $totals['service_found'],
        ];

        if ($errors !== []) {
            $summary[] = '';
            $summary[] = 'Ошибки:';
            array_push($summary, ...array_slice($errors, 0, 5));
        }

        $notification = Notification::make()
            ->title('Обновлено товаров: ' . $processed)
            ->body(implode("\n", $summary))
            ->persistent();

        $errors === [] ? $notification->success() : $notification->warning();
        $notification->send();

        return redirect('/admin/products');
    }

    public function cancel(string $token, Request $request): RedirectResponse
    {
        $key = $this->cacheKey($token);
        $payload = Cache::get($key);

        if (is_array($payload)) {
            $this->ensureCurrentAdminOwnsPreview($payload, $request);
            Cache::forget($key);
        }

        Notification::make()
            ->info()
            ->title('Обновление отменено')
            ->body('Товары не изменялись.')
            ->send();

        return redirect('/admin/products');
    }

    private function ensureCurrentAdminOwnsPreview(array $payload, Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->isAdmin() && $user->is_active, 403);
        abort_unless((int) ($payload['user_id'] ?? 0) === (int) $user->id, 403);
    }

    private function cacheKey(string $token): string
    {
        return 'product-source-enrichment:' . $token;
    }
}
