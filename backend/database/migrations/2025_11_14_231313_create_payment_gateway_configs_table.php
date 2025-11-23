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
        Schema::create('payment_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->string('gateway', 50);
            $table->string('environment', 50)->default('sandbox');
            $table->string('api_key', 255)->nullable();
            $table->text('api_secret')->nullable();
            $table->string('webhook_url', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('bot_id');
            $table->index('gateway');
            $table->unique(['bot_id', 'gateway', 'environment'], 'payment_gateway_configs_bot_gateway_env_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
    }
};
