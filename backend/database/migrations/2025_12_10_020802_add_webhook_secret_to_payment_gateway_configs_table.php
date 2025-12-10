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
            if (!Schema::hasColumn('payment_gateway_configs', 'webhook_secret')) {
                $table->text('webhook_secret')->nullable()->after('webhook_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateway_configs', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateway_configs', 'webhook_secret')) {
                $table->dropColumn('webhook_secret');
            }
        });
    }
};
