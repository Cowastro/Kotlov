<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $accessories = DB::table('categories')->where('slug', 'pechi-dlya-bani')->first();
            if (! $accessories) {
                return;
            }

            // Keep product and supplier references on the original category ID.
            DB::table('categories')->where('id', $accessories->id)->update([
                'slug' => 'aksessuary-dlya-parnoy', 'updated_at' => now(),
            ]);
            $hubId = DB::table('categories')->insertGetId([
                'slug' => 'pechi-dlya-bani',
                'name' => 'Печи для бани',
                'h1' => 'Печи для бани и сауны',
                'parent_id' => $accessories->parent_id,
                'type' => $accessories->type,
                'sort_order' => 10,
                'is_active' => true,
                'meta_title' => 'Печи для бани и сауны — дровяные печи и электрокаменки в %city% | KOTLOV',
                'meta_description' => 'Выберите дровяную печь или электрокаменку по объёму парной. Цены в BYN, наличие моделей и доставка по Беларуси.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('categories')->whereIn('slug', ['drovianye-peci-bannye', 'drovyanye-pechi-dlya-bani', 'elektrokamenki'])
                ->update(['parent_id' => $hubId, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Category URLs and their product relationships are public catalogue data.
    }
};
