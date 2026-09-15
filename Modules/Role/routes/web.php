<?php

use Illuminate\Support\Facades\Route;
use Modules\Role\Http\Controllers\RoleController;
use Modules\Role\Http\Controllers\ModuleCommentController;

/*
|--------------------------------------------------------------------------
| Role Module Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('roles')->name('roles.')->group(function () {
    Route::middleware('can:roles.ver')->get('/', [RoleController::class, 'index'])->name('index');
    Route::middleware('can:roles.crear')->post('/', [RoleController::class, 'store'])->name('store');
    Route::middleware('can:roles.editar')->put('/{role}', [RoleController::class, 'update'])->name('update');
    Route::middleware('can:roles.eliminar')->delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    
    // Rutas adicionales para manejo de permisos
    Route::middleware('can:roles.editar')->post('/{role}/assign-permissions', [RoleController::class, 'assignPermissions'])->name('assign-permissions');
    Route::middleware('can:roles.editar')->post('/{role}/remove-permissions', [RoleController::class, 'removePermissions'])->name('remove-permissions');
    Route::middleware('can:roles.ver')->get('/api/permissions', [RoleController::class, 'getPermissions'])->name('permissions');
    // Auditoría del módulo (el controlador exige rol admin). Antes de /{role}/... para no chocar.
    Route::middleware('can:roles.ver')->get('/auditoria', [RoleController::class, 'auditoria'])->name('auditoria');
    // Usuarios que tienen un rol (modal "Ver usuarios" de la tarjeta)
    Route::middleware('can:roles.ver')->get('/{role}/usuarios', [RoleController::class, 'usuarios'])->name('usuarios');
    Route::middleware('can:roles.ver')->get('/search', [RoleController::class, 'search'])->name('search');
    Route::middleware('can:roles.ver')->get('/api/estadisticas', [RoleController::class, 'estadisticas'])->name('estadisticas');

    // Comentarios/descripciones por MÓDULO de permisos (documentar para qué sirve cada grupo).
    // La LECTURA viaja en el payload de index(); la ESCRITURA la restringe a admin el controlador.
    // Prefijo literal 'modules/' → no choca con /{role}. Va antes de que /{role} capture segmentos.
    Route::middleware('can:roles.ver')->post('/modules/{module}/comment', [ModuleCommentController::class, 'store'])->name('modules.comment.store');
    Route::middleware('can:roles.ver')->delete('/modules/{module}/comment', [ModuleCommentController::class, 'destroy'])->name('modules.comment.destroy');
});