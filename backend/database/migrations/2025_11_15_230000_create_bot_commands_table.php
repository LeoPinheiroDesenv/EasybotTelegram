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
        Schema::create('bot_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->string('command', 50); // Ex: /custom, /info, etc (sem a barra)
            $table->text('response'); // Resposta do comando
            $table->text('description')->nullable(); // Descrição do comando
            $table->boolean('active')->default(true);
            $table->integer('usage_count')->default(0); // Contador de uso
            $table->timestamps();

            $table->index('bot_id');
            $table->index('command');
            $table->unique(['bot_id', 'command'], 'bot_commands_bot_command_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_commands');
    }
};

