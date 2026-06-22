<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreArchivedProductsCommand extends Command
{
    protected $signature = 'products:restore {ids* : Product IDs to restore}';

    protected $description = 'Restore archived products by ID (set is_archived=false, is_active=true).';

    public function handle(): int
    {
        $ids = array_map('intval', $this->argument('ids'));

        if (empty($ids)) {
            $this->error('No IDs provided.');
            return self::FAILURE;
        }

        $count = DB::table('products')
            ->whereIn('id', $ids)
            ->update(['is_archived' => false, 'is_active' => true, 'updated_at' => now()]);

        $this->info("Restored: {$count} products.");

        return self::SUCCESS;
    }
}
