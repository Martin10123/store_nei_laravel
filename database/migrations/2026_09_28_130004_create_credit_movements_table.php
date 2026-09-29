<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_customer_id')->constrained('credit_customers')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('movement_type', 10);
            $table->decimal('amount', 12, 2);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['credit_customer_id', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE credit_movements ADD CONSTRAINT chk_credit_movement_type CHECK (movement_type IN ('charge', 'payment'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_movements');
    }
};
