<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| User Module Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('users')->name('users.')->group(function () {
    Route::middleware('can:usuarios.ver')->get('/', [UserController::class, 'index'])->name('index');
    Route::middleware('can:usuarios.crear')->post('/', [UserController::class, 'store'])->name('store');
    Route::middleware('can:usuarios.editar')->put('/{user}', [UserController::class, 'update'])->name('update');
    Route::middleware('can:usuarios.eliminar')->delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    
    // Rutas adicionales
    Route::middleware('can:usuarios.editar')->post('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('toggle-status');
    Route::middleware('can:usuarios.editar')->post('/{user}/change-password', [UserController::class, 'changePassword'])->name('change-password');
    Route::middleware('can:usuarios.ver')->get('/api/estadisticas', [UserController::class, 'estadisticas'])->name('estadisticas');
});