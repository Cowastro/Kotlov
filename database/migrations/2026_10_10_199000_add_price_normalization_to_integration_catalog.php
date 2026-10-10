<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_sources', function (Blueprint $table): void {
            $table->string('price_currency', 3)->default('BYN')->after('driver');
            $table->decimal('price_currency_rate', 14, 6)->nullable()->default(1)->after('price_currency');
        });

        Schema::table('integration_products', function (Blueprint $table): void {
            $table->string('price_currency', 3)->nullable()->after('price');
            $table->decimal('price_currency_rate', 14, 6)->nullable()->after('price_currency');
            $table->string('price_tax_mode', 16)->nullable()->after('price_currency_rate');
            $table->decimal('price_vat_rate', 7, 4)->nullable()->after('price_tax_mode');
            $table->decimal('price_byn', 14, 2)->nullable()->after('price_vat_rate')->index();
        });

        DB::table('integration_sources')
            ->orderBy('id')
            ->each(function (object $source): void {
                $supplier = filled($source->supplier_id ?? null)
                    ? DB::table('suppliers')->where('id', $source->supplier_id)->first()
                    : null;
                $currency = $this->normalizeCurrency($supplier->currency ?? 'BYN');
                $rate = $currency === 'BYN'
                    ? 1.0
                    : $this->positiveFloat($supplier->currency_rate ?? null);

                DB::table('integration_sources')->where('id', $source->id)->update([
                    'price_currency' => $currency,
                    'price_currency_rate' => $rate,
                ]);

                $settings = $this->decodeSettings($source->settings ?? null);
                $taxMode = ($settings['price_tax_mode'] ?? null) === 'inclusive'
                    ? 'inclusive'
                    : 'exclusive';
                $vatRate = max(0, (float) ($settings['vat_rate'] ?? 20));

                DB::table('integration_products')
                    ->where('integration_source_id', $source->id)
                    ->orderBy('id')
                    ->chunkById(250, function ($products) use ($currency, $rate, $taxMode, $vatRate): void {
                        foreach ($products as $product) {
                            DB::table('integration_products')->where('id', $product->id)->update([
                                'price_currency' => $currency,
                                'price_currency_rate' => $rate,
                                'price_tax_mode' => $taxMode,
                                'price_vat_rate' => $vatRate,
                                'price_byn' => $this->normalizePrice(
                                    $product->price,
                                    $rate,
                                    $taxMode,
                                    $vatRate,
                                ),
                            ]);
                        }
                    });
            });
    }

    public function down(): void
    {
        Schema::table('integration_products', function (Blueprint $table): void {
            $table->dropIndex(['price_byn']);
            $table->dropColumn([
                'price_currency',
                'price_currency_rate',
                'price_tax_mode',
                'price_vat_rate',
                'price_byn',
            ]);
        });

        Schema::table('integration_sources', function (Blueprint $table): void {
            $table->dropColumn(['price_currency', 'price_currency_rate']);
        });
    }

    /** @return array<string, mixed> */
    private function decodeSettings(mixed $settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        $decoded = json_decode((string) $settings, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeCurrency(mixed $currency): string
    {
        $currency = mb_strtoupper(trim((string) $currency));

        return $currency !== '' ? mb_substr($currency, 0, 3) : 'BYN';
    }

    private function positiveFloat(mixed $value): ?float
    {
        $value = (float) $value;

        return $value > 0 ? $value : null;
    }

    private function normalizePrice(mixed $price, ?float $rate, string $taxMode, float $vatRate): ?float
    {
        $price = (float) $price;
        if ($price <= 0 || $rate === null || $rate <= 0) {
            return null;
        }

        $priceByn = $price * $rate;
        if ($taxMode === 'exclusive') {
            $priceByn *= 1 + $vatRate / 100;
        }

        return round($priceByn, 2);
    }
};
