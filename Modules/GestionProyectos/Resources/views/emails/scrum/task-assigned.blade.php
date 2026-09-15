@component('gestion-proyectos::emails.layout', [
    'badge' => 'SCRUM · Asignación',
    'title' => $isForAssignee ? 'Te asignaron una tarea' : 'La tarea fue reasignada',
    'subtitle' => $key,
])
    <p>Hola,</p>
    @if ($isForAssignee)
        <p><strong>{{ $actorName }}</strong> te asignó como responsable de la siguiente tarea:</p>
    @else
        <p><strong>{{ $actorName }}</strong> reasignó la tarea a <strong>{{ $assigneeName }}</strong>.</p>
    @endif

    <table class="meta-table">
        <tr><th>Clave</th><td>{{ $key }}</td></tr>
        <tr><th>Resumen</th><td>{{ $summaryText }}</td></tr>
        <tr><th>Estado</th><td>{{ $statusText }}</td></tr>
        <tr><th>Prioridad</th><td>{{ $priorityText }}</td></tr>
        <tr><th>Responsable</th><td>{{ $assigneeName }}</td></tr>
        @if ($dueDate)
            <tr><th>Fecha límite</th><td>{{ $dueDate }}</td></tr>
        @endif
    </table>
@endcomponent
