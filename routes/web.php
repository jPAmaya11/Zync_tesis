<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
use Modules\GestionProyectos\Http\Controllers\GpDashboardController;

// ===================
// RUTA INICIAL
// ===================
Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

// ===================
// AUTENTICACIÓN
// ===================
require __DIR__ . '/auth.php';

// ===================
// RUTAS PROTEGIDAS
// ===================
Route::middleware('auth')->group(function () {

    // ===================
    // PERFIL
    // ===================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // ===================
    // DASHBOARD (Gestión de Proyectos)
    // ===================
    Route::get('/dashboard', [GpDashboardController::class, 'index'])->name('dashboard');

    // ===================
    // MÓDULOS (desde pestaña Roles)
    // ===================
    Route::middleware('can:roles.crear')->post('/modulos', [ModuleController::class, 'store'])->name('modules.store');
});

// ===================
// RUTA BACKUP SI FALLA
// ===================
Route::fallback(function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});
