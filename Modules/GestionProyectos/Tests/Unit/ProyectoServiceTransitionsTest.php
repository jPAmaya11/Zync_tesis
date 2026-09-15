<?php

namespace Modules\GestionProyectos\Tests\Unit;

use Modules\GestionProyectos\Exceptions\GestionProyectosException;
use Modules\GestionProyectos\Services\ProyectoService;
use Modules\GestionProyectos\Tests\GestionProyectosTestCase;

/**
 * Invariantes del flujo de estados (ProyectoService::update).
 *
 * Fija los puntos críticos 2, 3, 4 y 7 de la documentación del módulo: quién mueve a un
 * estado crítico, qué evidencia lo habilita, la inmutabilidad de los terminales, el
 * roll-up al finalizar y la exclusividad equipo↔usuario. Son reglas de negocio puras:
 * se prueban contra el servicio, sin HTTP.
 */
class ProyectoServiceTransitionsTest extends GestionProyectosTestCase
{
    private ProyectoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProyectoService::class);
    }

    // ─── Punto crítico 2: estado crítico = rol aprobador + evidencia ──────────

    public function test_ejecutor_no_puede_mover_a_estado_critico(): void
    {
        $espacio = $this->espacio();
        $ejecutor = $this->usuario();
        $this->miembro($ejecutor, $espacio, 'ejecutor');
        $act = $this->actividad($espacio);
        $this->evidencia($act, 'Finalizado', $ejecutor);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('Aprobador');

        $this->service->update($act->key, ['status' => 'Finalizado'], $ejecutor);
    }

    public function test_aprobador_sin_evidencia_no_puede_finalizar(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $act = $this->actividad($espacio);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('evidencia');

        $this->service->update($act->key, ['status' => 'Finalizado'], $aprobador);
    }

    /**
     * El corazón del punto crítico 2: la evidencia debe ser de ESA transición. Una
     * evidencia vieja (de una reprogramación previa) no habilita un Finalizado nuevo.
     */
    public function test_evidencia_de_otra_transicion_no_habilita_finalizar(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $act = $this->actividad($espacio);
        $this->evidencia($act, 'Reprogramado', $aprobador);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('evidencia');

        $this->service->update($act->key, ['status' => 'Finalizado'], $aprobador);
    }

    public function test_aprobador_con_evidencia_de_la_transicion_finaliza(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $act = $this->actividad($espacio);
        $this->evidencia($act, 'Finalizado', $aprobador);

        $actualizada = $this->service->update($act->key, ['status' => 'Finalizado'], $aprobador);

        $this->assertSame('Finalizado', $actualizada->status);
    }

    public function test_reprogramar_exige_la_nueva_fecha(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $act = $this->actividad($espacio);
        $this->evidencia($act, 'Reprogramado', $aprobador);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('nueva fecha de vencimiento');

        $this->service->update($act->key, ['status' => 'Reprogramado'], $aprobador);
    }

    // ─── Punto crítico 5: el Aprobador solo cambia el estado ──────────────────

    public function test_aprobador_no_puede_editar_otros_campos(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $act = $this->actividad($espacio);
        $this->evidencia($act, 'Finalizado', $aprobador);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('solo puede cambiar el estado');

        $this->service->update($act->key, [
            'status'  => 'Finalizado',
            'summary' => 'Titulo cambiado por el aprobador',
        ], $aprobador);
    }

    // ─── Punto crítico 3: terminales inmutables ───────────────────────────────

    public function test_actividad_finalizada_no_cambia_de_estado(): void
    {
        $espacio = $this->espacio();
        $admin = $this->admin();
        $act = $this->actividad($espacio, ['status' => 'Finalizado']);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('terminal');

        $this->service->update($act->key, ['status' => 'En Curso'], $admin);
    }

    public function test_actividad_finalizada_no_admite_edicion_de_campos(): void
    {
        $espacio = $this->espacio();
        $admin = $this->admin();
        $act = $this->actividad($espacio, ['status' => 'Finalizado']);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('no puede ser modificada');

        $this->service->update($act->key, ['summary' => 'Otro titulo'], $admin);
    }

    /** Única excepción a la inmutabilidad: corregir "Producción" con el switch del espacio ON. */
    public function test_produccion_editable_permite_corregir_solo_fecha_aprobacion(): void
    {
        $espacio = $this->espacio(['produccion_editable_finalizado' => true]);
        $admin = $this->admin();
        $act = $this->actividad($espacio, ['status' => 'Finalizado']);

        $actualizada = $this->service->update(
            $act->key,
            ['fecha_aprobacion' => '2026-01-15'],
            $admin
        );

        $this->assertSame('2026-01-15', $actualizada->fecha_aprobacion->toDateString());
    }

    public function test_produccion_editable_no_abre_la_puerta_a_otros_campos(): void
    {
        $espacio = $this->espacio(['produccion_editable_finalizado' => true]);
        $admin = $this->admin();
        $act = $this->actividad($espacio, ['status' => 'Finalizado']);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('no puede ser modificada');

        $this->service->update($act->key, ['summary' => 'Otro titulo'], $admin);
    }

    /** La excepción es solo para Finalizado: una Cancelada sigue siendo inmutable. */
    public function test_produccion_editable_no_aplica_a_cancelada(): void
    {
        $espacio = $this->espacio(['produccion_editable_finalizado' => true]);
        $admin = $this->admin();
        $act = $this->actividad($espacio, ['status' => 'Cancelado']);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('no puede ser modificada');

        $this->service->update($act->key, ['fecha_aprobacion' => '2026-01-15'], $admin);
    }

    // ─── Punto crítico 4: roll-up al finalizar ────────────────────────────────

    public function test_no_se_puede_finalizar_con_subactividades_activas(): void
    {
        $espacio = $this->espacio();
        $admin = $this->admin();
        $act = $this->actividad($espacio);
        $this->subActividad($act, ['status' => 'En Curso']);
        $this->evidencia($act, 'Finalizado', $admin);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('sin completar');

        $this->service->update($act->key, ['status' => 'Finalizado'], $admin);
    }

    public function test_no_se_puede_finalizar_con_tareas_activas(): void
    {
        $espacio = $this->espacio();
        $admin = $this->admin();
        $act = $this->actividad($espacio);
        $this->subTarea($act, ['status' => 'Pendiente']);
        $this->evidencia($act, 'Finalizado', $admin);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('sin completar');

        $this->service->update($act->key, ['status' => 'Finalizado'], $admin);
    }

    /** Los hijos en estado terminal ya están cerrados: no bloquean el cierre del padre. */
    public function test_hijos_terminales_no_bloquean_finalizar(): void
    {
        $espacio = $this->espacio();
        $admin = $this->admin();
        $act = $this->actividad($espacio);
        $this->subActividad($act, ['status' => 'Finalizado']);
        $this->subTarea($act, ['status' => 'Cancelado']);
        $this->evidencia($act, 'Finalizado', $admin);

        $actualizada = $this->service->update($act->key, ['status' => 'Finalizado'], $admin);

        $this->assertSame('Finalizado', $actualizada->status);
    }

    // ─── Catálogo de estados: el MCP/API no puede inventar estados ────────────

    public function test_estado_fuera_del_catalogo_es_rechazado(): void
    {
        $espacio = $this->espacio();
        $admin = $this->admin();
        $act = $this->actividad($espacio);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('Estado inválido');

        $this->service->update($act->key, ['status' => 'revision'], $admin);
    }

    // ─── Punto crítico 7: exclusividad equipo ↔ usuario ───────────────────────

    public function test_asignar_usuario_limpia_el_equipo(): void
    {
        $espacio = $this->espacio();
        $admin = $this->admin();
        $equipo = \Modules\GestionProyectos\Models\GpTeam::create([
            'name'      => 'Equipo A',
            'is_active' => true,
        ]);
        $act = $this->actividad($espacio, ['team_id' => $equipo->id]);
        $otro = $this->usuario();

        $actualizada = $this->service->update($act->key, ['assignee_id' => $otro->id], $admin);

        $this->assertSame($otro->id, $actualizada->assignee_id);
        $this->assertNull($actualizada->team_id);
    }

    public function test_no_se_puede_asignar_un_equipo_inactivo(): void
    {
        $espacio = $this->espacio();
        $admin = $this->admin();
        $inactivo = \Modules\GestionProyectos\Models\GpTeam::create([
            'name'      => 'Equipo inactivo',
            'is_active' => false,
        ]);
        $act = $this->actividad($espacio);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('equipo inactivo');

        $this->service->update($act->key, ['team_id' => $inactivo->id], $admin);
    }

    // ─── Punto crítico 3, excepción 2: la categoría sí se edita en terminales ──

    /**
     * `categoria` es metadato de CLASIFICACIÓN: no altera qué se hizo, quién lo aprobó
     * ni cuándo. Si los terminales la bloquearan, el campo nacería inservible para todo
     * el histórico —que en producción es la mayoría y está Finalizado— y no se podría
     * reportar por categoría. Por eso es editable aun estando cerrada.
     */
    public function test_la_categoria_se_puede_editar_en_una_actividad_finalizada(): void
    {
        $espacio = $this->espacio();
        $act     = $this->actividad($espacio, ['status' => 'Finalizado']);

        $this->service->update($act->key, ['categoria' => 'Infraestructura'], $this->admin());

        $this->assertSame('Infraestructura', $act->fresh()->categoria);
    }

    /** También en Cancelado: el criterio es el mismo para cualquier terminal. */
    public function test_la_categoria_se_puede_editar_en_una_actividad_cancelada(): void
    {
        $espacio = $this->espacio();
        $act     = $this->actividad($espacio, ['status' => 'Cancelado']);

        $this->service->update($act->key, ['categoria' => 'Soporte'], $this->admin());

        $this->assertSame('Soporte', $act->fresh()->categoria);
    }

    /** Y en subactividades cerradas, que son filas de gp_proyectos como las demás. */
    public function test_la_categoria_se_puede_editar_en_una_subactividad_finalizada(): void
    {
        $espacio = $this->espacio();
        $padre   = $this->actividad($espacio);
        $sub     = $this->subActividad($padre, ['status' => 'Finalizado']);

        $this->service->update($sub->key, ['categoria' => 'Mejora'], $this->admin());

        $this->assertSame('Mejora', $sub->fresh()->categoria);
    }

    /**
     * La excepción es SOLO para la categoría: abrir la puerta a un campo no puede
     * abrirla para el resto. Este test es el que impide que la excepción se ensanche.
     */
    public function test_la_excepcion_de_categoria_no_abre_la_puerta_a_otros_campos(): void
    {
        $espacio = $this->espacio();
        $act     = $this->actividad($espacio, ['status' => 'Finalizado']);

        $this->expectException(GestionProyectosException::class);
        $this->expectExceptionMessage('terminal');

        $this->service->update(
            $act->key,
            ['categoria' => 'Infraestructura', 'summary' => 'Titulo cambiado'],
            $this->admin(),
        );
    }
}
