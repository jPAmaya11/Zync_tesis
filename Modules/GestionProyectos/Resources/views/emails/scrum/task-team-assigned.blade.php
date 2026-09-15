@component('gestion-proyectos::emails.layout', [
    'badge' => 'SCRUM · Equipo asignado',
    'title' => 'Tu equipo fue asignado a una tarea',
    'subtitle' => $key,
])
    <p>Hola,</p>
    <p>
        <strong>{{ $actorName }}</strong> asignó a tu equipo
        <strong>{{ $teamName }}</strong> como responsable de la siguiente tarea:
    </p>

    <table class="meta-table">
        <tr><th>Clave</th><td>{{ $key }}</td></tr>
        <tr><th>Resumen</th><td>{{ $summaryText }}</td></tr>
        <tr><th>Estado</th><td>{{ $statusText }}</td></tr>
        <tr><th>Prioridad</th><td>{{ $priorityText }}</td></tr>
        <tr><th>Equipo</th><td>{{ $teamName }}</td></tr>
        @if ($dueDate)
            <tr><th>Fecha de vencimiento</th><td>{{ $dueDate }}</td></tr>
        @endif
    </table>

    <p>Coordina con tu equipo para dar seguimiento a esta actividad.</p>
@endcomponent
