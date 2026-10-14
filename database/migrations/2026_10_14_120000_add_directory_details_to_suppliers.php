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
            $table->json('communication_channels')->nullable()->after('contact');
        });

        Schema::create('brand_supplier', function (Blueprint $table): void {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['brand_id', 'supplier_id']);
            $table->index(['supplier_id', 'brand_id']);
        });

        Schema::table('supplier_syncs', function (Blueprint $table): void {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        DB::table('supplier_syncs')
            ->whereNull('supplier_id')
            ->orderBy('id')
            ->get(['id', 'key', 'code', 'name'])
            ->each(function (object $sync): void {
                $candidates = collect([$sync->code, $sync->key])
                    ->map(fn ($value): string => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values();

                $supplierId = DB::table('suppliers')
                    ->whereIn('code', $candidates)
                    ->value('id');

                if (! $supplierId && filled($sync->name)) {
                    $supplierId = DB::table('suppliers')
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $sync->name))])
                        ->value('id');
                }

                if ($supplierId) {
                    DB::table('supplier_syncs')->where('id', $sync->id)->update([
                        'supplier_id' => $supplierId,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('supplier_syncs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('brand_supplier');

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('communication_channels');
        });
    }
};
