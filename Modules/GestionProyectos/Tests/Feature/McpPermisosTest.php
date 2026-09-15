<?php

namespace Modules\GestionProyectos\Tests\Feature;

use Laravel\Mcp\Server\Tools\ToolResult;
use Modules\GestionProyectos\Mcp\Tools\ActualizarTareasMasivoTool;
use Modules\GestionProyectos\Mcp\Tools\BorrarActividadTool;
use Modules\GestionProyectos\Mcp\Tools\BorrarEspacioTool;
use Modules\GestionProyectos\Mcp\Tools\BorrarTareasMasivoTool;
use Modules\GestionProyectos\Mcp\Tools\ListarActividadesTool;
use Modules\GestionProyectos\Mcp\Tools\ListarCategoriasTool;
use Modules\GestionProyectos\Mcp\Tools\ListarEspaciosTool;
use Modules\GestionProyectos\Mcp\Tools\QuitarMiembroTool;
use Modules\GestionProyectos\Mcp\Tools\VerActividadTool;
use Modules\GestionProyectos\Models\GpProject;
use Modules\GestionProyectos\Models\GpSpaceCategory;
use Modules\GestionProyectos\Models\GpSpaceMember;
use Modules\GestionProyectos\Models\Proyecto;
use Modules\GestionProyectos\Tests\GestionProyectosTestCase;

/**
 * Superficie de PERMISOS del canal MCP (punto crítico 1: anti-IDOR).
 *
 * El permiso global 'gestion-proyectos.ver' solo abre la puerta del módulo; la
 * autorización real es POR ESPACIO y vive en GpSpaceMember. Estos tests fijan esa
 * frontera para las tools de MAYOR daño —las destructivas y las masivas—, que hasta
 * ahora no tenían ninguna cobertura.
 *
 * Importan especialmente al abrir OAuth: hoy conectarse al MCP exige configurar cada
 * PC a mano, así que en la práctica lo usan poquísimas personas. Con autoservicio, todo
 * usuario con '.ver' puede conectarse el mismo día, y cualquier hueco de permisos pasa
 * de inalcanzable a alcanzable por ~100 personas. Ver docs/PLAN-OAUTH-MCP.md (Fase 0).
 */
class McpPermisosTest extends GestionProyectosTestCase
{
    /** Ejecuta una tool MCP como el usuario actualmente autenticado. */
    private function mcp(string $tool, array $args = []): ToolResult
    {
        return app($tool)->handle($args);
    }

    /** Texto plano devuelto por la tool (mensaje de error o JSON serializado). */
    private function texto(ToolResult $r): string
    {
        return collect($r->toArray()['content'])->pluck('text')->implode("\n");
    }

    /** Payload JSON de una tool que respondió con éxito. */
    private function payload(ToolResult $r): array
    {
        return json_decode($this->texto($r), true) ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. El permiso global no concede acceso a ningún espacio
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Invariante base de todo el módulo: '.ver' abre la puerta, no da contenido.
     * Un usuario sin membresías se conecta al MCP y no ve absolutamente nada.
     */
    public function test_usuario_con_ver_y_sin_membresias_no_ve_ningun_espacio(): void
    {
        $this->espacio();
        $this->espacio();

        $this->actingAs($this->usuario());

        $espacios = $this->payload($this->mcp(ListarEspaciosTool::class));

        $this->assertSame([], $espacios['espacios'] ?? $espacios['data'] ?? $espacios);
    }

    public function test_usuario_sin_membresias_no_puede_listar_actividades_ajenas(): void
    {
        $ajeno = $this->espacio();
        $this->actividad($ajeno);

        $this->actingAs($this->usuario());

        $r = $this->mcp(ListarActividadesTool::class, ['project' => $ajeno->key]);

        $this->assertTrue($r->isError);
        $this->assertStringContainsString('acceso', $this->texto($r));
    }

    /**
     * La key es adivinable (S0001-0001), así que un endpoint de LECTURA necesita el
     * gate tanto como uno de escritura. Esto ya falló una vez en la web.
     */
    public function test_usuario_sin_membresias_no_puede_ver_actividad_ajena(): void
    {
        $actividad = $this->actividad($this->espacio(), ['summary' => 'Secreto de otro equipo']);

        $this->actingAs($this->usuario());

        $r = $this->mcp(VerActividadTool::class, ['key' => $actividad->key]);

        $this->assertTrue($r->isError);
        $this->assertStringNotContainsString('Secreto de otro equipo', $this->texto($r));
    }

    /**
     * roleInSpace() excluye las membresías suspendidas: el rol se conserva pero no
     * concede acceso. Es la puerta cerrada reversible del equipo inactivo.
     */
    public function test_membresia_suspendida_no_concede_acceso(): void
    {
        $espacio = $this->espacio();
        $user = $this->usuario();
        $this->miembro($user, $espacio, 'administrador', suspended: true);
        $this->actividad($espacio);

        $this->actingAs($user);

        $r = $this->mcp(ListarActividadesTool::class, ['project' => $espacio->key]);

        $this->assertTrue($r->isError);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Tools destructivas
    // ─────────────────────────────────────────────────────────────────────────

    public function test_no_se_puede_borrar_actividad_de_un_espacio_ajeno(): void
    {
        $actividad = $this->actividad($this->espacio());

        $this->actingAs($this->usuario());

        $r = $this->mcp(BorrarActividadTool::class, ['key' => $actividad->key]);

        $this->assertTrue($r->isError);
        $this->assertNotNull(Proyecto::where('key', $actividad->key)->first());
    }

    /**
     * Borrar exige canManage (propietario/administrador). Un ejecutor escribe, pero
     * no borra: la jerarquía no se aplana por venir del MCP.
     */
    public function test_ejecutor_no_puede_borrar_una_actividad(): void
    {
        $espacio = $this->espacio();
        $ejecutor = $this->usuario();
        $this->miembro($ejecutor, $espacio, 'ejecutor');
        $actividad = $this->actividad($espacio);

        $this->actingAs($ejecutor);

        $r = $this->mcp(BorrarActividadTool::class, ['key' => $actividad->key]);

        $this->assertTrue($r->isError);
        $this->assertNotNull(Proyecto::where('key', $actividad->key)->first());
    }

    public function test_no_se_puede_borrar_un_espacio_ajeno(): void
    {
        $espacio = $this->espacio();

        $this->actingAs($this->usuario());

        $r = $this->mcp(BorrarEspacioTool::class, ['project_key' => $espacio->key]);

        $this->assertTrue($r->isError);
        $this->assertNotNull(GpProject::where('key', $espacio->key)->first());
    }

    /** Un lector del espacio tampoco puede eliminarlo: ver no es gestionar. */
    public function test_lector_no_puede_borrar_su_propio_espacio(): void
    {
        $espacio = $this->espacio();
        $lector = $this->usuario();
        $this->miembro($lector, $espacio, 'lector');

        $this->actingAs($lector);

        $r = $this->mcp(BorrarEspacioTool::class, ['project_key' => $espacio->key]);

        $this->assertTrue($r->isError);
        $this->assertNotNull(GpProject::where('key', $espacio->key)->first());
    }

    /**
     * QuitarMiembroTool recibe un member_id numérico y adivinable: sin el gate,
     * cualquiera podría expulsar gente de espacios que ni conoce.
     */
    public function test_no_se_puede_quitar_un_miembro_de_un_espacio_ajeno(): void
    {
        $espacio = $this->espacio();
        $victima = $this->usuario();
        $membresia = $this->miembro($victima, $espacio, 'ejecutor');

        $this->actingAs($this->usuario());

        $r = $this->mcp(QuitarMiembroTool::class, ['member_id' => $membresia->id]);

        $this->assertTrue($r->isError);
        $this->assertNotNull(GpSpaceMember::find($membresia->id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Tools masivas — el mayor daño potencial del canal
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * bulkDelete() delega key por key en delete(), que autoriza cada una. Un lote
     * mezclado debe borrar SOLO lo propio y reportar el resto en 'failed'; nunca
     * puede colarse una actividad ajena aprovechando el lote.
     */
    public function test_borrado_masivo_no_alcanza_actividades_de_otro_espacio(): void
    {
        $propio = $this->espacio();
        $duenio = $this->usuario();
        $this->miembro($duenio, $propio, 'administrador');
        $mia = $this->actividad($propio);

        $ajena = $this->actividad($this->espacio());

        $this->actingAs($duenio);

        $r = $this->mcp(BorrarTareasMasivoTool::class, [
            'keys' => [$mia->key, $ajena->key],
        ]);

        $resultado = $this->payload($r);

        $this->assertSame([$mia->key], $resultado['succeeded']);
        $this->assertSame([$ajena->key], array_column($resultado['failed'], 'key'));

        $this->assertNull(Proyecto::where('key', $mia->key)->first());
        $this->assertNotNull(Proyecto::where('key', $ajena->key)->first());
    }

    public function test_actualizacion_masiva_no_alcanza_actividades_de_otro_espacio(): void
    {
        $propio = $this->espacio();
        $duenio = $this->usuario();
        $this->miembro($duenio, $propio, 'administrador');
        $mia = $this->actividad($propio, ['priority' => 'Media']);

        $ajena = $this->actividad($this->espacio(), ['priority' => 'Media']);

        $this->actingAs($duenio);

        $r = $this->mcp(ActualizarTareasMasivoTool::class, [
            'keys'     => [$mia->key, $ajena->key],
            'priority' => 'Alta',
        ]);

        $resultado = $this->payload($r);

        $this->assertSame([$mia->key], $resultado['succeeded']);
        $this->assertSame([$ajena->key], array_column($resultado['failed'], 'key'));

        $this->assertSame('Alta', $mia->fresh()->priority);
        $this->assertSame('Media', $ajena->fresh()->priority);
    }

    /** Un lote enteramente ajeno no borra nada y lo reporta entero como fallido. */
    public function test_borrado_masivo_de_lote_totalmente_ajeno_no_borra_nada(): void
    {
        $a = $this->actividad($this->espacio());
        $b = $this->actividad($this->espacio());

        $this->actingAs($this->usuario());

        $resultado = $this->payload($this->mcp(BorrarTareasMasivoTool::class, [
            'keys' => [$a->key, $b->key],
        ]));

        $this->assertSame([], $resultado['succeeded']);
        $this->assertCount(2, $resultado['failed']);
        $this->assertNotNull(Proyecto::where('key', $a->key)->first());
        $this->assertNotNull(Proyecto::where('key', $b->key)->first());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Catálogos: no filtrar también es filtrar de más
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * listar-categorias devolvía TODAS las categorías del sistema a cualquiera con
     * '.ver', sin mirar membresías: era la única tool del servidor que no aplicaba el
     * scoping por espacio. Filtra taxonomía interna de la empresa (los nombres de las
     * categorías describen áreas y clientes), así que se acota a lo visible.
     */
    public function test_listar_categorias_solo_devuelve_las_de_espacios_visibles(): void
    {
        $miCategoria = GpSpaceCategory::create(['name' => 'Mi Area']);
        $otraCategoria = GpSpaceCategory::create(['name' => 'Area Confidencial']);

        $espacio = $this->espacio(['space_category_id' => $miCategoria->id]);
        $this->espacio(['space_category_id' => $otraCategoria->id]);

        $user = $this->usuario();
        $this->miembro($user, $espacio, 'lector');

        $this->actingAs($user);

        $nombres = array_column($this->payload($this->mcp(ListarCategoriasTool::class))['categorias'], 'name');

        $this->assertContains('Mi Area', $nombres);
        $this->assertNotContains('Area Confidencial', $nombres);
    }

    /** El admin global sigue viendo el catálogo completo: el bypass no se toca. */
    public function test_admin_global_ve_todas_las_categorias(): void
    {
        GpSpaceCategory::create(['name' => 'Mi Area']);
        GpSpaceCategory::create(['name' => 'Area Confidencial']);

        $this->actingAs($this->admin());

        $nombres = array_column($this->payload($this->mcp(ListarCategoriasTool::class))['categorias'], 'name');

        $this->assertContains('Mi Area', $nombres);
        $this->assertContains('Area Confidencial', $nombres);
    }
}
