<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->date('closed_on');
            $table->decimal('total_sold', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('total_credit', 12, 2)->default(0);
            $table->foreignId('top_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['business_id', 'closed_on']);
            $table->index(['business_id', 'closed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closes');
    }
};
