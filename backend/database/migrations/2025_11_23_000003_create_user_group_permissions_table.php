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
        Schema::create('user_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_group_id');
            $table->string('resource_type'); // 'menu' ou 'bot'
            $table->string('resource_id')->nullable(); // ID do bot (número) ou nome do menu (string)
            $table->string('permission'); // 'read', 'write', 'delete'
            $table->timestamps();

            $table->foreign('user_group_id')->references('id')->on('user_groups')->onDelete('cascade');
            $table->unique(['user_group_id', 'resource_type', 'resource_id', 'permission'], 'unique_group_permission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_group_permissions');
    }
};

