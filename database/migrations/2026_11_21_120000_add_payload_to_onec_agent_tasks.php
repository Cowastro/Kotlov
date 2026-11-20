<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What a task without a subject asks the agent to do (the 1C lists to read), and what came back. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_agent_tasks', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('onec_agent_tasks', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
