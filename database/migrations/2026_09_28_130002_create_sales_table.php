<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('credit_customer_id')->nullable()->constrained('credit_customers')->nullOnDelete();
            $table->decimal('total', 12, 2);
            $table->string('payment_method', 20)->default('cash');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
            $table->index('credit_customer_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sales ADD CONSTRAINT chk_payment_method CHECK (payment_method IN ('cash', 'nequi', 'daviplata', 'credit', 'card'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
