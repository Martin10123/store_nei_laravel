<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('phone_number', 30);
            $table->string('direction', 10);
            $table->text('body');
            $table->string('detected_intent', 50)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE whatsapp_messages ADD CONSTRAINT chk_whatsapp_direction CHECK (direction IN ('inbound', 'outbound'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
