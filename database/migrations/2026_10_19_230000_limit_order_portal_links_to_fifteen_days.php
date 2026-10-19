<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('order_portal_links')
            ->select(['id', 'created_at', 'expires_at'])
            ->orderBy('id')
            ->chunkById(200, function ($links): void {
                foreach ($links as $link) {
                    $maximum = Carbon::parse($link->created_at)->addDays(15);
                    if (Carbon::parse($link->expires_at)->gt($maximum)) {
                        DB::table('order_portal_links')->where('id', $link->id)->update([
                            'expires_at' => $maximum,
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Срок старых ссылок намеренно не увеличивается при откате.
    }
};
