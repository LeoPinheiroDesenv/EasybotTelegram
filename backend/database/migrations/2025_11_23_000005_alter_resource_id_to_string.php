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
        Schema::table('user_group_permissions', function (Blueprint $table) {
            // Altera resource_id de unsignedBigInteger para string para suportar nomes de menus
            $table->string('resource_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_group_permissions', function (Blueprint $table) {
            // Reverte para unsignedBigInteger (pode causar perda de dados se houver strings)
            $table->unsignedBigInteger('resource_id')->nullable()->change();
        });
    }
};

