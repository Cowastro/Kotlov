<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->foreignId('crm_counterparty_id')
                ->nullable()
                ->after('id')
                ->constrained('crm_counterparties')
                ->nullOnDelete();
        });

        $counterpartiesByName = [];
        foreach (DB::table('crm_counterparties')->get(['id', 'name', 'public_name', 'legal_name']) as $counterparty) {
            foreach ([$counterparty->name, $counterparty->public_name, $counterparty->legal_name] as $name) {
                $key = $this->normalizeName($name);
                if ($key !== '') {
                    $counterpartiesByName[$key][(int) $counterparty->id] = true;
                }
            }
        }

        foreach (DB::table('suppliers')->whereNull('crm_counterparty_id')->get(['id', 'name']) as $supplier) {
            $matches = array_keys($counterpartiesByName[$this->normalizeName($supplier->name)] ?? []);
            if (count($matches) === 1) {
                DB::table('suppliers')->where('id', $supplier->id)->update([
                    'crm_counterparty_id' => $matches[0],
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('crm_counterparty_id');
        });
    }

    private function normalizeName(mixed $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim((string) $value))) ?? '';
    }
};
