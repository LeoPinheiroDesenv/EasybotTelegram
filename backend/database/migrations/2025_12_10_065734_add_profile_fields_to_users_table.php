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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'address_street')) {
                $table->string('address_street', 255)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'address_number')) {
                $table->string('address_number', 20)->nullable()->after('address_street');
            }
            if (!Schema::hasColumn('users', 'address_zipcode')) {
                $table->string('address_zipcode', 10)->nullable()->after('address_number');
            }
            if (!Schema::hasColumn('users', 'municipality_id')) {
                $table->unsignedBigInteger('municipality_id')->nullable()->after('address_zipcode');
            }
            if (!Schema::hasColumn('users', 'state_id')) {
                $table->unsignedBigInteger('state_id')->nullable()->after('municipality_id');
            }
        });

        // Adiciona foreign keys apenas se as tabelas existirem e as colunas existirem
        if (Schema::hasTable('municipalities') && Schema::hasTable('states')) {
            Schema::table('users', function (Blueprint $table) {
                // Verifica se a foreign key já não existe
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'users' 
                    AND COLUMN_NAME = 'municipality_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                if (empty($foreignKeys) && Schema::hasColumn('users', 'municipality_id')) {
                    $table->foreign('municipality_id')->references('id')->on('municipalities')->onDelete('set null');
                }
                
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'users' 
                    AND COLUMN_NAME = 'state_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                if (empty($foreignKeys) && Schema::hasColumn('users', 'state_id')) {
                    $table->foreign('state_id')->references('id')->on('states')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['municipality_id']);
            $table->dropForeign(['state_id']);
            $table->dropColumn(['phone', 'address_street', 'address_number', 'address_zipcode', 'municipality_id', 'state_id']);
        });
    }
};
