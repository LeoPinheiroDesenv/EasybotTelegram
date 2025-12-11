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
        Schema::table('payment_gateway_configs', function (Blueprint $table) {
            // Adiciona campos para credenciais completas do Mercado Pago
            $table->string('public_key', 255)->nullable()->after('api_key');
            $table->string('client_id', 255)->nullable()->after('public_key');
            $table->string('client_secret', 255)->nullable()->after('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateway_configs', function (Blueprint $table) {
            $table->dropColumn(['public_key', 'client_id', 'client_secret']);
        });
    }
};
