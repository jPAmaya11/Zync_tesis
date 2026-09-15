<?php

namespace Modules\GestionProyectos\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fuerza el header Content-Length en las respuestas del MCP.
 *
 * El server de desarrollo de PHP (`php artisan serve`, que usa Sail) responde con
 * `Connection: close` y SIN `Content-Length` → respuestas "close-delimited". El
 * cliente HTTP de mcp-remote (undici) crashea con eso (`assert(!this.paused)`).
 * Fijar Content-Length hace que undici lea exactamente N bytes y cierre limpio.
 * En producción (nginx) las respuestas ya vienen bien; este middleware no molesta.
 */
class ForceContentLength
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Las respuestas en streaming (SSE) no llevan Content-Length: las dejamos.
        if ($response instanceof StreamedResponse) {
            return $response;
        }

        if (! $response->headers->has('Content-Length')) {
            $content = $response->getContent();
            if ($content !== false) {
                $response->headers->set('Content-Length', (string) strlen($content));
            }
        }

        return $response;
    }
}
