<?php

use Illuminate\Support\Facades\Route;
use Metrial\RBAC\Controllers\Api\Rbac\RoleController;
use Metrial\RBAC\Controllers\Api\Rbac\PermissionController;
use Metrial\RBAC\Controllers\Api\Rbac\TeamController;
use Metrial\RBAC\Controllers\Api\Rbac\AuditLogController;

Route::apiResource('roles', RoleController::class);
Route::apiResource('permissions', PermissionController::class);
Route::apiResource('teams', TeamController::class)->except(['store', 'update', 'destroy']);
Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);

Route::prefix('teams/{team}')->group(function () {
    Route::post('members', [TeamController::class, 'addMember']);
    Route::delete('members', [TeamController::class, 'removeMember']);
});
