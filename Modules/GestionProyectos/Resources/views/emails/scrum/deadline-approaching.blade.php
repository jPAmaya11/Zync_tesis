@component('gestion-proyectos::emails.layout', [
    'badge' => 'SCRUM · Vencimiento próximo',
    'title' => 'Tarea por vencer en las próximas ' . $windowHours . ' horas',
    'subtitle' => $key,
])
    <p>Hola,</p>
    <p>La tarea <strong>{{ $key }}</strong> — {{ $summaryText }} se vence en las próximas {{ $windowHours }} horas y aún no se cierra.</p>

    <table class="meta-table">
        <tr><th>Estado actual</th><td>{{ $statusText }}</td></tr>
        <tr><th>Prioridad</th><td>{{ $priorityText }}</td></tr>
        @if ($dueDate)
            <tr><th>Fecha límite</th><td><strong>{{ $dueDate }}</strong></td></tr>
        @endif
    </table>

    <p>Te recomendamos revisar el detalle, actualizar el avance o reprogramar la entrega si corresponde.</p>
@endcomponent
