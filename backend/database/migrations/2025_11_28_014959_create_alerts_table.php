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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->nullable()->constrained('payment_plans')->onDelete('set null');
            $table->enum('alert_type', ['scheduled', 'periodic', 'common'])->default('common');
            $table->text('message');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->enum('user_language', ['pt', 'en', 'es'])->default('pt');
            $table->enum('user_category', ['all', 'premium', 'free'])->default('all');
            $table->string('file_url')->nullable();
            $table->enum('status', ['active', 'inactive', 'sent'])->default('active');
            $table->timestamp('sent_at')->nullable();
            $table->integer('sent_count')->default(0);
            $table->timestamps();

            $table->index('bot_id');
            $table->index('plan_id');
            $table->index('alert_type');
            $table->index('status');
            $table->index('scheduled_date');
            $table->index(['scheduled_date', 'scheduled_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
