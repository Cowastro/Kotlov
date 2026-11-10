<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\SmolcomSiteCatalog;
use App\Services\Suppliers\SmolkomPriceList;
use Illuminate\Console\Command;

/**
 * Takes off the photos a web search put on Смолком cards when they are wrong (another colour or model, a drawing, a
 * video frame — named by --slug) or carry another shop's watermark (photos from --host, 21vek.by by default). Only
 * cards made from the price list (SKU «SMOLKOM-») are touched. A card left without a photo is hidden until one is
 * found; where another colour of its model has photos from smolcom.by, that photo is shown for now with a note
 * («На фото — цвет …») and is replaced once supplier:smolkom-site-photos finds the card's own.
 *
 *   php artisan supplier:smolkom-photo-cleanup --slug=portal-smolcom-line-v42-cernyi            # dry run
 *   php artisan supplier:smolkom-photo-cleanup --slug=… --apply
 */
class SmolkomPhotoCleanupCommand extends Command
{
    protected $signature = 'supplier:smolkom-photo-cleanup
        {--slug=* : Cards whose photo is wrong}
        {--host=* : Photo sources to drop (default 21vek.by: watermarked)}
        {--keep=* : Cards from such a source whose photo has no watermark}
        {--recheck-site : Also take off smolcom.by photos the site no longer gives for the colour of the card}
        {--apply : Write the change}';

    protected $description = 'Remove wrong or watermarked web photos from Смолком cards; show a sibling colour photo for now';

    public const NOTE = 'На фото — цвет «%s», фото этого цвета скоро добавим.';

    public function handle(SmolcomSiteCatalog $site): int
    {
        $supplier = Supplier::query()->where('code', SyncSmolkomCommand::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->line('metric.smolkom_photo_cleanup.supplier=0');

            return self::FAILURE;
        }
        $slugs = array_filter(array_map('trim', (array) $this->option('slug')));
        $hosts = array_filter(array_map('trim', (array) $this->option('host'))) ?: ['21vek.by'];
        $keep = array_filter(array_map('trim', (array) $this->option('keep')));
        $recheck = (bool) $this->option('recheck-site');
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->with('product')->get()
            ->filter(fn (SupplierProduct $o) => $o->product && str_starts_with((string) $o->product->sku, 'SMOLKOM-'));
        $family = fn (SupplierProduct $o): string => SmolkomPriceList::modelKey((string) ($o->raw['name'] ?? ''));

        // Cards with the price list model's own smolcom.by photos, by model.
        $donors = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->with('product')->get()
            ->filter(fn (SupplierProduct $o) => $o->product && $o->product->is_active && str_contains((string) $o->product->content_source_url, 'smolcom.by')
                && array_filter((array) $o->product->images) !== [] && empty($o->raw['temp_photo_from']) && ! in_array($o->product->slug, $slugs, true)
                && (! $recheck || $site->photos((string) ($o->raw['article'] ?? '')) !== []))
            ->groupBy($family);

        $stats = ['wrong' => 0, 'watermarked' => 0, 'temp_photo' => 0, 'hidden' => 0, 'note_fixed' => 0];
        foreach ($offers as $offer) {
            $product = $offer->product;
            $source = (string) $product->content_source_url;
            $wrong = in_array($product->slug, $slugs, true)
                // A smolcom.by photo that the site shows for every colour of the model.
                || ($recheck && empty($offer->raw['temp_photo_from']) && str_contains($source, 'smolcom.by') && array_filter((array) $product->images) !== []
                    && $site->photos((string) ($offer->raw['article'] ?? '')) === []);
            $marked = ! $wrong && ! in_array($product->slug, $keep, true) && array_filter((array) $product->images) !== []
                && collect($hosts)->contains(fn (string $h) => str_contains($source, $h));
            if (! $wrong && ! $marked) {
                // A card already showing a sibling colour keeps a whole note (an older run cut it at 250 characters).
                if (! empty($offer->raw['temp_photo_from']) && ($donorCard = Product::query()->find($offer->raw['temp_photo_from']))) {
                    $donorOffer = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('product_id', $donorCard->id)->first();
                    $colour = mb_strtolower(trim((string) ($donorOffer?->raw['colour'] ?? '')));
                    $fixed = self::withNote((string) $product->short_description, $colour);
                    if ($colour !== '' && $fixed !== $product->short_description) {
                        $stats['note_fixed'] = ($stats['note_fixed'] ?? 0) + 1;
                        $this->option('apply') && $product->forceFill(['short_description' => $fixed])->save();
                    }
                }

                continue;
            }
            $stats[$wrong ? 'wrong' : 'watermarked']++;
            $donor = $donors->get($family($offer))?->first(fn (SupplierProduct $d) => $d->product_id !== $offer->product_id);
            $this->line(sprintf('audit.smolkom_photo_cleanup.card=%s:%s:%s', $product->slug, $wrong ? 'wrong' : 'watermark', $donor ? $donor->product->slug : 'hidden'));
            $stats[$donor ? 'temp_photo' : 'hidden']++;
            if (! $this->option('apply')) {
                continue;
            }
            $raw = (array) $offer->raw;
            // The web search may look again (21vek.by is off its list now).
            unset($raw['photo_searched_at']);
            if ($donor) {
                $colour = mb_strtolower(trim((string) ($donor->raw['colour'] ?? '')));
                $product->forceFill([
                    'images' => array_values(array_filter((array) $donor->product->images)),
                    'short_description' => self::withNote((string) $product->short_description, $colour),
                    'is_active' => true,
                    'created_via' => null,
                    'content_source_url' => 'https://smolcom.by/',
                ])->save();
                $raw['temp_photo_from'] = $donor->product_id;
            } else {
                $product->forceFill(['images' => [], 'is_active' => false, 'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS, 'content_source_url' => null])->save();
                unset($raw['temp_photo_from']);
            }
            $offer->forceFill(['raw' => $raw])->save();
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.smolkom_photo_cleanup.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** The note first, the description untouched after it. */
    public static function withNote(string $text, string $colour): string
    {
        return trim(sprintf(self::NOTE, $colour).' '.self::stripNote($text));
    }

    /** The short description without the temporary photo note (also a note cut short at the end). */
    public static function stripNote(string $text): string
    {
        $text = (string) preg_replace('/\s*На фото — цвет «[^»]*», фото этого цвета скоро добавим\.\s*/u', ' ', $text);

        $cut = (bool) preg_match('/\s*На фото —[^.]*$/u', $text);
        $text = trim((string) preg_replace('/\s*На фото —[^.]*$/u', '', $text));
        // An older run cut the text itself at 250 characters: keep the whole sentences.
        if ($cut && ! preg_match('/[.!?…]$/u', $text) && ($end = mb_strrpos($text, '.')) !== false) {
            $text = mb_substr($text, 0, $end + 1);
        }

        return $text;
    }
}
