<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionProyectos\Http\Controllers\Api\ScrumApiController;
use Modules\GestionProyectos\Http\Middleware\AuthGpApiToken;

/*
|--------------------------------------------------------------------------
| GestionProyectos — API REST (token Bearer)
|--------------------------------------------------------------------------
| Autenticada con AuthGpApiToken: el cliente envía Authorization: Bearer <token>.
| El usuario genera su token desde la web (Operaciones → Generar Token).
| Las autorizaciones por espacio/rol se aplican igual que en la web (mismas policies).
*/

Route::middleware(['api', AuthGpApiToken::class])
    ->prefix('api/gestion-proyectos')
    ->name('gp-api.')
    ->group(function () {
        // Verificación de token (whoami).
        Route::get('/me', [ScrumApiController::class, 'me'])->name('me');

        // Espacios visibles para el token (para obtener el `key` que usa /issues).
        Route::get('/spaces', [ScrumApiController::class, 'spaces'])->name('spaces.index');

        // CRUD de issues (modelo SCRUM). Reusa ProyectoService + policies por espacio.
        Route::get('/issues',          [ScrumApiController::class, 'index'])->name('issues.index');
        Route::post('/issues',         [ScrumApiController::class, 'store'])->name('issues.store');
        Route::get('/issues/{key}',    [ScrumApiController::class, 'show'])->name('issues.show');
        Route::patch('/issues/{key}',  [ScrumApiController::class, 'update'])->name('issues.update');
        Route::delete('/issues/{key}', [ScrumApiController::class, 'destroy'])->name('issues.destroy');
    });
