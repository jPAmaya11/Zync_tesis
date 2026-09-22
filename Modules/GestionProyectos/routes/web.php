<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionProyectos\Http\Controllers\GestionProyectosController;
use Modules\GestionProyectos\Http\Controllers\GpTeamController;
use Modules\GestionProyectos\Http\Controllers\GpSpaceMemberController;
use Modules\GestionProyectos\Http\Controllers\MailProvidersController;
use Modules\GestionProyectos\Http\Controllers\ApiTokenController;
use Modules\GestionProyectos\Http\Controllers\ChatIAController;

/*
|--------------------------------------------------------------------------
| GestionProyectos Module Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])
    ->prefix('gestion-proyectos')
    ->name('gestion-proyectos.')
    ->group(function () {

        // ── Asistente conversacional con IA (Gemini) — Cap. 3 de la tesis ────────
        // Disponible para CUALQUIER usuario autenticado, sin gate de permiso de
        // módulo: es una herramienta de apoyo personal, no una operación sobre
        // el espacio/proyecto.
        Route::get('/chat-ia', [ChatIAController::class, 'index'])->name('chat-ia.index');
        Route::post('/chat-ia', [ChatIAController::class, 'store'])->name('chat-ia.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/', [GestionProyectosController::class, 'index'])
            ->name('index');

        // ── Sesiones MCP: cada usuario lista y desconecta LAS SUYAS (sesión web) ──
        // El gate es 'ver' porque son las sesiones propias, no una operación
        // privilegiada; el scoping por user_id lo aplica el controller.
        // 'store' (generación manual de token) conserva su ruta pero el propio
        // controller la reserva a administradores: es la vía de las integraciones
        // servidor-a-servidor, que no pueden hacer un flujo OAuth interactivo.
        Route::middleware('can:gestion-proyectos.ver')->group(function () {
            Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
            Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
            Route::delete('/api-tokens', [ApiTokenController::class, 'destroyAll'])->name('api-tokens.destroy-all');
            Route::delete('/api-tokens/{id}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
        });

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/', [GestionProyectosController::class, 'store'])
            ->name('store');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/users', [GestionProyectosController::class, 'users'])
            ->name('users');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/assignable-users', [GestionProyectosController::class, 'assignableUsers'])
            ->name('assignable-users');

        // Panel "Mis pendientes": todo lo asignado al usuario autenticado (cross-espacio).
        Route::middleware('can:gestion-proyectos.ver')
            ->get('/mis-pendientes', [GestionProyectosController::class, 'misPendientes'])
            ->name('mis-pendientes');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/member-candidates', [GestionProyectosController::class, 'memberCandidates'])
            ->name('member-candidates');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/issue-types', [GestionProyectosController::class, 'issueTypes'])
            ->name('issue-types');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/priorities', [GestionProyectosController::class, 'priorities'])
            ->name('priorities');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/statuses', [GestionProyectosController::class, 'statuses'])
            ->name('statuses');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/labels', [GestionProyectosController::class, 'labels'])
            ->name('labels');

        Route::middleware('can:gestion-proyectos.ver')
            ->patch('/{key}', [GestionProyectosController::class, 'update'])
            ->name('update');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/{key}/reprogramar', [GestionProyectosController::class, 'reprogramar'])
            ->name('reprogramar');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/{key}/reprogramaciones', [GestionProyectosController::class, 'getReprogramaciones'])
            ->name('reprogramaciones.index');

        // Analítica/KPIs del espacio (Plazos + Estados) — gate manage en el controller.
        Route::middleware('can:gestion-proyectos.ver')
            ->get('/espacios/{projectKey}/analytics', [GestionProyectosController::class, 'analytics'])
            ->name('analytics');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/bulk/update', [GestionProyectosController::class, 'bulkUpdate'])
            ->name('bulk.update');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/bulk/delete', [GestionProyectosController::class, 'bulkDelete'])
            ->name('bulk.delete');

        // Gestión de proyectos (espacios)
        Route::middleware('can:gestion-proyectos.admin')
            ->post('/projects', [GestionProyectosController::class, 'storeProject'])
            ->name('projects.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->patch('/projects/{projectKey}', [GestionProyectosController::class, 'updateProject'])
            ->name('projects.update');

        Route::middleware('can:gestion-proyectos.ver')
            ->delete('/projects/{projectKey}', [GestionProyectosController::class, 'destroyProject'])
            ->name('projects.destroy');

        // ── Campos personalizados por proyecto ──────────────────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/projects/{projectKey}/custom-fields', [GestionProyectosController::class, 'getCustomFields'])
            ->name('custom-fields.index');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/projects/{projectKey}/custom-fields', [GestionProyectosController::class, 'storeCustomField'])
            ->name('custom-fields.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->put('/custom-fields/{field}', [GestionProyectosController::class, 'updateCustomField'])
            ->name('custom-fields.update');

        Route::middleware('can:gestion-proyectos.ver')
            ->delete('/custom-fields/{field}', [GestionProyectosController::class, 'destroyCustomField'])
            ->name('custom-fields.destroy');

        // ── Preferencias de columnas por usuario ────────────────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/column-preferences/{projectKey}', [GestionProyectosController::class, 'getColumnPreferences'])
            ->name('column-preferences.show');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/column-preferences/{projectKey}', [GestionProyectosController::class, 'saveColumnPreferences'])
            ->name('column-preferences.save');

        // ── Columnas del catálogo del sistema ───────────────────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/catalog-columns', [GestionProyectosController::class, 'getCatalogColumns'])
            ->name('catalog-columns');

        // ── Categorías de espacio ───────────────────────────────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/categories', [GestionProyectosController::class, 'indexCategories'])
            ->name('categories.index');

        Route::middleware('can:gestion-proyectos.admin')
            ->post('/categories', [GestionProyectosController::class, 'storeCategory'])
            ->name('categories.store');

        Route::middleware('can:gestion-proyectos.admin')
            ->put('/categories/{id}', [GestionProyectosController::class, 'updateCategory'])
            ->name('categories.update');

        Route::middleware('can:gestion-proyectos.admin')
            ->delete('/categories/{id}', [GestionProyectosController::class, 'destroyCategory'])
            ->name('categories.destroy');

        // ── Etiquetas por espacio (CRUD propio de cada espacio) ──────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/projects/{projectKey}/labels', [GestionProyectosController::class, 'getProjectLabels'])
            ->name('projects.labels.index');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/projects/{projectKey}/labels', [GestionProyectosController::class, 'storeLabel'])
            ->name('projects.labels.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/projects/{projectKey}/labels/inherit', [GestionProyectosController::class, 'inheritLabels'])
            ->name('projects.labels.inherit');

        Route::middleware('can:gestion-proyectos.ver')
            ->put('/labels/{id}', [GestionProyectosController::class, 'updateLabel'])
            ->name('labels.update');

        Route::middleware('can:gestion-proyectos.ver')
            ->delete('/labels/{id}', [GestionProyectosController::class, 'destroyLabel'])
            ->name('labels.destroy');
            // ── Historial de Actividades ─────────────────────────────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/activity/{key}', [GestionProyectosController::class, 'getActivityHistory'])
            ->name('activity.index');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/activity/{key}', [GestionProyectosController::class, 'storeActivityHistory'])
            ->name('activity.store');
        Route::middleware('can:gestion-proyectos.ver')
            ->get('/activity/attachment/{id}/{index?}', [GestionProyectosController::class, 'downloadActivityAttachment'])
            ->name('activity.download');
        // ── Timeline unificado (audit + comentarios) ─────────────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/timeline/{key}', [GestionProyectosController::class, 'getTimeline'])
            ->name('timeline.index');

        // Histórico de TÍTULOS (solo cambios de summary), paginado 10 en 10.
        Route::middleware('can:gestion-proyectos.ver')
            ->get('/timeline/{key}/titulos', [GestionProyectosController::class, 'getTitleHistory'])
            ->name('timeline.titulos');

        // ── Sub Tareas — Blueprint §2.5 ──────────────────────────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/subtareas/{key}', [GestionProyectosController::class, 'indexSubTareas'])
            ->name('subtareas.index');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/subtareas/{key}', [GestionProyectosController::class, 'storeSubTarea'])
            ->name('subtareas.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->patch('/subtareas/item/{id}', [GestionProyectosController::class, 'updateSubTarea'])
            ->name('subtareas.update');

        Route::middleware('can:gestion-proyectos.ver')
            ->delete('/subtareas/item/{id}', [GestionProyectosController::class, 'destroySubTarea'])
            ->name('subtareas.destroy');

        // ── Historial / evidencia propio de cada tarea ──────────────────────
        Route::middleware('can:gestion-proyectos.ver')
            ->get('/subtareas/item/{id}/historial', [GestionProyectosController::class, 'indexSubTareaHistorial'])
            ->name('subtareas.historial.index');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/subtareas/item/{id}/historial', [GestionProyectosController::class, 'storeSubTareaHistorial'])
            ->name('subtareas.historial.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/subtareas/historial/attachment/{id}/{index?}', [GestionProyectosController::class, 'downloadSubTareaAttachment'])
            ->name('subtareas.historial.download');

        // ── Sub Actividades (Proyecto anidado, mismos campos que una Actividad) ─
        Route::middleware('can:gestion-proyectos.ver')
            ->get('/subactividades/{key}', [GestionProyectosController::class, 'indexSubActividades'])
            ->name('subactividades.index');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/subactividades/{key}', [GestionProyectosController::class, 'storeSubActividad'])
            ->name('subactividades.store');

        // ── Equipos por espacio (§2.2 columna Equipo) ───────────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/teams-all', [GpTeamController::class, 'globalIndex'])
            ->name('teams.all');

        Route::middleware('can:gestion-proyectos.admin')
            ->post('/teams-global', [GpTeamController::class, 'globalStore'])
            ->name('teams.global.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/projects/{projectKey}/teams/sync', [GpTeamController::class, 'syncWithProject'])
            ->name('projects.teams.sync');

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/teams/{projectKey}', [GpTeamController::class, 'index'])
            ->name('teams.index');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/teams/{projectKey}', [GpTeamController::class, 'store'])
            ->name('teams.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->patch('/teams/item/{id}', [GpTeamController::class, 'update'])
            ->name('teams.update');

        Route::middleware('can:gestion-proyectos.ver')
            ->patch('/teams/item/{id}/active', [GpTeamController::class, 'toggleActive'])
            ->name('teams.toggle-active');

        Route::middleware('can:gestion-proyectos.ver')
            ->delete('/teams/item/{id}', [GpTeamController::class, 'destroy'])
            ->name('teams.destroy');

        // ── Miembros de espacio (§2.1 modelo de permisos) ───────────────────

        Route::middleware('can:gestion-proyectos.ver')
            ->get('/spaces/{projectKey}/members', [GpSpaceMemberController::class, 'index'])
            ->name('spaces.members.index');

        Route::middleware('can:gestion-proyectos.ver')
            ->post('/spaces/{projectKey}/members', [GpSpaceMemberController::class, 'store'])
            ->name('spaces.members.store');

        Route::middleware('can:gestion-proyectos.ver')
            ->patch('/spaces/member/{id}', [GpSpaceMemberController::class, 'update'])
            ->name('spaces.members.update');

        Route::middleware('can:gestion-proyectos.ver')
            ->delete('/spaces/member/{id}', [GpSpaceMemberController::class, 'destroy'])
            ->name('spaces.members.destroy');

        // ── Panel de Mail Providers (admin) ─────────────────────────────────

        Route::middleware('can:gestion-proyectos.admin')
            ->get('/mail/providers', [MailProvidersController::class, 'index'])
            ->name('mail.providers.index');

        Route::middleware('can:gestion-proyectos.admin')
            ->patch('/mail/providers/{provider}', [MailProvidersController::class, 'update'])
            ->name('mail.providers.update');

        Route::middleware('can:gestion-proyectos.admin')
            ->post('/mail/providers/{provider}/reset-quota', [MailProvidersController::class, 'resetQuota'])
            ->name('mail.providers.reset-quota');

        Route::middleware('can:gestion-proyectos.admin')
            ->post('/mail/providers/{provider}/reset-monthly-quota', [MailProvidersController::class, 'resetMonthlyQuota'])
            ->name('mail.providers.reset-monthly-quota');

        Route::middleware('can:gestion-proyectos.admin')
            ->post('/mail/providers/{provider}/sync-usage', [MailProvidersController::class, 'syncUsage'])
            ->name('mail.providers.sync-usage');

});
