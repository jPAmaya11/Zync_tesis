{{--
    Error de la autorización OAuth del MCP.

    Se muestra EN PANTALLA en vez de redirigir cuando el fallo está en el client_id o en
    la redirect_uri: si esos datos no son de fiar, no hay destino al que redirigir con
    seguridad (OAuth 2.1 §7.12, contra la redirección abierta).

    Mismo lenguaje visual que consent.blade.php: pastel derivado de --colorPrincipal,
    neutros con tinte lavanda, volúmenes suaves y el stack tipográfico de la app.
    SIEMPRE EN CLARO, igual que el login (GuestLayout.vue no usa ninguna clase dark:).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>{{ $titulo ?? 'No se pudo autorizar' }} · Zync</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">

    <style>
        :root {
            --marca:        #5f5fff;
            --marca-pastel: #ecebff;
            --marca-nube:   #f6f5ff;

            /* El aviso también en pastel: informa sin alarmar. */
            --alerta:        #e0574f;
            --alerta-pastel: #ffeceb;

            --fondo:      #f1f0fc;
            --superficie: #ffffff;
            --tinta:      #23233b;
            --tinta-suave:#6f6d8f;
            --linea:      #e9e7fa;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 16px;
            background: var(--fondo);
            background-image:
                radial-gradient(760px 420px at 12% -8%,  #e5e3ff 0%, transparent 62%),
                radial-gradient(680px 400px at 92% 108%, #eae8ff 0%, transparent 60%);
            color: var(--tinta);
            font-family: Satoshi, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 15px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .tarjeta {
            width: 100%;
            max-width: 440px;
            background: var(--superficie);
            border-radius: 22px;
            padding: 0 28px 28px;
            overflow: hidden;
            text-align: center;
            box-shadow:
                0 1px 1px rgba(35, 35, 59, .04),
                0 10px 22px -10px rgba(95, 95, 255, .22),
                0 28px 60px -24px rgba(95, 95, 255, .28),
                inset 0 1px 0 #fff;
        }

        .logo {
            margin: 0 -28px 24px;
            padding: 26px 28px 22px;
            background: linear-gradient(180deg, var(--marca-nube), var(--superficie));
            border-bottom: 1px solid var(--linea);
        }

        .logo img { display: inline-block; height: 31px; width: auto; }

        /* Disco pastel con el icono dentro: mismo recurso que los checks del consent. */
        .icono {
            width: 54px;
            height: 54px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(180deg, #fff5f4, var(--alerta-pastel));
            color: var(--alerta);
            box-shadow:
                inset 0 1px 0 #fff,
                0 2px 6px -2px rgba(224, 87, 79, .3);
        }

        .icono svg { width: 26px; height: 26px; }

        h1 {
            margin: 0 0 10px;
            font-size: 1.22rem;
            font-weight: 700;
            line-height: 1.32;
            letter-spacing: -.015em;
        }

        .mensaje {
            margin: 0 0 20px;
            color: var(--tinta-suave);
            font-size: .93rem;
        }

        .nota {
            background: var(--marca-nube);
            border-radius: 15px;
            padding: 13px 16px;
            font-size: .86rem;
            color: var(--tinta-suave);
            text-align: left;
            box-shadow:
                inset 0 1px 2px rgba(95, 95, 255, .09),
                inset 0 0 0 1px var(--marca-pastel);
        }

        @media (max-width: 520px) {
            .tarjeta { padding: 0 20px 24px; border-radius: 20px; }
            .logo { margin: 0 -20px 22px; padding: 24px 20px 20px; }
        }
    </style>
</head>
<body>

    <main class="tarjeta">

        <div class="logo">
            <img src="{{ asset('img/logo.png') }}" alt="Zync">
        </div>

        <div class="icono">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
        </div>

        <h1>{{ $titulo ?? 'No se pudo autorizar' }}</h1>

        <p class="mensaje">{{ $mensaje ?? 'La solicitud de autorización no es válida.' }}</p>

        <div class="nota">
            No se concedió ningún acceso. Vuelve a intentar la conexión desde tu aplicación;
            si el problema sigue, avisa al equipo de sistemas.
        </div>

    </main>

</body>
</html>
