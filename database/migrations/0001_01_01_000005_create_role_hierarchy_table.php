<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_hierarchy', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ancestor_id');
            $table->uuid('descendant_id');
            $table->unsignedInteger('depth')->default(0);
            $table->timestamps();

            $table->foreign('ancestor_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('descendant_id')->references('id')->on('roles')->cascadeOnDelete();

            $table->unique(['ancestor_id', 'descendant_id']);
            $table->index('descendant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_hierarchy');
    }
};
