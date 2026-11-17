<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->move(false);
    }

    public function down(): void
    {
        $this->move(true);
    }

    private function move(bool $reverse): void
    {
        if (! DB::getSchemaBuilder()->hasTable('products') || ! DB::getSchemaBuilder()->hasTable('categories')) return;
        $rows = json_decode(file_get_contents(resource_path('data/pump-accessory-classification.json')), true, flags: JSON_THROW_ON_ERROR)['products'];
        $categories = DB::table('categories')->pluck('id', 'slug');
        DB::transaction(function () use ($rows, $categories, $reverse): void {
            foreach ($rows as $row) {
                $from = $categories[$reverse ? $row['to'] : $row['from']] ?? null;
                $to = $categories[$reverse ? $row['from'] : $row['to']] ?? null;
                if (! $from || ! $to) continue;
                // Never repurpose a renamed card or overwrite a later category decision.
                DB::table('products')->where('id', $row['id'])->where('name', $row['name'])
                    ->where('category_id', $from)->update(['category_id' => $to, 'updated_at' => now()]);
            }
        });
    }
};
