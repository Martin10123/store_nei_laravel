<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->index('business_id');
        });

        Schema::create('supplier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('source', 20)->default('manual');
            $table->timestampTz('recorded_at')->useCurrent();

            $table->index(['product_id', 'recorded_at']);
            $table->index('supplier_id');
        });

        Schema::create('price_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();

            $table->index('business_id');
        });

        Schema::create('price_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('price_source_id')->nullable()->constrained('price_sources')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('found_name', 200);
            $table->decimal('found_price', 12, 2);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
            $table->index('product_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE supplier_prices ADD CONSTRAINT chk_supplier_price_source CHECK (source IN ('manual'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quotes');
        Schema::dropIfExists('price_sources');
        Schema::dropIfExists('supplier_prices');
        Schema::dropIfExists('suppliers');
    }
};
