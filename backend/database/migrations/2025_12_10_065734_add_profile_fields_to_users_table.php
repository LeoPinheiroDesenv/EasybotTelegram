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
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('address_street', 255)->nullable()->after('phone');
            $table->string('address_number', 20)->nullable()->after('address_street');
            $table->string('address_zipcode', 10)->nullable()->after('address_number');
            $table->unsignedBigInteger('municipality_id')->nullable()->after('address_zipcode');
            $table->unsignedBigInteger('state_id')->nullable()->after('municipality_id');
            
            $table->foreign('municipality_id')->references('id')->on('municipalities')->onDelete('set null');
            $table->foreign('state_id')->references('id')->on('states')->onDelete('set null');
        });
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
