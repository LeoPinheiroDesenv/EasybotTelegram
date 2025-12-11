<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primeiro, converte os valores existentes de string para JSON array
        DB::statement("
            UPDATE bots 
            SET payment_method = CASE 
                WHEN payment_method = 'credit_card' THEN JSON_ARRAY('credit_card')
                WHEN payment_method = 'pix' THEN JSON_ARRAY('pix')
                WHEN payment_method IS NULL OR payment_method = '' THEN JSON_ARRAY('credit_card')
                ELSE JSON_ARRAY(payment_method)
            END
            WHERE payment_method IS NOT NULL
        ");

        // Altera o tipo da coluna para JSON
        // Nota: MySQL/MariaDB pode ter problemas com default em JSON, então fazemos em duas etapas
        DB::statement("ALTER TABLE bots MODIFY COLUMN payment_method JSON NOT NULL");
        
        // Define o default usando uma trigger ou atualiza registros sem valor
        DB::statement("
            UPDATE bots 
            SET payment_method = JSON_ARRAY('credit_card')
            WHERE payment_method IS NULL OR JSON_LENGTH(payment_method) = 0
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Converte JSON array de volta para string (pega o primeiro elemento)
        DB::statement("
            UPDATE bots 
            SET payment_method = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payment_method, '$[0]')), 'credit_card')
            WHERE payment_method IS NOT NULL
        ");

        // Altera o tipo da coluna de volta para string
        Schema::table('bots', function (Blueprint $table) {
            $table->string('payment_method')->default('credit_card')->change();
        });
    }
};
