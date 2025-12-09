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
        Schema::table('logs', function (Blueprint $table) {
            $table->json('request')->nullable()->after('context')->comment('Dados da requisição HTTP (método, URL, headers, body)');
            $table->json('response')->nullable()->after('request')->comment('Dados da resposta HTTP (status, headers, body)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn(['request', 'response']);
        });
    }
};
