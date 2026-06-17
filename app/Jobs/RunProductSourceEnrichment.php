<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductSourceEnricher;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RunProductSourceEnrichment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 80;

    /**
     * @param array<int> $productIds
     * @param array<string, bool> $options
     */
    public function __construct(
        private array $productIds,
        private int $userId,
        private string $sourceUrl,
        private array $options,
        private bool $previewOnly,
    ) {}

    public function handle(ProductSourceEnricher $enricher): void
    {
        $user = User::query()->find($this->userId);
        $products = Product::query()->whereKey($this->productIds)->get();

        $processed = 0;
        $errors = [];
        $preview = null;
        $totals = [
            'images_found' => 0,
            'images_saved' => 0,
            'images_replaced' => 0,
            'specs_found' => 0,
            'attribute_values_saved' => 0,
            'service_found' => 0,
            'content_found' => 0,
            'short_description_found' => 0,
        ];

        $options = $this->options;
        $options['preview_only'] = $this->previewOnly;

        foreach ($products as $product) {
            try {
                $result = $enricher->enrich($product, $this->sourceUrl, $options);

                foreach ($totals as $key => $value) {
                    $totals[$key] += (int) ($result[$key] ?? 0);
                }

                $preview ??= $result['preview'] ?? null;
                foreach (($result['errors'] ?? []) as $error) {
                    $errors[] = ($product->sku ?: $product->id) . ': ' . $error;
                }

                $processed++;
            } catch (\Throwable $e) {
                $errors[] = ($product->sku ?: $product->id) . ': ' . $e->getMessage();
            }
        }

        $summary = $this->summary($totals, $preview);
        if ($errors !== []) {
            $summary[] = '';
            $summary[] = 'Ошибки:';
            array_push($summary, ...array_slice($errors, 0, 10));
        }

        $notification = Notification::make()
            ->title(($this->previewOnly ? 'Проверка завершена: ' : 'Обновлено товаров: ') . $processed)
            ->body(implode("\n", $summary))
            ->persistent();

        $errors === [] ? $notification->success() : $notification->warning();

        if ($user) {
            $notification->sendToDatabase($user);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        Notification::make()
            ->danger()
            ->title($this->previewOnly ? 'Проверка не выполнена' : 'Обновление не выполнено')
            ->body(Str::limit($exception->getMessage(), 700))
            ->persistent()
            ->sendToDatabase($user);
    }

    /**
     * @param array<string, int> $totals
     * @return array<int, string>
     */
    private function summary(array $totals, ?array $preview): array
    {
        if (! $this->previewOnly) {
            return [
                'Фото: найдено ' . $totals['images_found'] . ', сохранено ' . $totals['images_saved'] . ', режим: ' . ($totals['images_replaced'] > 0 ? 'замена' : 'добавление/без замены'),
                'Описание: полное ' . $totals['content_found'] . ', короткое ' . $totals['short_description_found'],
                'Характеристики: найдено ' . $totals['specs_found'] . ', записано в атрибуты ' . $totals['attribute_values_saved'],
                'Сервис: найдено строк ' . $totals['service_found'],
            ];
        }

        $summary = [
            'Режим проверки: база не изменялась.',
            'Фото найдено: ' . $totals['images_found'],
            'Описание найдено: полное ' . $totals['content_found'] . ', короткое ' . $totals['short_description_found'],
            'Характеристики найдены: ' . $totals['specs_found'],
            'Сервис найдено строк: ' . $totals['service_found'],
        ];

        if (is_array($preview) && filled($preview['description'] ?? '')) {
            $summary[] = '';
            $summary[] = 'Фрагмент описания:';
            $summary[] = Str::limit((string) $preview['description'], 700);
        }

        if (is_array($preview) && ($preview['specs'] ?? []) !== []) {
            $summary[] = '';
            $summary[] = 'Первые характеристики:';
            foreach (array_slice($preview['specs'], 0, 8) as $spec) {
                $summary[] = '- ' . ($spec['key'] ?? '') . ': ' . ($spec['value'] ?? '');
            }
        }

        return $summary;
    }
}
