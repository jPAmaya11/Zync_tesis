@component('gestion-proyectos::emails.layout', [
    'badge' => 'SCRUM · Desasignación',
    'title' => 'Ya no estás asignado a esta tarea',
    'subtitle' => $key,
])
    <p>Hola {{ $previousAssigneeName }},</p>
    <p>
        <strong>{{ $actorName }}</strong> reasignó la siguiente tarea a
        <strong>{{ $newAssigneeName }}</strong>. Ya no figura como tu responsabilidad.
    </p>
    <p>
        Si tenías trabajo en curso sobre esta tarea, considera coordinar el handoff
        con el nuevo responsable.
    </p>

    <table class="meta-table">
        <tr><th>Clave</th><td>{{ $key }}</td></tr>
        <tr><th>Resumen</th><td>{{ $summaryText }}</td></tr>
        <tr><th>Estado</th><td>{{ $statusText }}</td></tr>
        <tr><th>Prioridad</th><td>{{ $priorityText }}</td></tr>
        <tr><th>Nuevo responsable</th><td>{{ $newAssigneeName }}</td></tr>
        @if ($dueDate)
            <tr><th>Fecha límite</th><td>{{ $dueDate }}</td></tr>
        @endif
    </table>
@endcomponent
