@component('gestion-proyectos::emails.layout', [
    'badge' => 'SCRUM · Resumen masivo',
    'title' => 'Resumen de cambios en ' . count($items) . ' tareas',
    'subtitle' => 'Acción realizada por ' . $actorName,
])
    <p>Hola,</p>
    <p><strong>{{ $actorName }}</strong> realizó una actualización masiva. A continuación, el resumen de cambios en las tareas donde eres parte:</p>

    @foreach ($items as $item)
        <div style="margin:18px 0 0; padding:12px 14px; background:#f9fafb; border-radius:6px;">
            <div style="font-weight:600; color:#111827; margin-bottom:6px;">
                {{ $item['key'] }} — {{ $item['summary'] }}
            </div>
            <ul class="change-list">
                @foreach ($item['changes'] as $change)
                    <li>
                        <strong>{{ $change['label'] }}:</strong>
                        <span class="from">{{ $change['from'] ?? '—' }}</span>
                        →
                        <span class="to">{{ $change['to'] ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach

    <p style="margin-top:18px;">Ingresa al sistema para revisar el detalle completo.</p>
@endcomponent
