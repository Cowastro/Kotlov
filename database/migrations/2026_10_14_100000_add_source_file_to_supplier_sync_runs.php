<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_sync_runs', function (Blueprint $table): void {
            $table->string('source_file_name')->nullable()->after('supplier_names');
            $table->string('source_file_path')->nullable()->after('source_file_name');
            $table->string('source_file_type', 16)->nullable()->after('source_file_path');
            $table->unsignedBigInteger('source_file_size')->nullable()->after('source_file_type');
            $table->string('source_file_sha256', 64)->nullable()->after('source_file_size');
            $table->index('source_file_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_sync_runs', function (Blueprint $table): void {
            $table->dropIndex(['source_file_sha256']);
            $table->dropColumn([
                'source_file_name', 'source_file_path', 'source_file_type',
                'source_file_size', 'source_file_sha256',
            ]);
        });
    }
};
