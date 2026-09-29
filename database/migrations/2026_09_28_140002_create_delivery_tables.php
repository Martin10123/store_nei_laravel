<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('public_slug', 100)->nullable()->unique();
        });

        Schema::create('end_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('address', 200)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('business_id');
            $table->index(['business_id', 'phone']);
        });

        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('end_customer_id')->constrained('end_customers')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('delivery_address', 200);
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 20)->default('cash');
            $table->string('notes', 300)->nullable();
            $table->string('origin', 20)->default('panel');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['business_id', 'status']);
            $table->index('end_customer_id');
        });

        Schema::create('delivery_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);

            $table->index('delivery_order_id');
            $table->index('product_id');
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('reserved_quantity', 12, 3);
            $table->boolean('is_released')->default(false);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['product_id', 'is_released']);
            $table->index('delivery_order_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE delivery_orders ADD CONSTRAINT chk_delivery_status CHECK (status IN ('pending', 'confirmed', 'in_transit', 'delivered', 'cancelled'))");
            DB::statement("ALTER TABLE delivery_orders ADD CONSTRAINT chk_delivery_payment CHECK (payment_method IN ('cash', 'card'))");
            DB::statement("ALTER TABLE delivery_orders ADD CONSTRAINT chk_delivery_origin CHECK (origin IN ('panel', 'catalog_web', 'whatsapp'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('delivery_order_lines');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('end_customers');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique(['public_slug']);
            $table->dropColumn('public_slug');
        });
    }
};
