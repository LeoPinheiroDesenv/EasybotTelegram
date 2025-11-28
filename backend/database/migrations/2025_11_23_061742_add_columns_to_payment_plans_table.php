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
        Schema::table('payment_plans', function (Blueprint $table) {
            // Verifica se as colunas já existem antes de adicionar
            if (!Schema::hasColumn('payment_plans', 'bot_id')) {
                $table->foreignId('bot_id')->after('id')->constrained('bots')->onDelete('cascade');
            }
            if (!Schema::hasColumn('payment_plans', 'payment_cycle_id')) {
                $table->foreignId('payment_cycle_id')->after('bot_id')->constrained('payment_cycles')->onDelete('restrict');
            }
            if (!Schema::hasColumn('payment_plans', 'title')) {
                $table->string('title')->after('payment_cycle_id');
            }
            if (!Schema::hasColumn('payment_plans', 'price')) {
                $table->decimal('price', 10, 2)->after('title');
            }
            if (!Schema::hasColumn('payment_plans', 'charge_period')) {
                $table->string('charge_period', 50)->after('price');
            }
            if (!Schema::hasColumn('payment_plans', 'cycle')) {
                $table->integer('cycle')->default(1)->after('charge_period');
            }
            if (!Schema::hasColumn('payment_plans', 'message')) {
                $table->text('message')->nullable()->after('cycle');
            }
            if (!Schema::hasColumn('payment_plans', 'pix_message')) {
                $table->text('pix_message')->nullable()->after('message');
            }
            if (!Schema::hasColumn('payment_plans', 'active')) {
                $table->boolean('active')->default(true)->after('pix_message');
            }
        });

        // Adiciona índices se não existirem
        Schema::table('payment_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_plans', 'bot_id')) {
                $table->index('bot_id');
            }
            if (!Schema::hasColumn('payment_plans', 'payment_cycle_id')) {
                $table->index('payment_cycle_id');
            }
            if (!Schema::hasColumn('payment_plans', 'active')) {
                $table->index('active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_plans', function (Blueprint $table) {
            // Remove foreign keys primeiro
            if (Schema::hasColumn('payment_plans', 'bot_id')) {
                $table->dropForeign(['bot_id']);
            }
            if (Schema::hasColumn('payment_plans', 'payment_cycle_id')) {
                $table->dropForeign(['payment_cycle_id']);
            }
            
            // Remove índices
            $table->dropIndex(['bot_id']);
            $table->dropIndex(['payment_cycle_id']);
            $table->dropIndex(['active']);
            
            // Remove colunas
            $columns = ['bot_id', 'payment_cycle_id', 'title', 'price', 'charge_period', 'cycle', 'message', 'pix_message', 'active'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('payment_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
