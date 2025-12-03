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
        Schema::create('contact_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action_type', 50); // command, payment, message, data_collection, block, etc
            $table->string('action', 100); // start, plan_selected, pix_payment, email_collected, etc
            $table->text('description')->nullable(); // Descrição legível da ação
            $table->json('metadata')->nullable(); // Dados adicionais (plan_id, amount, etc)
            $table->string('status', 50)->default('completed'); // pending, completed, failed, cancelled
            $table->timestamps();
            
            $table->index('bot_id');
            $table->index('contact_id');
            $table->index('transaction_id');
            $table->index('action_type');
            $table->index('action');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_actions');
    }
};
