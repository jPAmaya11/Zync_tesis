@component('gestion-proyectos::emails.layout', [
    'badge' => $isCritical ? 'SCRUM · Transición crítica' : 'SCRUM · Cambio de estado',
    'title' => $isCritical ? 'Tarea ' . $toStatus : 'Estado actualizado a ' . $toStatus,
    'subtitle' => $key,
])
    <p>Hola,</p>
    <p><strong>{{ $actorName }}</strong> movió la tarea <strong>{{ $key }}</strong> — {{ $summaryText }}.</p>

    <table class="meta-table">
        <tr><th>Estado anterior</th><td>{{ $fromStatus }}</td></tr>
        <tr><th>Estado nuevo</th><td><strong>{{ $toStatus }}</strong></td></tr>
        @if ($evidenceComment)
            <tr><th>Comentario</th><td>{!! nl2br(e($evidenceComment)) !!}</td></tr>
        @endif
        @if ($evidenceAttachmentName)
            <tr><th>Adjunto</th><td>{{ $evidenceAttachmentName }}</td></tr>
        @endif
    </table>

    @if ($isCritical && !$evidenceComment)
        <p>Esta transición está marcada como crítica. Ingresa al sistema para revisar la evidencia adjunta en el Historial de Actividades.</p>
    @elseif ($isCritical && $evidenceId)
        <p>La evidencia adjunta está disponible para descarga dentro del Historial de Actividades de la tarea.</p>
    @endif
@endcomponent
