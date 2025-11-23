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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('level', 50);
            $table->text('message');
            $table->json('context')->nullable();
            $table->text('details')->nullable();
            $table->string('user_email')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            
            $table->index('bot_id');
            $table->index('level');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
