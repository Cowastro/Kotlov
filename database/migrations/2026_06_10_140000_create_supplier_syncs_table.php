<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('code')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('command');
            $table->string('source_url')->nullable();
            $table->string('image_disk_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status')->default('never');
            $table->integer('last_exit_code')->nullable();
            $table->string('last_log_path')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('supplier_syncs')->insert([
            'key' => 'elicon_gas_meters',
            'name' => 'Эликон',
            'code' => 'elicon',
            'title' => 'Эликон: счетчики газа',
            'description' => 'Обновляет цены, наличие, описания, характеристики и фотографии бытовых счетчиков газа.',
            'command' => 'supplier:sync-elicon-gas-meters',
            'source_url' => 'https://elicon.by/product-category/bitovie_schetchiki_gaza/',
            'image_disk_path' => 'img/products/elicon',
            'is_active' => true,
            'last_status' => 'never',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_syncs');
    }
};
