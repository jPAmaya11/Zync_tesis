@component('gestion-proyectos::emails.layout', [
    'badge' => 'SCRUM · Cambios',
    'title' => 'Se actualizó una tarea',
    'subtitle' => $key,
])
    <p>Hola,</p>
    <p><strong>{{ $actorName }}</strong> realizó cambios en la tarea <strong>{{ $key }}</strong> — {{ $summaryText }}:</p>

    <ul class="change-list">
        @foreach ($changes as $change)
            <li>
                <strong>{{ $change['label'] }}:</strong>
                <span class="from">{{ $change['from'] ?? '—' }}</span>
                →
                <span class="to">{{ $change['to'] ?? '—' }}</span>
            </li>
        @endforeach
    </ul>
@endcomponent
