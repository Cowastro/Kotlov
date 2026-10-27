<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Replaces a fragment of a product's name — a wrong steel grade, model code… — in the name,
 * H1 and meta tags at once; the address stays. Only whole numbers/words are replaced,
 * so «304» does not touch «3040».
 *
 *   php artisan catalog:replace-in-name 9517 304 430            # dry run
 *   php artisan catalog:replace-in-name 9517 304 430 --apply
 */
class ReplaceInProductNameCommand extends Command
{
    protected $signature = 'catalog:replace-in-name {product : Product id} {from : Fragment to replace} {to : Replacement} {--apply : Save the name}';

    protected $description = 'Replace a fragment of a product name, H1 and meta tags';

    private const FIELDS = ['name', 'h1', 'meta_title', 'meta_description'];

    public function handle(): int
    {
        $product = Product::query()->find((int) $this->argument('product'));
        if (! $product) {
            $this->error('Товар не найден.');

            return self::FAILURE;
        }
        $pattern = '/(?<![\pL\pN])'.preg_quote((string) $this->argument('from'), '/').'(?![\pL\pN])/u';
        $changes = [];
        foreach (self::FIELDS as $field) {
            $value = (string) $product->{$field};
            $replaced = (string) preg_replace($pattern, (string) $this->argument('to'), $value);
            if ($replaced !== $value) {
                $changes[$field] = $replaced;
            }
        }
        $this->line(sprintf('audit.replace_in_name.card=%d:%s', $product->id, implode('.', array_keys($changes)) ?: 'none'));
        $this->line('metric.replace_in_name.fields='.count($changes));
        if ($changes !== [] && $this->option('apply')) {
            $product->forceFill($changes)->save();
            $this->line('metric.replace_in_name.saved=1');
        }

        return self::SUCCESS;
    }
}
