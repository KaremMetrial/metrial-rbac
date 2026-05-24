<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->nullable();
            $table->uuid('role_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->string('guard_name')->default('web');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();

            $table->index(['model_type', 'model_id']);
            $table->index('guard_name');
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_roles');
    }
};
