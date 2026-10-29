<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_specifications', function (Blueprint $table): void {
            $table->string('parsing_status', 30)->default('pending')->after('status')->index();
            $table->text('parse_message')->nullable()->after('parsing_status');
            $table->timestamp('parsed_at')->nullable()->after('parse_message');
        });

        Schema::create('b2b_specification_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_specification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('b2b_specification_attachment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_sku')->nullable();
            $table->text('source_name')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 40)->nullable();
            $table->string('status', 30)->default('unmatched');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('match_reason', 50)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['b2b_specification_id', 'status'], 'b2b_spec_items_spec_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_specification_items');

        Schema::table('b2b_specifications', function (Blueprint $table): void {
            $table->dropIndex(['parsing_status']);
            $table->dropColumn(['parsing_status', 'parse_message', 'parsed_at']);
        });
    }
};
