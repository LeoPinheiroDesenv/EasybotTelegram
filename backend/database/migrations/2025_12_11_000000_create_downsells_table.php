<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('downsells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('payment_plans')->onDelete('cascade');
            $table->string('title');
            $table->string('initial_media_url')->nullable();
            $table->text('message');
            $table->decimal('promotional_value', 10, 2);
            $table->integer('quantity_uses')->default(0)->comment('Quantidade de vezes que foi usado');
            $table->integer('max_uses')->nullable()->comment('Limite máximo de usos (null = ilimitado)');
            $table->integer('trigger_after_minutes')->default(0)->comment('Disparar após X minutos');
            $table->enum('trigger_event', ['payment_failed', 'payment_canceled', 'checkout_abandoned'])->default('payment_failed');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('bot_id');
            $table->index('plan_id');
            $table->index('active');
            $table->index('trigger_event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downsells');
    }
};
