<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off follow-up to supplier:create-comfortprom-mangaly: the price list
 * had no embedded photo for «Мангал складной ComfortProm Пегас» and
 * «Мангал передвижной ComfortProm Асгард» (both thickness variants), so
 * those 3 products were created without images. Per user request, found
 * matching product photos from a Belarusian retailer selling the same
 * ComfortProm models (100kaminov.by, images hosted on images.deal.by —
 * confirmed by product name/article match: "mpasg2" = Асгард передвижной
 * 2мм, складной Пегас page). Seeded into resources/seed-images/
 * comfortprom-mangaly/ for this command to copy into the public disk.
 *
 *   php artisan supplier:add-comfortprom-mangaly-photos            # dry run
 *   php artisan supplier:add-comfortprom-mangaly-photos --apply
 */
class AddComfortpromMangalyMissingPhotosCommand extends Command
{
    protected $signature = 'supplier:add-comfortprom-mangaly-photos {--apply : Actually copy images + save products}';

    protected $description = 'Add found photos to the 3 ComfortProm мангалы products created without images (Пегас, Асгард передвижной x2)';

    private const SEED_DIR = 'comfortprom-mangaly';

    private const MAP = [
        'mangal-skladnoy-comfortprom-pegas' => 'pegas_found.jpg',
        'mangal-peredvizhnoy-asgard-2mm' => 'asgard_peredv_found.jpg',
        'mangal-peredvizhnoy-asgard-3mm' => 'asgard_peredv_found.jpg',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        foreach (self::MAP as $slug => $imageFile) {
            $product = Product::where('slug', $slug)->first();
            if (! $product) {
                $this->warn("not found: {$slug}");
                continue;
            }

            $existingImages = (array) ($product->images ?? []);
            if (! empty($existingImages)) {
                $this->line("= already has photo, skip: {$slug}");
                continue;
            }

            $seedFile = resource_path('seed-images/' . self::SEED_DIR . '/' . $imageFile);
            if (! file_exists($seedFile)) {
                $this->warn("  seed image missing: {$seedFile}");
                continue;
            }

            $ext = pathinfo($imageFile, PATHINFO_EXTENSION);
            $destName = 'products/' . $slug . '.' . $ext;

            $this->line("+ {$slug} -> {$destName}");

            if ($apply) {
                Storage::disk('public')->put($destName, file_get_contents($seedFile));
                $product->images = [$destName];
                $product->save();
            }
        }

        $this->info($apply ? 'APPLIED' : 'DRY-RUN');

        return self::SUCCESS;
    }
}
