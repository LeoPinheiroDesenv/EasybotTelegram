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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type', 20)->default('user')->after('role');
                // user, admin, super_admin
            }
            if (!Schema::hasColumn('users', 'user_group_id')) {
                $table->unsignedBigInteger('user_group_id')->nullable()->after('user_type');
                $table->foreign('user_group_id')->references('id')->on('user_groups')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'user_group_id')) {
                $table->dropForeign(['user_group_id']);
                $table->dropColumn('user_group_id');
            }
            if (Schema::hasColumn('users', 'user_type')) {
                $table->dropColumn('user_type');
            }
        });
    }
};

