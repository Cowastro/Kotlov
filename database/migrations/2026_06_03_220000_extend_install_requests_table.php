<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('install_requests', function (Blueprint $table) {

            // Тип работ (heating, heatpump, fireplace, chimney, sauna, service, commissioning, other)
            $table->string('specialization')->nullable()->after('description');

            // Адрес объекта
            $table->string('address')->nullable()->after('city');

            // Бюджет клиента
            $table->decimal('budget', 12, 2)->nullable()->after('price_agreed');

            // Источник заявки: installers_page, installer_profile, product_page, cart, admin, other
            $table->string('source')->nullable()->after('budget');

            // Внутренние заметки менеджера/монтажника
            $table->text('notes')->nullable()->after('source');

            // Связь с профилем монтажника (в дополнение к существующему installer_id -> users)
            $table->foreignId('installer_profile_id')
                ->nullable()
                ->after('notes')
                ->constrained('installer_profiles')
                ->nullOnDelete();

            // Примечание: поле status (enum) уже существует.
            // Его тип будет расширен отдельной миграцией после согласования,
            // так как ALTER COLUMN для enum требует явного переопределения.
        });
    }

    public function down(): void
    {
        Schema::table('install_requests', function (Blueprint $table) {
            $table->dropForeign(['installer_profile_id']);
            $table->dropColumn([
                'specialization',
                'address',
                'budget',
                'source',
                'notes',
                'installer_profile_id',
            ]);
        });
    }
};
