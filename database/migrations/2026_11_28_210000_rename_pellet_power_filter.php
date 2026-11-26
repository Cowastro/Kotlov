<?php

use App\Services\Catalog\BoilerAutomationGroups as Groups;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The burner power filter of «Пеллетные горелки» was named «Мощность», which the catalog hides on a parent section: the
 * command now fills «Мощность горелки», and the old select (only the power bands it created) goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $section = DB::table('categories')->where('slug', Groups::PELLET_BURNERS)->value('id');
        if ($section) {
            $ids = DB::table('attributes')->where(['category_id' => $section, 'name' => 'Мощность', 'type' => 'select', 'in_product' => false])->pluck('id');
            foreach ($ids as $id) {
                $options = DB::table('attribute_options')->where('attribute_id', $id)->pluck('name')->all();
                if (array_diff($options, Groups::PELLET_POWERS) === []) {
                    DB::table('product_attribute_values')->where('attribute_id', $id)->delete();
                    DB::table('attribute_options')->where('attribute_id', $id)->delete();
                    DB::table('attributes')->where('id', $id)->delete();
                }
            }
        }
        if (! app()->runningUnitTests()) {
            Artisan::call('catalog:sort-boiler-automation', ['--apply' => true]);
        }
    }

    public function down(): void {}
};
