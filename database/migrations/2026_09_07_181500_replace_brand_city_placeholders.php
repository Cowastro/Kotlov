<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('brands')
            ->where('content', 'like', '%%city%%')
            ->update([
                'content' => DB::raw("REPLACE(content, '%city%', 'Минске')"),
                'updated_at' => now(),
            ]);
    }
};
