<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuarantineLegacyShiberCommand extends Command
{
    protected $signature = 'catalog:quarantine-legacy-shiber {--apply : Hide the unverified legacy card from new sales}';

    protected $description = 'Quarantine PS-011.130 without merging models or changing historical orders';

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $old = Product::where('sku', 'PS-011.130')->lockForUpdate()->firstOrFail();
            $verified = Product::where('slug', 'teplov-i-suxov-siber-zadvizka-430-08-d115-m')->firstOrFail();
            if ($old->supplierProducts()->where(fn ($q) => $q->whereNull('match_confidence')->orWhere('match_confidence', '!=', 'brand_assignment'))->exists()
                || $old->integrationProducts()->exists() || $old->warehouseStocks()->exists()
                || ! $verified->supplierProducts()->where('supplier_article', 'TS.FRT.SHR.0115.82320')->exists()) {
                $this->error('Данные карточек изменились. Нужна повторная проверка; ничего не изменено.');

                return self::FAILURE;
            }
            $this->line('legacy='.$old->sku.'; verified='.$verified->sku.'; mode=archive-only; orders=unchanged');
            if (! $this->option('apply') || $old->is_archived) {
                return self::SUCCESS;
            }
            $path = 'imports/audits/legacy-shiber-'.now()->format('Ymd-His').'.json';
            Storage::disk('local')->put($path, json_encode(['product_id' => $old->id, 'sku' => $old->sku, 'is_active' => $old->is_active,
                'is_archived' => $old->is_archived, 'price' => $old->price, 'reason' => 'Legacy card without confirmed manufacturer article; stop new sales until identification',
                'comparison_product_id' => $verified->id], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $old->forceFill(['is_active' => false, 'is_archived' => true])->save();
            $this->info('Карточка убрана из продаж. Ссылка, корзины, заказы и цены старых заказов сохранены. Backup: '.$path);

            return self::SUCCESS;
        });
    }
}
