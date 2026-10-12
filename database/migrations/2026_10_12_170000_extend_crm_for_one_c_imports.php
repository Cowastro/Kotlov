<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_counterparties', function (Blueprint $table) {
            $table->string('public_name')->nullable()->after('name');
            $table->string('business_region')->nullable()->after('legal_name');
            $table->string('one_c_manager_name')->nullable()->after('business_region');
            $table->date('registered_at')->nullable()->after('one_c_manager_name');
            $table->string('abc_xyz_classification')->nullable()->after('registered_at');
            $table->text('postal_address')->nullable()->after('legal_address');
            $table->text('actual_address')->nullable()->after('postal_address');
        });

        Schema::table('crm_contact_points', function (Blueprint $table) {
            $table->foreignId('counterparty_id')->nullable()->after('contact_id')
                ->constrained('crm_counterparties')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->change();
            $table->index(['counterparty_id', 'type', 'normalized_value'], 'crm_counterparty_point_lookup');
        });

        Schema::create('crm_external_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counterparty_id')->constrained('crm_counterparties')->cascadeOnDelete();
            $table->string('source');
            $table->string('external_id');
            $table->string('external_code')->nullable();
            $table->string('external_name')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id'], 'crm_external_identity_unique');
            $table->index(['counterparty_id', 'source']);
        });

        Schema::create('crm_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('import_type');
            $table->string('file_name');
            $table->string('checksum', 64)->nullable()->index();
            $table->string('status')->default('processing');
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->unsignedInteger('contact_point_count')->default(0);
            $table->unsignedInteger('call_match_count')->default(0);
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_import_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->nullable()->constrained('crm_import_runs')->cascadeOnDelete();
            $table->string('source');
            $table->string('import_type');
            $table->string('external_id')->nullable();
            $table->string('external_name');
            $table->string('reason');
            $table->json('candidate_ids')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('resolved_counterparty_id')->nullable()->constrained('crm_counterparties')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_import_conflicts');
        Schema::dropIfExists('crm_import_runs');
        Schema::dropIfExists('crm_external_identities');

        Schema::table('crm_contact_points', function (Blueprint $table) {
            $table->dropIndex('crm_counterparty_point_lookup');
            $table->dropConstrainedForeignId('counterparty_id');
            $table->foreignId('contact_id')->nullable(false)->change();
        });

        Schema::table('crm_counterparties', function (Blueprint $table) {
            $table->dropColumn([
                'public_name', 'business_region', 'one_c_manager_name', 'registered_at',
                'abc_xyz_classification', 'postal_address', 'actual_address',
            ]);
        });
    }
};
