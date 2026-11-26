<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rename('Пеллетный котел', 'Теплообменник');
    }

    public function down(): void
    {
        $this->rename('Теплообменник', 'Пеллетный котел');
    }

    private function rename(string $from, string $to): void
    {
        $products = DB::table('products')->whereIn('sku', [
            'KOTLOV-023087', 'KOTLOV-023088', 'KOTLOV-023089', 'KOTLOV-023090',
            'KOTLOV-023091', 'KOTLOV-023092', 'KOTLOV-023093', 'KOTLOV-023094',
        ])->get();

        foreach ($products as $product) {
            $updates = [];
            foreach (['name', 'h1', 'meta_title', 'meta_description', 'short_description', 'content'] as $field) {
                $old = $product->{$field};
                $new = str_replace($from.' Sakovich Pellet Standart', $to.' Sakovich Pellet Standart', (string) $old);
                if ($new !== (string) $old) {
                    $updates[$field] = $new;
                }
            }

            if ($updates) {
                DB::table('products')->where('id', $product->id)->update($updates + ['updated_at' => now()]);
            }
        }
    }
};
