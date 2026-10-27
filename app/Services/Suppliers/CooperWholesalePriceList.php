<?php

namespace App\Services\Suppliers;

use App\Console\Commands\SyncCooperDoorsCommand;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The wholesale price list of ООО «СаунаОпт» for Cooper doors (xlsx: name, «Цена с ндс», «РРЦ»),
 * uploaded in the admin. It stays on the server only (private disk), never in the repository.
 */
class CooperWholesalePriceList
{
    public const FILE = 'suppliers/saunaopt/cooper-wholesale.json';

    /** @return list<array{name:string,made_to_order:bool,wholesale:float,rrc:float}> the Cooper rows of the workbook */
    public static function parse(string $path): array
    {
        $rows = [];
        foreach (IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false) as $cells) {
            $name = trim((string) ($cells[3] ?? ''));
            $wholesale = self::number($cells[5] ?? null);
            if (! str_contains(mb_strtoupper($name), 'COOPER') || $wholesale <= 0) {
                continue;
            }
            $rows[] = [
                // «… 170х71,5          ЗАКАЗНАЯ» — the note after a wide gap is not part of the name.
                'name' => trim((string) preg_replace('/\s{2,}.*$/u', '', $name)),
                'made_to_order' => str_contains(mb_strtoupper($name), 'ЗАКАЗН'),
                'wholesale' => round($wholesale, 2),
                'rrc' => round(self::number($cells[6] ?? null), 2),
            ];
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows */
    public static function save(array $rows, string $sourceName): void
    {
        Storage::disk('local')->put(self::FILE, json_encode([
            'source' => $sourceName,
            'uploaded_at' => now()->toIso8601String(),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** @return array<string,array{wholesale:float,rrc:float}> door key => cost and recommended retail */
    public static function load(): array
    {
        $disk = Storage::disk('local');
        $data = $disk->exists(self::FILE) ? json_decode((string) $disk->get(self::FILE), true) : null;
        $offers = [];
        foreach ((array) ($data['rows'] ?? []) as $row) {
            $offers[SyncCooperDoorsCommand::doorKey((string) ($row['name'] ?? ''))] = ['wholesale' => (float) $row['wholesale'], 'rrc' => (float) $row['rrc']];
        }

        return $offers;
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : (float) str_replace([' ', ','], ['', '.'], (string) $value);
    }
}
