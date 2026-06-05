<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use Illuminate\Console\Command;

class NormalizeAttributeFlagsCommand extends Command
{
    protected $signature = 'attributes:normalize-flags {--force : Skip confirmation prompt}';

    protected $description = 'Set in_product=1 for filter-only attributes that have no value-duplicate on the product page';

    /**
     * Attributes with in_filter=1, in_product=0 that carry unique semantic value
     * on the product page and have no value/check duplicate already set to in_product=1.
     *
     * Verified 2026-06-05 via audit against kotlov_new2026 DB.
     * See: analysis in session frosty-cannon-db32db.
     */
    private const TARGET_IDS = [
        787,  // Обогрев                        — Электрокамины
        789,  // Эффект Opti-Myst               — Электрокамины
        841,  // Предназначен для               — Облицовки и порталы
        900,  // Расстояние между осями ниппелей — Радиаторы
        1245, // Форма стекла                   — Топки каминов
        1268, // Ширина топки                   — Топки каминов
        1260, // Подача воздуха (из вне)        — Топки каминов
        1232, // Вид жидкости                   — Теплоносители (Антифриз)
        1236, // Основа жидкости                — Теплоносители (Антифриз)
    ];

    public function handle(): int
    {
        $this->info('=== Normalize Attribute Flags: in_product normalization ===');
        $this->newLine();

        $attributes = Attribute::with('category')
            ->whereIn('id', self::TARGET_IDS)
            ->orderBy('id')
            ->get();

        if ($attributes->isEmpty()) {
            $this->error('No attributes found for the target IDs. Nothing to do.');
            return self::FAILURE;
        }

        // --- Show current state ---
        $this->info('Current state of target attributes:');
        $this->newLine();

        $this->table(
            ['ID', 'Name', 'Category', 'Type', 'in_filter', 'in_product', 'in_brief'],
            $attributes->map(fn($a) => [
                $a->id,
                $a->name,
                $a->category?->name ?? '—',
                $a->type,
                $a->in_filter  ? '✓' : '✗',
                $a->in_product ? '✓' : '✗',
                $a->in_brief   ? '✓' : '✗',
            ])
        );

        $needsUpdate = $attributes->where('in_product', false);
        $alreadySet  = $attributes->where('in_product', true);

        if ($alreadySet->isNotEmpty()) {
            $this->line(sprintf(
                '<comment>%d attribute(s) already have in_product=1 — will be skipped.</comment>',
                $alreadySet->count()
            ));
        }

        $count = $needsUpdate->count();

        if ($count === 0) {
            $this->info('All target attributes already have in_product=1. Nothing to update.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf('<info>%d attribute(s) will be updated:</info> in_product 0 → 1', $count));
        $this->table(
            ['ID', 'Name', 'Category'],
            $needsUpdate->map(fn($a) => [
                $a->id,
                $a->name,
                $a->category?->name ?? '—',
            ])
        );

        // --- Confirmation ---
        if (! $this->option('force')) {
            if (! $this->confirm('Proceed with update?', false)) {
                $this->warn('Aborted. No changes made.');
                return self::SUCCESS;
            }
        }

        // --- Update ---
        $updated = Attribute::whereIn('id', $needsUpdate->pluck('id')->all())
            ->update(['in_product' => true]);

        $this->newLine();
        $this->info("Updated {$updated} attribute(s).");

        // --- Final state ---
        $this->newLine();
        $this->info('Final state after update:');

        $fresh = Attribute::with('category')
            ->whereIn('id', self::TARGET_IDS)
            ->orderBy('id')
            ->get();

        $this->table(
            ['ID', 'Name', 'Category', 'Type', 'in_filter', 'in_product', 'in_brief'],
            $fresh->map(fn($a) => [
                $a->id,
                $a->name,
                $a->category?->name ?? '—',
                $a->type,
                $a->in_filter  ? '✓' : '✗',
                $a->in_product ? '✓' : '✗',
                $a->in_brief   ? '✓' : '✗',
            ])
        );

        return self::SUCCESS;
    }
}
