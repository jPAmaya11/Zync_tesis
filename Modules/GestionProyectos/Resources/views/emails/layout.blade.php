<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectTitle ?? 'Notificación' }}</title>
    <style>
        body { margin:0; padding:0; background:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; color:#1f2937; line-height:1.55; }
        .wrap { max-width:640px; margin:0 auto; padding:24px 16px; }
        .card { background:#ffffff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:hidden; }
        .header { padding:24px 28px; border-bottom:1px solid #e5e7eb; }
        .header .badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; background:#ECF3F7; color:#03476B; }
        .header h1 { margin:12px 0 0; font-size:20px; color:#111827; font-weight:600; }
        .header .subtitle { margin:6px 0 0; color:#6b7280; font-size:13px; }
        .body { padding:24px 28px; font-size:14px; }
        .body p { margin:0 0 12px; }
        .meta-table { width:100%; border-collapse:collapse; margin:16px 0; font-size:13px; }
        .meta-table th, .meta-table td { padding:8px 10px; border-bottom:1px solid #f3f4f6; text-align:left; vertical-align:top; }
        .meta-table th { width:34%; color:#6b7280; font-weight:500; }
        .meta-table td { color:#111827; }
        .change-list { margin:8px 0 0; padding:0; list-style:none; }
        .change-list li { padding:6px 0; border-bottom:1px dashed #e5e7eb; font-size:13px; }
        .change-list li strong { color:#374151; }
        .change-list .from { color:#b91c1c; text-decoration:line-through; }
        .change-list .to { color:#047857; }
        .cta { display:inline-block; margin-top:12px; padding:10px 18px; background:#056599; color:#fff !important; border-radius:6px; text-decoration:none; font-weight:500; font-size:13px; }
        .footer { padding:16px 28px; background:#f9fafb; color:#9ca3af; font-size:11px; text-align:center; border-top:1px solid #e5e7eb; }
        .footer a { color:#6b7280; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="header">
            @isset($badge)<span class="badge">{{ $badge }}</span>@endisset
            <h1>{{ $title ?? 'Notificación' }}</h1>
            @isset($subtitle)<p class="subtitle">{{ $subtitle }}</p>@endisset
        </div>
        <div class="body">
            {!! $slot !!}
        </div>
        <div class="footer">
            Este mensaje fue generado automáticamente por {{ config('app.name', 'Zync') }}.<br>
            No respondas a este correo.
        </div>
    </div>
</div>
</body>
</html>
