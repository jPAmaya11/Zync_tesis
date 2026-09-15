<?php

namespace Modules\GestionProyectos\Tests\Feature;

use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Tests\GestionProyectosTestCase;

/**
 * Invariantes de acceso por espacio (punto crítico 1 — anti-IDOR).
 *
 * Casi toda ruta web del módulo se protege solo con `can:gestion-proyectos.ver` y recibe
 * un {key}/{projectKey} ADIVINABLE (S0001, S0001-0001…). La protección real vive dentro
 * del controller y debe delegar en GpSpaceMember. Estos tests fijan esa frontera: un
 * usuario con .ver pero SIN membresía en el espacio no puede leer ni escribir nada suyo.
 */
class GestionProyectosScopeTest extends GestionProyectosTestCase
{
    // ─── Chokepoint: GpSpaceMember ────────────────────────────────────────────

    public function test_membresia_suspendida_no_concede_acceso(): void
    {
        $espacio = $this->espacio();
        $user = $this->usuario();
        $this->miembro($user, $espacio, 'administrador', suspended: true);

        $this->assertNull(GpSpaceMember::roleInSpace($user->id, $espacio->key));
        $this->assertFalse(GpSpaceMember::canWrite($user->id, $espacio->key));
        $this->assertFalse(GpSpaceMember::canApprove($user->id, $espacio->key));
        $this->assertFalse(GpSpaceMember::canManage($user->id, $espacio->key));
        $this->assertFalse(GpSpaceMember::canSeeProject($user->id, $espacio->key));
    }

    public function test_visible_project_keys_excluye_suspendidos_e_incluye_owner(): void
    {
        $user = $this->usuario();
        $vigente = $this->espacio();
        $suspendido = $this->espacio();
        $propio = $this->espacio(['owner_id' => $user->id]);
        $ajeno = $this->espacio();

        $this->miembro($user, $vigente, 'lector');
        $this->miembro($user, $suspendido, 'administrador', suspended: true);

        $keys = GpSpaceMember::visibleProjectKeys($user);

        $this->assertContains($vigente->key, $keys);
        $this->assertContains($propio->key, $keys);
        $this->assertNotContains($suspendido->key, $keys);
        $this->assertNotContains($ajeno->key, $keys);
    }

    public function test_visible_project_keys_es_null_para_admin_global(): void
    {
        $this->assertNull(GpSpaceMember::visibleProjectKeys($this->admin()));
    }

    public function test_lector_ve_pero_no_escribe(): void
    {
        $espacio = $this->espacio();
        $lector = $this->usuario();
        $this->miembro($lector, $espacio, 'lector');

        $this->assertTrue(GpSpaceMember::canSeeProject($lector->id, $espacio->key));
        $this->assertFalse(GpSpaceMember::canWrite($lector->id, $espacio->key));
        $this->assertFalse(GpSpaceMember::canApprove($lector->id, $espacio->key));
    }

    // ─── Escritura sobre un espacio ajeno ─────────────────────────────────────

    public function test_no_miembro_no_puede_editar_actividad_ajena(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);
        $extrano = $this->usuario();

        $this->actingAs($extrano)
            ->patchJson("/gestion-proyectos/{$act->key}", ['summary' => 'Hackeado'])
            ->assertForbidden();

        $this->assertSame('Actividad de prueba', $act->fresh()->summary);
    }

    public function test_miembro_suspendido_no_puede_editar_actividad(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);
        $user = $this->usuario();
        $this->miembro($user, $espacio, 'administrador', suspended: true);

        $this->actingAs($user)
            ->patchJson("/gestion-proyectos/{$act->key}", ['summary' => 'Hackeado'])
            ->assertForbidden();

        $this->assertSame('Actividad de prueba', $act->fresh()->summary);
    }

    // ─── Lectura de un espacio ajeno (endpoints con {key} adivinable) ──────────

    public function test_no_miembro_no_lee_subactividades_ajenas(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);
        $this->subActividad($act);

        $this->actingAs($this->usuario())
            ->getJson("/gestion-proyectos/subactividades/{$act->key}")
            ->assertForbidden();
    }

    public function test_no_miembro_no_lee_subtareas_ajenas(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);
        $this->subTarea($act);

        $this->actingAs($this->usuario())
            ->getJson("/gestion-proyectos/subtareas/{$act->key}")
            ->assertForbidden();
    }

    public function test_no_miembro_no_lee_reprogramaciones_ajenas(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);

        $this->actingAs($this->usuario())
            ->getJson("/gestion-proyectos/{$act->key}/reprogramaciones")
            ->assertForbidden();
    }

    /** El historial de actividades contiene los comentarios y la EVIDENCIA de las transiciones. */
    public function test_no_miembro_no_lee_historial_de_actividad_ajena(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);
        $this->evidencia($act, 'Finalizado');

        $this->actingAs($this->usuario())
            ->getJson("/gestion-proyectos/activity/{$act->key}")
            ->assertForbidden();
    }

    public function test_no_miembro_no_lee_timeline_de_actividad_ajena(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);

        $this->actingAs($this->usuario())
            ->getJson("/gestion-proyectos/timeline/{$act->key}")
            ->assertForbidden();
    }

    public function test_no_miembro_no_lee_historico_de_titulos_ajeno(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);

        $this->actingAs($this->usuario())
            ->getJson("/gestion-proyectos/timeline/{$act->key}/titulos")
            ->assertForbidden();
    }

    public function test_no_miembro_no_lee_historial_de_tarea_ajena(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);
        $tarea = $this->subTarea($act);

        $this->actingAs($this->usuario())
            ->getJson("/gestion-proyectos/subtareas/item/{$tarea->id}/historial")
            ->assertForbidden();
    }

    // ─── El miembro legítimo sí pasa (la protección no rompe el caso normal) ───

    public function test_miembro_vigente_lee_su_espacio(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);
        $lector = $this->usuario();
        $this->miembro($lector, $espacio, 'lector');

        $this->actingAs($lector)
            ->getJson("/gestion-proyectos/activity/{$act->key}")
            ->assertOk();

        $this->actingAs($lector)
            ->getJson("/gestion-proyectos/subactividades/{$act->key}")
            ->assertOk();
    }

    public function test_admin_global_lee_cualquier_espacio(): void
    {
        $espacio = $this->espacio();
        $act = $this->actividad($espacio);

        $this->actingAs($this->admin())
            ->getJson("/gestion-proyectos/subactividades/{$act->key}")
            ->assertOk();
    }
}
