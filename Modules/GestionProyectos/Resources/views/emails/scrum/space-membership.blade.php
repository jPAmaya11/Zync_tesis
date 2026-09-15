@component('gestion-proyectos::emails.layout', [
    'badge' => 'SCRUM · Membresía',
    'title' => $action === 'added' ? 'Te agregaron a un espacio' : 'Fuiste removido de un espacio',
    'subtitle' => $spaceName,
])
    <p>Hola,</p>
    @if ($action === 'added')
        <p><strong>{{ $actorName }}</strong> te agregó al espacio <strong>{{ $spaceName }}</strong> ({{ $spaceKey }}) con el rol <strong>{{ ucfirst($role) }}</strong>.</p>
        <p>Ya puedes acceder al espacio y consultar las tareas que correspondan a tu rol.</p>
    @else
        <p><strong>{{ $actorName }}</strong> te removió del espacio <strong>{{ $spaceName }}</strong> ({{ $spaceKey }}).</p>
        <p>Ya no recibirás notificaciones de tareas asociadas a este espacio.</p>
    @endif
@endcomponent
