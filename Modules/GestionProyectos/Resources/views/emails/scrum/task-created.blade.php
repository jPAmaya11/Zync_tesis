@component('gestion-proyectos::emails.layout', [
    'badge' => 'SCRUM · Nueva tarea',
    'title' => $forAssignee ? 'Te asignaron una nueva tarea' : 'Nueva tarea creada',
    'subtitle' => $key . ' · ' . $projectKey,
])
    <p>Hola,</p>
    @if ($forAssignee)
        <p><strong>{{ $createdBy }}</strong> creó una nueva tarea y te la asignó como responsable.</p>
    @else
        <p><strong>{{ $createdBy }}</strong> registró una nueva tarea en el espacio.</p>
    @endif

    <table class="meta-table">
        <tr><th>Clave</th><td>{{ $key }}</td></tr>
        <tr><th>Resumen</th><td>{{ $summaryText }}</td></tr>
        <tr><th>Estado</th><td>{{ $statusText }}</td></tr>
        <tr><th>Prioridad</th><td>{{ $priorityText }}</td></tr>
        @if ($assigneeName)
            <tr><th>Asignado a</th><td>{{ $assigneeName }}</td></tr>
        @endif
        @if ($startDate)
            <tr><th>Fecha inicio</th><td>{{ $startDate }}</td></tr>
        @endif
        @if ($dueDate)
            <tr><th>Fecha límite</th><td>{{ $dueDate }}</td></tr>
        @endif
    </table>

    <p>Ingresa al sistema para ver el detalle y dar seguimiento.</p>
@endcomponent
