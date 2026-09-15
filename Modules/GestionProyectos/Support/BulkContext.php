<?php

namespace Modules\GestionProyectos\Support;

/**
 * Marca un bloque de código como "dentro de una operación masiva" para que
 * los observers de notificaciones supriman correos individuales y delegen al
 * digest que emite el servicio al cerrar el bloque.
 *
 * Uso:
 *   BulkContext::enter();
 *   try { ... } finally { BulkContext::leave(); }
 */
final class BulkContext
{
    private static int $depth = 0;

    /** @var array<int, array{key:string, summary:string, changes: array<int, array{label:string, from:?string, to:?string}>, recipients: array<int,int>}> */
    private static array $changes = [];

    public static function enter(): void
    {
        self::$depth++;
        if (self::$depth === 1) {
            self::$changes = [];
        }
    }

    public static function leave(): void
    {
        self::$depth = max(0, self::$depth - 1);
    }

    public static function inside(): bool
    {
        return self::$depth > 0;
    }

    /**
     * Registra los cambios de una tarea dentro del bloque bulk.
     *
     * @param array<int, array{label:string, from:?string, to:?string}> $changes
     * @param int[] $recipientUserIds
     */
    public static function record(string $key, string $summary, array $changes, array $recipientUserIds): void
    {
        if ($changes === []) {
            return;
        }
        self::$changes[] = [
            'key'        => $key,
            'summary'    => $summary,
            'changes'    => $changes,
            'recipients' => array_values(array_unique(array_map('intval', $recipientUserIds))),
        ];
    }

    /** @return array<int, array{key:string, summary:string, changes:array, recipients:int[]}> */
    public static function flush(): array
    {
        $out = self::$changes;
        self::$changes = [];
        return $out;
    }
}
