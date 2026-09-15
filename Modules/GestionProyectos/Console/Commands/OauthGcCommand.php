<?php

namespace Modules\GestionProyectos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Modules\GestionProyectos\Models\GpApiToken;
use Modules\GestionProyectos\Models\GpOauthAuthCode;
use Modules\GestionProyectos\Models\GpOauthClient;

/**
 * Recolector de basura del authorization server OAuth (ver docs/PLAN-OAUTH-MCP.md).
 *
 * Se pensó para el cron ya existente (schedule:run). Sin él, tres tablas crecen sin
 * techo: Claude registra un cliente DCR nuevo en cada conexión fresca, cada login deja
 * un código de autorización de 60 segundos y cada sesión cortada deja su fila.
 *
 * Uso:
 *   php artisan gp:oauth-gc              → limpia
 *   php artisan gp:oauth-gc --dry-run    → solo informa, no borra nada
 */
class OauthGcCommand extends Command
{
    protected $signature = 'gp:oauth-gc
        {--dry-run : Informa cuántas filas se borrarían sin borrar nada}';

    protected $description = 'Recolector de basura de OAuth: borra códigos de autorización vencidos o usados, clientes registrados que nunca se usaron y sesiones MCP muertas hace más de 7 días.';

    /**
     * Días de gracia antes de borrar una sesión muerta.
     *
     * Una sesión revocada o con el refresh vencido se sigue mostrando en el panel como
     * "expirada" durante este tiempo: si desapareciera al instante, el usuario vería
     * evaporarse su conexión sin ninguna explicación de qué pasó.
     */
    private const DIAS_GRACIA = 7;

    /** Días que un cliente registrado puede estar sin estrenarse antes de ser basura. */
    private const DIAS_CLIENTE_SIN_USO = 7;

    /** Filas por lote: se borra a trozos para no cargar la tabla en memoria. */
    private const TAMANO_LOTE = 500;

    public function handle(): int
    {
        $simular = (bool) $this->option('dry-run');
        $corte   = now()->subDays(self::DIAS_GRACIA);

        if ($simular) {
            $this->warn('MODO SIMULACIÓN (--dry-run): no se borrará ninguna fila.');
        }

        $codigos  = $this->limpiarCodigos($simular);
        $sesiones = $this->limpiarSesiones($simular, $corte);
        $clientes = $this->limpiarClientes($simular, $corte);

        $verbo = $simular ? 'Se borrarían' : 'Borradas';

        $this->line('');
        $this->info("{$verbo} {$codigos} filas de gp_oauth_auth_codes (códigos vencidos o ya usados).");
        $this->info("{$verbo} {$sesiones} filas de gp_api_tokens (sesiones OAuth muertas hace más de " . self::DIAS_GRACIA . ' días).');
        $this->info("{$verbo} {$clientes} filas de gp_oauth_clients (clientes registrados que nunca se usaron).");

        return self::SUCCESS;
    }

    /**
     * Códigos de autorización: vencidos o ya canjeados.
     *
     * Viven ~60 segundos y son de un solo uso, así que en cuanto se marca used_at
     * o pasa expires_at la fila no vuelve a servir para nada. No hay periodo de
     * gracia: el usuario nunca ve estos códigos, no hay nada que explicarle.
     */
    private function limpiarCodigos(bool $simular): int
    {
        $query = GpOauthAuthCode::query()->where(function (Builder $q) {
            $q->where('expires_at', '<', now())
              ->orWhereNotNull('used_at');
        });

        return $this->borrarPorLotes($query, 'id', $simular);
    }

    /**
     * Sesiones MCP muertas: revocadas o con el refresh vencido hace más de 7 días.
     *
     * CRÍTICO — whereNotNull('client_id'): los tokens con client_id NULL son los
     * MANUALES de integración servidor a servidor (hoy, el chatbot de WhatsApp). No
     * pasan por OAuth porque el flujo exige un navegador y una persona aprobando, y no
     * caducan nunca. Además la migración que estrenó OAuth los dejó a todos con
     * revoked_at marcado, así que sin este filtro el GC arrasaría con el token del
     * chatbot y con cualquier token heredado de la API REST. Este comando toca
     * ÚNICAMENTE sesiones OAuth.
     */
    private function limpiarSesiones(bool $simular, \DateTimeInterface $corte): int
    {
        $query = GpApiToken::query()
            ->whereNotNull('client_id')
            ->where(function (Builder $q) use ($corte) {
                $q->where('revoked_at', '<', $corte)
                  ->orWhere('refresh_expires_at', '<', $corte);
            });

        return $this->borrarPorLotes($query, 'id', $simular);
    }

    /**
     * Clientes DCR que nunca llegaron a usarse.
     *
     * Claude registra un cliente nuevo cada vez que se conecta de cero; los intentos
     * abandonados a mitad del flujo dejan la fila huérfana. Se dan 7 días de margen
     * para no matar un registro recién creado que aún esté completando su primer login.
     *
     * Nunca se borra un cliente con sesiones asociadas en gp_api_tokens. El criterio es
     * a propósito más estricto que "tokens vivos": también protege a las sesiones ya
     * muertas que siguen dentro de la ventana de gracia, porque hasta que se purguen
     * el usuario las está viendo en el panel.
     */
    private function limpiarClientes(bool $simular, \DateTimeInterface $corte): int
    {
        $query = GpOauthClient::query()
            ->whereNull('last_used_at')
            ->where('created_at', '<', now()->subDays(self::DIAS_CLIENTE_SIN_USO))
            ->whereNotExists(function (QueryBuilder $q) use ($corte) {
                $q->select(DB::raw(1))
                  ->from('gp_api_tokens')
                  ->whereColumn('gp_api_tokens.client_id', 'gp_oauth_clients.client_id')
                  // Token que sobrevive a la purga = sigue vivo, o murió hace poco y
                  // aún se muestra como "expirada". Los NULL se comparan a mano: en
                  // SQL, "revoked_at >= corte" con NULL no da verdadero.
                  ->where(function (QueryBuilder $vivo) use ($corte) {
                      $vivo->whereNull('revoked_at')
                           ->orWhere('revoked_at', '>=', $corte);
                  })
                  ->where(function (QueryBuilder $vigente) use ($corte) {
                      $vigente->whereNull('refresh_expires_at')
                              ->orWhere('refresh_expires_at', '>=', $corte);
                  });
            });

        return $this->borrarPorLotes($query, 'client_id', $simular);
    }

    /**
     * Borra en lotes de TAMANO_LOTE filas.
     *
     * Se seleccionan solo las claves —nunca los modelos completos— y se borra por
     * whereIn: ni se carga la tabla en memoria ni se lanza un DELETE gigante que
     * bloquee la tabla mientras el MCP está atendiendo peticiones.
     *
     * @param  Builder  $query  Consulta que aísla las filas basura.
     * @param  string   $llave  Clave primaria por la que se borra (id, o client_id).
     */
    private function borrarPorLotes(Builder $query, string $llave, bool $simular): int
    {
        if ($simular) {
            return (int) $query->clone()->count();
        }

        $modelo = $query->getModel();
        $total  = 0;

        while (true) {
            $llaves = $query->clone()
                ->orderBy($llave)
                ->limit(self::TAMANO_LOTE)
                ->pluck($llave);

            if ($llaves->isEmpty()) {
                return $total;
            }

            $borradas = $modelo->newQuery()->whereIn($llave, $llaves)->delete();

            // Salvaguarda: si un lote no borra nada, la condición no avanza y el
            // bucle sería infinito. Mejor salir que colgar el cron.
            if ($borradas === 0) {
                return $total;
            }

            $total += $borradas;
        }
    }
}
