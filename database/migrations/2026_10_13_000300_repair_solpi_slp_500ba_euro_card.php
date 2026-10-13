<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SKU = 'PS-002.366';

    private const SOURCE_URL = 'https://gig.by/catalog/stabilizator-napryazheniya/stabilizatory-napryazheniya-solpi-m/stabilizator-napryazheniya-odnofaznyy-elektronno-releynyy-solpi-m-slp-500ba-euro/';

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        DB::table('products')->where('sku', self::SKU)->update([
            'name' => 'Стабилизатор напряжения Solpi-M SLP-500BA EURO, 500 ВА, 2 розетки',
            'h1' => 'Стабилизатор напряжения Solpi-M SLP-500BA EURO, 500 ВА, 2 розетки',
            'price' => 180.00,
            'price_old' => null,
            'short_description' => 'Однофазный электронно-релейный стабилизатор Solpi-M SLP-500BA EURO мощностью 500 ВА с двумя розетками для котла и маломощной бытовой техники.',
            'content' => '<p><strong>Solpi-M SLP-500BA EURO</strong> — компактный однофазный электронно-релейный стабилизатор мощностью 500 ВА. Он предназначен для защиты газовых котлов, насосов и другой маломощной бытовой техники от пониженного, повышенного и нестабильного сетевого напряжения.</p><p>Модель позволяет выбрать выходное напряжение 220 или 230 В, оснащена двумя розетками, задержкой включения и металлическим корпусом для настенного либо напольного размещения. Встроенная автоматика защищает нагрузку от короткого замыкания, перегрева и перегрузки.</p>',
            'specs' => json_encode([
                ['key' => 'Тип', 'value' => 'Электронно-релейный стабилизатор'],
                ['key' => 'Модель', 'value' => 'SLP-500BA EURO'],
                ['key' => 'Количество фаз', 'value' => '1'],
                ['key' => 'Номинальная мощность', 'value' => '500 ВА'],
                ['key' => 'Выходное напряжение', 'value' => '220 / 230 В'],
                ['key' => 'Диапазон входного напряжения при 220 В', 'value' => '140–260 В'],
                ['key' => 'Диапазон входного напряжения при 230 В', 'value' => '150–270 В'],
                ['key' => 'Точность стабилизации', 'value' => '±5%'],
                ['key' => 'Количество розеток', 'value' => '2'],
                ['key' => 'КПД', 'value' => '90%'],
                ['key' => 'Степень защиты', 'value' => 'IP20'],
                ['key' => 'Материал корпуса', 'value' => 'Металл'],
                ['key' => 'Защита', 'value' => 'Короткое замыкание, перегрев, перегрузка, пониженное и повышенное напряжение'],
                ['key' => 'Гарантия', 'value' => '12 месяцев'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_source_url' => self::SOURCE_URL,
            'meta_title' => 'Solpi-M SLP-500BA EURO 500 ВА — купить за 180 BYN | KOTLOV',
            'meta_description' => 'Релейный стабилизатор Solpi-M SLP-500BA EURO 500 ВА: 2 розетки, вход 140–260 В, выход 220/230 В. Цена 180 BYN, доставка по Беларуси.',
            'is_active' => true,
            'is_archived' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The legacy text contained product-family claims that do not apply to
        // this relay model, so a rollback intentionally keeps the correction.
    }
};
