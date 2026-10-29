<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_specification_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_specification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_version_id')->nullable()->constrained('b2b_specification_versions')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('source', 30)->default('upload');
            $table->string('parsing_status', 30)->default('pending');
            $table->text('note')->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('suggested_count')->default(0);
            $table->json('attachment_ids')->nullable();
            $table->json('diff_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['b2b_specification_id', 'number'], 'b2b_spec_versions_spec_number_unique');
        });

        Schema::table('b2b_specification_items', function (Blueprint $table): void {
            $table->foreignId('b2b_specification_version_id')->nullable()->after('b2b_specification_id')
                ->constrained('b2b_specification_versions')->nullOnDelete();
            $table->boolean('is_current')->default(true)->after('b2b_specification_version_id')->index();
        });

        Schema::table('b2b_specifications', function (Blueprint $table): void {
            $table->foreignId('current_version_id')->nullable()->after('parsed_at')
                ->constrained('b2b_specification_versions')->nullOnDelete();
        });

        DB::table('b2b_specifications')->orderBy('id')->each(function (object $specification): void {
            $items = DB::table('b2b_specification_items')->where('b2b_specification_id', $specification->id);
            $itemCount = (clone $items)->count();
            $matchedCount = (clone $items)->where('status', 'matched')->count();
            $suggestedCount = (clone $items)->where('status', 'suggested')->count();
            $attachmentIds = DB::table('b2b_specification_attachments')
                ->where('b2b_specification_id', $specification->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $timestamp = $specification->parsed_at ?? $specification->created_at ?? now();
            $versionId = DB::table('b2b_specification_versions')->insertGetId([
                'b2b_specification_id' => $specification->id,
                'parent_version_id' => null,
                'number' => 1,
                'source' => 'migration',
                'parsing_status' => $specification->parsing_status ?? 'pending',
                'note' => null,
                'message' => $specification->parse_message,
                'item_count' => $itemCount,
                'matched_count' => $matchedCount,
                'suggested_count' => $suggestedCount,
                'attachment_ids' => json_encode($attachmentIds, JSON_THROW_ON_ERROR),
                'diff_summary' => json_encode([
                    'added' => $itemCount,
                    'removed' => 0,
                    'changed' => 0,
                    'unchanged' => 0,
                ], JSON_THROW_ON_ERROR),
                'created_by' => $specification->user_id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            DB::table('b2b_specification_items')
                ->where('b2b_specification_id', $specification->id)
                ->update(['b2b_specification_version_id' => $versionId, 'is_current' => true]);
            DB::table('b2b_specifications')
                ->where('id', $specification->id)
                ->update(['current_version_id' => $versionId]);
        });
    }

    public function down(): void
    {
        Schema::table('b2b_specifications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_version_id');
        });
        Schema::table('b2b_specification_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('b2b_specification_version_id');
            $table->dropColumn('is_current');
        });
        Schema::dropIfExists('b2b_specification_versions');
    }
};
