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
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nome do cron job');
            $table->text('description')->nullable()->comment('Descrição do que o cron job faz');
            $table->string('endpoint')->comment('Endpoint da API a ser chamado');
            $table->enum('method', ['GET', 'POST', 'PUT', 'DELETE'])->default('POST')->comment('Método HTTP');
            $table->string('frequency')->comment('Frequência no formato cron (ex: */5 * * * *)');
            $table->text('headers')->nullable()->comment('Headers HTTP em JSON');
            $table->text('body')->nullable()->comment('Body da requisição em JSON');
            $table->boolean('is_active')->default(true)->comment('Se o cron job está ativo');
            $table->boolean('is_system')->default(false)->comment('Se é um cron job do sistema (não pode ser deletado)');
            $table->timestamp('last_run_at')->nullable()->comment('Última vez que foi executado');
            $table->text('last_response')->nullable()->comment('Última resposta da execução');
            $table->boolean('last_success')->nullable()->comment('Se a última execução foi bem-sucedida');
            $table->integer('run_count')->default(0)->comment('Contador de execuções');
            $table->integer('success_count')->default(0)->comment('Contador de sucessos');
            $table->integer('error_count')->default(0)->comment('Contador de erros');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('is_active');
            $table->index('is_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
