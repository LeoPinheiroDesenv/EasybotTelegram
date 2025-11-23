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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->bigInteger('telegram_id');
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            
            $table->index('bot_id');
            $table->index('telegram_id');
            $table->unique(['bot_id', 'telegram_id'], 'contacts_bot_telegram_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
