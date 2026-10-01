<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installer_works', function (Blueprint $table) {
            $table->foreignId('blog_post_id')
                ->nullable()
                ->after('installer_profile_id')
                ->constrained('blog_posts')
                ->nullOnDelete();

            $table->unique(['installer_profile_id', 'blog_post_id'], 'installer_work_blog_unique');
        });
    }

    public function down(): void
    {
        Schema::table('installer_works', function (Blueprint $table) {
            $table->dropUnique('installer_work_blog_unique');
            $table->dropConstrainedForeignId('blog_post_id');
        });
    }
};
