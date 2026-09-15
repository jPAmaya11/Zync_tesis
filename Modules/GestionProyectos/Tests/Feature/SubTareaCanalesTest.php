<?php

namespace Modules\GestionProyectos\Tests\Feature;

use Modules\GestionProyectos\Mcp\Tools\ActualizarTareaTool;
use Modules\GestionProyectos\Models\GpSubTareaHistorial;
use Modules\GestionProyectos\Tests\GestionProyectosTestCase;

/**
 * Reglas de las TAREAS (GpSubTarea) y consistencia entre canales (punto crítico 5).
 *
 * La regla de finalización vive en el hook GpSubTarea::updating y se declara "única
 * fuente de verdad que cubre web y MCP": finalizar = canApprove + evidencia, y
 * Finalizado es terminal. Lo que NO es único es el GATE DE ENTRADA de cada canal:
 *   - web  updateSubTarea      → authorize('crear')             = canWrite
 *   - MCP  ActualizarTareaTool → can('registrarHistorial')      = canWrite ∪ canApprove
 * Estos tests fijan ambas cosas: la regla compartida y la divergencia de los gates.
 */
class SubTareaCanalesTest extends GestionProyectosTestCase
{
    private function conEvidencia(int $subTareaId): void
    {
        GpSubTareaHistorial::create([
            'sub_tarea_id' => $subTareaId,
            'user_id'      => null,
            'comment'      => 'Evidencia de cierre',
        ]);
    }

    private function actualizarViaMcp(array $args): mixed
    {
        return app(ActualizarTareaTool::class)->handle($args);
    }

    // ─── Regla compartida: el hook del modelo (web y MCP) ─────────────────────

    public function test_ejecutor_no_puede_finalizar_una_tarea(): void
    {
        $espacio = $this->espacio();
        $ejecutor = $this->usuario();
        $this->miembro($ejecutor, $espacio, 'ejecutor');
        $tarea = $this->subTarea($this->actividad($espacio));
        $this->conEvidencia($tarea->id);

        $this->actingAs($ejecutor)
            ->patchJson("/gestion-proyectos/subtareas/item/{$tarea->id}", ['status' => 'Finalizado'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Solo el Aprobador del espacio puede finalizar tareas.');

        $this->assertSame('Pendiente', $tarea->fresh()->status);
    }

    public function test_finalizar_sin_evidencia_es_rechazado(): void
    {
        $espacio = $this->espacio();
        $impl = $this->usuario();
        $this->miembro($impl, $espacio, 'implementador');
        $tarea = $this->subTarea($this->actividad($espacio));

        $this->actingAs($impl)
            ->patchJson("/gestion-proyectos/subtareas/item/{$tarea->id}", ['status' => 'Finalizado'])
            ->assertStatus(422)
            ->assertJsonPath('requires', 'sub_tarea_history');

        $this->assertSame('Pendiente', $tarea->fresh()->status);
    }

    public function test_implementador_con_evidencia_finaliza_por_web(): void
    {
        $espacio = $this->espacio();
        $impl = $this->usuario();
        $this->miembro($impl, $espacio, 'implementador');
        $tarea = $this->subTarea($this->actividad($espacio));
        $this->conEvidencia($tarea->id);

        $this->actingAs($impl)
            ->patchJson("/gestion-proyectos/subtareas/item/{$tarea->id}", ['status' => 'Finalizado'])
            ->assertOk();

        $this->assertSame('Finalizado', $tarea->fresh()->status);
    }

    public function test_tarea_finalizada_es_terminal(): void
    {
        $espacio = $this->espacio();
        $impl = $this->usuario();
        $this->miembro($impl, $espacio, 'implementador');
        $tarea = $this->subTarea($this->actividad($espacio), ['status' => 'Finalizado']);

        $this->actingAs($impl)
            ->patchJson("/gestion-proyectos/subtareas/item/{$tarea->id}", ['summary' => 'Otro titulo'])
            ->assertStatus(422);

        $this->assertSame('Tarea de prueba', $tarea->fresh()->summary);
    }

    // ─── El rol 'aprobador' se comporta igual por web y por MCP ───────────────

    /**
     * El hook del modelo nombra al Aprobador como el rol que finaliza tareas, así que el
     * gate web es registrarHistorial (canWrite ∪ canApprove) y no 'crear' (canWrite): con
     * 'crear', el 'aprobador' —que no está en WRITE_ROLES— quedaba fuera por web y solo
     * podía finalizar por MCP.
     */
    public function test_aprobador_finaliza_tarea_por_web(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $tarea = $this->subTarea($this->actividad($espacio));
        $this->conEvidencia($tarea->id);

        $this->actingAs($aprobador)
            ->patchJson("/gestion-proyectos/subtareas/item/{$tarea->id}", ['status' => 'Finalizado'])
            ->assertOk();

        $this->assertSame('Finalizado', $tarea->fresh()->status);
    }

    public function test_aprobador_finaliza_la_misma_tarea_por_mcp(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $tarea = $this->subTarea($this->actividad($espacio));
        $this->conEvidencia($tarea->id);

        $this->actingAs($aprobador);
        $this->actualizarViaMcp(['id' => $tarea->id, 'status' => 'Finalizado']);

        $this->assertSame('Finalizado', $tarea->fresh()->status);
    }

    /** El aprobador mueve el estado, pero no edita los datos: por web. */
    public function test_aprobador_no_puede_editar_datos_de_la_tarea_por_web(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $tarea = $this->subTarea($this->actividad($espacio));

        $this->actingAs($aprobador)
            ->patchJson("/gestion-proyectos/subtareas/item/{$tarea->id}", ['summary' => 'Titulo del aprobador'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'El Aprobador solo puede cambiar el estado de la tarea.');

        $this->assertSame('Tarea de prueba', $tarea->fresh()->summary);
    }

    /**
     * …y lo mismo por MCP: AVISA en vez de descartar en silencio. Antes el tool filtraba
     * los campos no permitidos y devolvía éxito, así que el llamador creía que había
     * cambiado el summary. Ahora la tarea no cambia y el error lo dice.
     */
    public function test_mcp_avisa_al_aprobador_en_vez_de_descartar_en_silencio(): void
    {
        $espacio = $this->espacio();
        $aprobador = $this->usuario();
        $this->miembro($aprobador, $espacio, 'aprobador');
        $tarea = $this->subTarea($this->actividad($espacio));
        $this->conEvidencia($tarea->id);

        $this->actingAs($aprobador);
        $this->actualizarViaMcp([
            'id'      => $tarea->id,
            'status'  => 'Finalizado',
            'summary' => 'Titulo cambiado por el aprobador',
        ]);

        $fresca = $tarea->fresh();
        $this->assertSame('Tarea de prueba', $fresca->summary);
        $this->assertSame('Pendiente', $fresca->status);
    }

    /** El escritor sí edita datos: la restricción es solo para el aprobador puro. */
    public function test_ejecutor_si_puede_editar_datos_de_la_tarea(): void
    {
        $espacio = $this->espacio();
        $ejecutor = $this->usuario();
        $this->miembro($ejecutor, $espacio, 'ejecutor');
        $tarea = $this->subTarea($this->actividad($espacio));

        $this->actingAs($ejecutor)
            ->patchJson("/gestion-proyectos/subtareas/item/{$tarea->id}", ['summary' => 'Titulo del ejecutor'])
            ->assertOk();

        $this->assertSame('Titulo del ejecutor', $tarea->fresh()->summary);
    }
}
