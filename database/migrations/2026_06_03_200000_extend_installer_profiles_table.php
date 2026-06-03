<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installer_profiles', function (Blueprint $table) {

            // 1. Контакты
            $table->string('contact_name')->nullable()->after('user_id');
            $table->string('phone')->nullable()->after('contact_name');
            $table->string('additional_phone')->nullable()->after('phone');
            $table->string('email')->nullable()->after('additional_phone');
            $table->string('website')->nullable()->after('email');
            $table->string('telegram')->nullable()->after('website');
            $table->string('viber')->nullable()->after('telegram');
            $table->string('whatsapp')->nullable()->after('viber');

            // 2. Компания
            $table->string('company_name')->nullable()->after('whatsapp');
            $table->string('legal_name')->nullable()->after('company_name');
            $table->string('unp', 20)->nullable()->after('legal_name');
            $table->string('address')->nullable()->after('unp');

            // 3. География (city и region уже есть, work_regions уже есть — не дублируем)
            $table->json('work_cities')->nullable()->after('work_regions');
            $table->integer('work_radius_km')->nullable()->after('work_cities');
            $table->boolean('nationwide')->default(false)->after('work_radius_km');

            // 4. Публичный профиль
            $table->string('slug')->nullable()->unique()->after('nationwide');
            $table->string('short_description')->nullable()->after('slug');
            $table->string('logo')->nullable()->after('short_description');
            $table->json('gallery')->nullable()->after('logo');
            $table->json('certificate_files')->nullable()->after('gallery');
            // certificate_photo (varchar) — оставляем, не удаляем
            $table->boolean('is_published')->default(false)->after('certificate_files');
            $table->string('status')->default('pending')->after('is_published');
            // Допустимые значения status: pending, active, suspended, blocked
        });
    }

    public function down(): void
    {
        Schema::table('installer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name', 'phone', 'additional_phone', 'email',
                'website', 'telegram', 'viber', 'whatsapp',
                'company_name', 'legal_name', 'unp', 'address',
                'work_cities', 'work_radius_km', 'nationwide',
                'slug', 'short_description', 'logo',
                'gallery', 'certificate_files',
                'is_published', 'status',
            ]);
        });
    }
};
