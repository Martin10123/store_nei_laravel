<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_movements', function (Blueprint $table) {
            $table->date('due_on')->nullable();
        });

        Schema::table('daily_closes', function (Blueprint $table) {
            $table->text('ai_summary')->nullable();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('alert_type', 30);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('credit_customer_id')->nullable()->constrained('credit_customers')->nullOnDelete();
            $table->string('message', 250);
            $table->boolean('is_resolved')->default(false);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['business_id', 'is_resolved']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE alerts ADD CONSTRAINT chk_alert_type CHECK (alert_type IN ('low_stock', 'expiration', 'overdue_credit'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::table('daily_closes', function (Blueprint $table) {
            $table->dropColumn('ai_summary');
        });
        Schema::table('credit_movements', function (Blueprint $table) {
            $table->dropColumn('due_on');
        });
    }
};
