<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_applications', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->json('product_categories')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new'); // new, contacted, approved, rejected
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_applications');
    }
};
