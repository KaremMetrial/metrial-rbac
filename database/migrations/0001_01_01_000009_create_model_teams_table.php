<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_id');
            $table->string('model_type');
            $table->uuid('model_id');
            $table->boolean('is_owner')->default(false);
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();

            $table->index(['model_type', 'model_id']);
            $table->unique(['team_id', 'model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_teams');
    }
};
