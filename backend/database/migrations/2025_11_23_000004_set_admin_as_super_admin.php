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
        // Define o usuário admin@admin.com como super_admin
        DB::table('users')
            ->where('email', 'admin@admin.com')
            ->update(['user_type' => 'super_admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverte para admin (não pode ser user pois perderia acesso)
        DB::table('users')
            ->where('email', 'admin@admin.com')
            ->update(['user_type' => 'admin']);
    }
};

