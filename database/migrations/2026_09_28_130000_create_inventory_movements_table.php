<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 20);
            $table->decimal('quantity', 12, 3);
            $table->string('reason', 200)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT chk_movement_type CHECK (movement_type IN ('in', 'out', 'waste', 'adjustment'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
