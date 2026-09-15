<?php

namespace Modules\GestionProyectos\Support;

/**
 * Marca el bloque actual como "creación de evidencia para una transición crítica".
 *
 * Lo usa GestionProyectosController::storeActivityHistory cuando el frontend
 * indica que la entrada de historial es la evidencia exigida para mover una
 * tarea a un estado crítico (Finalizado / Reprogramado). El observer
 * GpActivityHistoryObserver consulta este contexto y suprime el correo de
 * "nuevo comentario", porque inmediatamente después el cliente dispara la
 * transición y ProyectoNotificationObserver enviará un único correo de
 * "cambio de estado" con el comentario + adjunto embebidos.
 *
 * Patrón análogo a [[bulk-context]] pero con un alcance distinto.
 */
final class CriticalTransitionContext
{
    private static int $depth = 0;

    public static function enter(): void
    {
        self::$depth++;
    }

    public static function leave(): void
    {
        self::$depth = max(0, self::$depth - 1);
    }

    public static function inside(): bool
    {
        return self::$depth > 0;
    }
}
