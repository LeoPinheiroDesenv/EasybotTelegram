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
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'telegram_status')) {
                $table->enum('telegram_status', ['active', 'inactive'])->default('inactive')->after('is_blocked');
            }
            if (!Schema::hasColumn('contacts', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('telegram_status');
            }
            if (!Schema::hasColumn('contacts', 'last_interaction_at')) {
                $table->timestamp('last_interaction_at')->nullable()->after('expires_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'telegram_status')) {
                $table->dropColumn('telegram_status');
            }
            if (Schema::hasColumn('contacts', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('contacts', 'last_interaction_at')) {
                $table->dropColumn('last_interaction_at');
            }
        });
    }
};
