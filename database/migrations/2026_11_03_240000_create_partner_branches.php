<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A retail point of a partner in a city: shown in "Где купить" on product cards.
        Schema::create('partner_branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('b2b_client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->json('phones');
            $table->string('email')->nullable();
            $table->string('working_hours')->nullable();
            $table->string('map_url', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
            $table->index(['city_id', 'is_active', 'sort_order']);
        });

        // Every phone reveal is logged: the centre sees which branch got which customer interest.
        Schema::create('partner_branch_contact_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('page', 40)->default('product');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['partner_branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_branch_contact_views');
        Schema::dropIfExists('partner_branches');
    }
};
