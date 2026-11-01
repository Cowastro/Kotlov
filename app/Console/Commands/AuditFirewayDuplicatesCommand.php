<?php

namespace App\Console\Commands;

use App\Services\Suppliers\KaminproffPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fireway cards that are the same model (KaminproffPriceList::modelKey): duplicates to merge by hand.
 * Prints each group with the card slugs; «p» marks a card made from the Камин-профессионал price list,
 * «a» an active one. Read only.
 *
 *   php artisan supplier:fireway-duplicates
 */
class AuditFirewayDuplicatesCommand extends Command
{
    protected $signature = 'supplier:fireway-duplicates';

    protected $description = 'List Fireway cards that share one model (duplicates)';

    public function handle(): int
    {
        $brandIds = DB::table('brands')->get(['id', 'name'])->filter(fn ($brand) => SyncKaminproffCommand::isBrand((string) $brand->name))->pluck('id');
        $cards = DB::table('products')->whereIn('brand_id', $brandIds)->where('is_archived', false)->get(['id', 'name', 'slug', 'sku', 'is_active']);
        $groups = $cards->groupBy(fn ($card) => KaminproffPriceList::modelKey((string) $card->name))->forget('')->filter(fn ($group) => $group->count() > 1);
        $index = 0;
        foreach ($groups as $group) {
            $index++;
            foreach ($group as $card) {
                $flags = (str_starts_with((string) $card->sku, 'FIREWAY-') ? 'p' : '').($card->is_active ? 'a' : '');
                $this->line(sprintf('audit.fireway_duplicates.card=%d:%s:%s', $index, $flags ?: 'none', $card->slug));
            }
        }
        $this->line('metric.fireway_duplicates.cards='.$cards->count());
        $this->line('metric.fireway_duplicates.groups='.$groups->count());
        $this->line('metric.fireway_duplicates.made_from_price='.$groups->flatten(1)->filter(fn ($card) => str_starts_with((string) $card->sku, 'FIREWAY-'))->count());

        return self::SUCCESS;
    }
}
