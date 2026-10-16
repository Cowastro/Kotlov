<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropUnique('commercial_documents_number_unique');
        });

        DB::table('commercial_documents')
            ->whereNotNull('revision_of_id')
            ->where('status', 'draft')
            ->orderBy('id')
            ->each(function (object $revision): void {
                $rootNumber = DB::table('commercial_documents')
                    ->where('id', $revision->revision_of_id)
                    ->value('number');

                if (filled($rootNumber)) {
                    DB::table('commercial_documents')
                        ->where('id', $revision->id)
                        ->update(['number' => $rootNumber]);
                }
            });

        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->unique(
                ['number', 'revision_number'],
                'commercial_documents_number_revision_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropUnique('commercial_documents_number_revision_unique');
        });

        DB::table('commercial_documents')
            ->whereNotNull('revision_of_id')
            ->orderBy('id')
            ->each(function (object $revision): void {
                $rootNumber = DB::table('commercial_documents')
                    ->where('id', $revision->revision_of_id)
                    ->value('number');

                if ($revision->number !== $rootNumber) {
                    return;
                }

                DB::table('commercial_documents')
                    ->where('id', $revision->id)
                    ->update([
                        'number' => $revision->number.'-R'.((int) $revision->revision_number),
                    ]);
            });

        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->unique('number');
        });
    }
};
