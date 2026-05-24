<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbac_audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->string('action');
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('context')->default('http')->comment('http, cli, queue, api');
            $table->timestamps();

            $table->index('actor_id');
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_audit_log');
    }
};
