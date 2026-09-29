<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->string('document_number', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_customers');
    }
};
