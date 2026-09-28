<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('business_type_preset_id')->nullable()->constrained('business_type_presets');
            $table->string('name', 100);
            $table->unique(['business_type_preset_id', 'name']);
            $table->unique(['business_id', 'name']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE product_categories ADD CONSTRAINT chk_category_origin CHECK ((business_id IS NOT NULL AND business_type_preset_id IS NULL) OR (business_id IS NULL AND business_type_preset_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
