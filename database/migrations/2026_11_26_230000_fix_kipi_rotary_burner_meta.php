<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The renamed KIPI ROTARY burners still carried «вместе с фланцем 15-70 kW» in their meta title and description. */
return new class extends Migration
{
    public function up(): void
    {
        $burners = DB::table('products')->where('name', 'like', 'Пеллетная горелка KIPI ROTARY %')
            ->where(fn ($q) => $q->where('meta_title', 'like', '%вместе с фланцем%')->orWhere('meta_description', 'like', '%вместе с фланцем%'))
            ->get(['id', 'name', 'meta_title', 'meta_description']);
        foreach ($burners as $burner) {
            $fix = fn (?string $text) => $text === null ? null : preg_replace('/вместе с фланцем \d+-\d+ kW/ui', $burner->name, $text);
            DB::table('products')->where('id', $burner->id)->update([
                'meta_title' => $fix($burner->meta_title),
                'meta_description' => $fix($burner->meta_description),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
