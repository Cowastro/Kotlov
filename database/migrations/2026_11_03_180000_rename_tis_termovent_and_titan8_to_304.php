<?php

use App\Models\IntegrationProduct;
use App\Services\Integrations\IntegrationProductMatchDecision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Теплов и Сухов makes ТермоВент with an inner pipe of AISI 304 now (the same articles were 430 in the price list
 * of 17.09.2026), and «Теплообменник 8» (11912) is the 304 one by its price list and 1C rows: the names say 304.
 * The 1C rows of the 304 ТермоВент join the same cards, so the 1C exchange makes no second ones.
 */
return new class extends Migration
{
    /** card id => 1C row id of the 304 make */
    private const TERMOVENT = [12039 => 13366, 12040 => 13364, 12041 => 13362, 12042 => 13365, 12043 => 13361];

    public function up(): void
    {
        $brand = DB::table('brands')->where('name', 'Теплов и Сухов')->value('id');
        if (! $brand) {
            return;
        }
        $fields = ['name', 'h1', 'meta_title', 'meta_description', 'short_description'];
        $rename = function (int $id, callable $change) use ($brand, $fields): void {
            $card = DB::table('products')->where('id', $id)->where('brand_id', $brand)->first();
            if (! $card) {
                return;
            }
            $update = [];
            foreach ($fields as $field) {
                $value = (string) ($card->{$field} ?? '');
                $new = $value === '' ? $value : $change($value);
                if ($new !== $value) {
                    $update[$field] = $new;
                }
            }
            $update === [] || DB::table('products')->where('id', $id)->update($update + ['updated_at' => now()]);
        };

        // «ТермоВент L1000 430-0,8/430» → «… 304-0,8/430»: the inner steel only, the casing stays 430.
        foreach (array_keys(self::TERMOVENT) as $id) {
            $rename($id, fn (string $text) => (string) preg_replace('/(?<!\d)430(?=\s*-\s*0[.,][58]\s*\/\s*430)/u', '304', $text, 1));
        }
        // «Теплообменник 8 (S-12 дм2) D115/115 430» → «… 304».
        $rename(11912, fn (string $text) => str_contains($text, '304') ? $text : (string) preg_replace('/(?<!\d)430(?!\d)(?!.*(?<!\d)430(?!\d))/u', '304', $text));

        $decision = app(IntegrationProductMatchDecision::class);
        foreach (self::TERMOVENT as $cardId => $rowId) {
            $row = IntegrationProduct::query()->find($rowId);
            if ($row && ! $row->product_id && DB::table('products')->where('id', $cardId)->where('brand_id', $brand)->exists()
                && preg_match('/термовент|упш/iu', (string) $row->name) === 1 && str_contains((string) $row->name, '304')) {
                $decision->linkSelectedProduct($row, $cardId);
            }
        }
    }

    public function down(): void
    {
        // Names and links are data: left as they are.
    }
};
