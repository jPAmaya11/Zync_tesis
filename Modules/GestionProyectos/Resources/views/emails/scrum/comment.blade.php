@component('gestion-proyectos::emails.layout', [
    'badge' => $hasAttachment ? 'SCRUM · Nuevo adjunto' : 'SCRUM · Nuevo comentario',
    'title' => $hasAttachment ? 'Se adjuntó un archivo a la tarea' : 'Nuevo comentario en la tarea',
    'subtitle' => $key . ' · ' . $summaryText,
])
    <p><strong>{{ $actorName }}</strong> {{ $hasAttachment ? 'agregó un adjunto' : 'comentó' }} en la tarea {{ $key }}.</p>

    @if ($commentText)
        <table class="meta-table">
            <tr><th>Comentario</th><td>{!! nl2br(e($commentText)) !!}</td></tr>
            @if ($hasAttachment && $attachmentName)
                <tr><th>Adjunto</th><td>{{ $attachmentName }}</td></tr>
            @endif
        </table>
    @elseif ($hasAttachment && $attachmentName)
        <table class="meta-table">
            <tr><th>Adjunto</th><td>{{ $attachmentName }}</td></tr>
        </table>
    @endif

    <p>Ingresa al sistema para ver el detalle completo en el Historial de Actividades.</p>
@endcomponent
