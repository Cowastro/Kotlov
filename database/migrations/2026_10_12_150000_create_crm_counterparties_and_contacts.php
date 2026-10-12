<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_counterparties', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('organization');
            $table->string('classification')->default('unclassified');
            $table->string('business_unit')->default('wholesale');
            $table->string('status')->default('active');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('unp', 32)->nullable()->index();
            $table->string('external_1c_id')->nullable()->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('manual');
            $table->string('website')->nullable();
            $table->text('legal_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();

            $table->index(['classification', 'status']);
            $table->index(['business_unit', 'status']);
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counterparty_id')->nullable()->constrained('crm_counterparties')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('external_1c_id')->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['counterparty_id', 'is_active']);
        });

        Schema::create('crm_contact_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('type');
            $table->string('value');
            $table->string('normalized_value')->index();
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'normalized_value', 'is_active'], 'crm_contact_point_lookup');
        });

        Schema::table('telephony_calls', function (Blueprint $table) {
            $table->foreignId('crm_counterparty_id')->nullable()->after('matched_user_id')
                ->constrained('crm_counterparties')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->after('crm_counterparty_id')
                ->constrained('crm_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telephony_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_contact_id');
            $table->dropConstrainedForeignId('crm_counterparty_id');
        });

        Schema::dropIfExists('crm_contact_points');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_counterparties');
    }
};
