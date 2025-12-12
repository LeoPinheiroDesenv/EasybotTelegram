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
        Schema::table('cron_jobs', function (Blueprint $table) {
            $table->integer('cpanel_cron_id')->nullable()->after('is_system')->comment('ID do cron job no cPanel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cron_jobs', function (Blueprint $table) {
            $table->dropColumn('cpanel_cron_id');
        });
    }
};
