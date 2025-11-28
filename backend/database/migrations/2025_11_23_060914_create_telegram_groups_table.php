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
        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->onDelete('cascade');
            $table->string('title');
            $table->string('telegram_group_id');
            $table->foreignId('payment_plan_id')->nullable()->constrained('payment_plans')->onDelete('set null');
            $table->string('invite_link')->nullable();
            $table->enum('type', ['group', 'channel'])->default('group');
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('bot_id');
            $table->index('telegram_group_id');
            $table->unique(['bot_id', 'telegram_group_id']); // Um grupo/canal só pode estar associado a um bot uma vez
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_groups');
    }
};
