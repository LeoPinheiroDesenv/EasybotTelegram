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
        Schema::table('bots', function (Blueprint $table) {
            // Mensagens de boas-vindas
            $table->text('initial_message')->nullable()->after('telegram_group_id');
            $table->text('top_message')->nullable()->after('initial_message');
            $table->string('button_message')->nullable()->after('top_message');
            $table->boolean('activate_cta')->default(false)->after('button_message');
            
            // URLs de mídia
            $table->string('media_1_url')->nullable()->after('activate_cta');
            $table->string('media_2_url')->nullable()->after('media_1_url');
            $table->string('media_3_url')->nullable()->after('media_2_url');
            
            // Configurações de privacidade
            $table->boolean('request_email')->default(false)->after('media_3_url');
            $table->boolean('request_phone')->default(false)->after('request_email');
            $table->boolean('request_language')->default(false)->after('request_phone');
            
            // Configurações de pagamento
            $table->string('payment_method')->default('credit_card')->after('request_language');
            
            // Status de ativação
            $table->boolean('activated')->default(false)->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn([
                'initial_message',
                'top_message',
                'button_message',
                'activate_cta',
                'media_1_url',
                'media_2_url',
                'media_3_url',
                'request_email',
                'request_phone',
                'request_language',
                'payment_method',
                'activated',
            ]);
        });
    }
};
