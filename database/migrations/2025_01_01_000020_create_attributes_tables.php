<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Атрибуты (привязаны к категории)
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->index(); // привязка к категории
            $table->unsignedBigInteger('group_id')->default(0); // группа атрибутов
            $table->integer('sort_order')->default(0);
            $table->enum('type', ['select', 'value', 'check'])->default('value');
            // select = выбор из вариантов (тип котла, камера сгорания)
            // value  = текстовое/числовое значение (мощность кВт, КПД %)
            // check  = да/нет (защита от замерзания, термостат)
            $table->string('name');                   // Мощность
            $table->string('suffix')->nullable();      // кВт, л, °C, %
            $table->boolean('in_filter')->default(false);  // показывать в фильтрах каталога
            $table->boolean('in_sort')->default(false);    // использовать для сортировки
            $table->boolean('in_product')->default(true);  // показывать на странице товара
            $table->boolean('in_brief')->default(false);   // показывать в карточке товара
            $table->boolean('is_comparable')->default(false); // показывать при сравнении
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });

        // Варианты для select-атрибутов
        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attribute_id')->index();
            $table->string('name');         // настенный / напольный / одноконтурный
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
        });

        // Значения атрибутов конкретного товара
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('attribute_id')->index();
            $table->unsignedBigInteger('option_id')->nullable(); // для select
            $table->boolean('is_checked')->nullable();           // для check
            $table->text('value')->nullable();                   // для value
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('option_id')->references('id')->on('attribute_options')->onDelete('set null');

            $table->unique(['product_id', 'attribute_id']); // один атрибут — одно значение
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
    }
};
