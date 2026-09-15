{{--
    Pantalla de consentimiento OAuth del MCP de Gestión de Proyectos.

    Es una página STANDALONE (fuera del SPA de Inertia): el usuario llega aquí desde el
    login de Breeze, sin Vue montado, así que no puede reutilizar los componentes .vue del
    módulo y replica a mano el sistema de diseño.

    TIPOGRAFÍA: el mismo stack que resuelve `font-sans` en el resto de Zync
    (tailwind.config.js → sans: ["Satoshi", "sans-serif"]). Ojo: Satoshi está declarada
    pero NO se carga en ninguna parte —no hay @font-face ni archivos de fuente—, así que
    hoy toda la app cae a la sans del sistema. Se replica igual para que esta pantalla se
    vea exactamente como las demás; si algún día se añade Satoshi, la tomará sola.

    COLOR: parte de --colorPrincipal (#5f5fff) de resources/css/app.css y construye
    alrededor una paleta pastel derivada de ese mismo tono, con neutros de tinte lavanda
    en vez de grises puros.

    SIEMPRE EN CLARO, a propósito: el login del sistema (GuestLayout.vue) usa bg-gray-100
    y bg-white sin ninguna clase dark:, o sea que es claro pase lo que pase. Esta pantalla
    aparece justo después del login, así que seguir el tema oscuro rompería la continuidad.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <title>Autorizar conexión · Zync</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">

    <style>
        :root {
            /* Marca: --colorPrincipal de app.css y su familia pastel. */
            --marca:         #5f5fff;
            --marca-hondo:   #4a4ae0;   /* canto inferior de los botones (el relieve) */
            --marca-claro:   #8b8bff;
            --marca-pastel:  #ecebff;
            --marca-nube:    #f6f5ff;

            /* Neutros con tinte lavanda: un gris puro al lado del índigo se ve sucio. */
            --fondo:      #f1f0fc;
            --superficie: #ffffff;
            --tinta:      #23233b;
            --tinta-suave:#6f6d8f;
            --linea:      #e9e7fa;

            --radio:       22px;
            --radio-chico: 15px;
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
            /* Dos luces pastel muy suaves: dan profundidad sin ensuciar el fondo. */
            background-image:
                radial-gradient(760px 420px at 12% -8%,  #e5e3ff 0%, transparent 62%),
                radial-gradient(680px 400px at 92% 108%, #eae8ff 0%, transparent 60%);
            color: var(--tinta);
            font-family: Satoshi, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 15px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Tarjeta ─────────────────────────────────────────────────────────
           Sin borde duro: el volumen lo dan sombras superpuestas, una difusa y
           teñida de marca, y un filo claro arriba que simula la luz. */
        .tarjeta {
            width: 100%;
            max-width: 462px;
            background: var(--superficie);
            border-radius: var(--radio);
            padding: 0 28px 26px;
            overflow: hidden;
            box-shadow:
                0 1px 1px rgba(35, 35, 59, .04),
                0 10px 22px -10px rgba(95, 95, 255, .22),
                0 28px 60px -24px rgba(95, 95, 255, .28),
                inset 0 1px 0 #fff;
        }

        /* ── Cabecera con el logo ────────────────────────────────────────────
           Fusionada con la tarjeta, igual que .appLogo en el login. */
        .logo {
            margin: 0 -28px 24px;
            padding: 26px 28px 22px;
            text-align: center;
            background: linear-gradient(180deg, var(--marca-nube), var(--superficie));
            border-bottom: 1px solid var(--linea);
        }

        .logo img { display: inline-block; height: 41px; width: auto; }

        h1 {
            margin: 0 0 18px;
            font-size: 1.28rem;
            font-weight: 700;
            line-height: 1.32;
            letter-spacing: -.015em;
        }

        .cliente { color: var(--marca); }

        /* ── Header de conexión (dos nodos + enlace), estilo del screenshot ── */
        .conexion {
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 4px;
            margin: 2px 0 22px;
        }

        /* Línea de traza horizontal entre los dos nodos (detrás): los íconos y el
           eslabón la tapan donde se solapan, dejándola visible solo en los huecos. */
        .conexion::before {
            content: "";
            position: absolute;
            top: 27px;              /* centro vertical de los íconos (56px de alto) */
            left: 27%;
            right: 27%;
            border-top: 2px dashed #cbc7e6;
            z-index: 0;
        }

        .nodo { flex: 1; max-width: 140px; text-align: center; }

        .nodo-icono {
            position: relative;
            z-index: 1;
            width: 56px; height: 56px;
            margin: 0 auto 10px;
            border-radius: 16px;
            display: grid; place-items: center;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .38), 0 7px 16px -7px rgba(35, 35, 59, .3);
        }

        .nodo-icono svg { width: 30px; height: 30px; color: #fff; }
        .nodo-icono--ia         { background: linear-gradient(180deg, #6f6fff, var(--marca)); }
        .nodo-icono--plataforma { background: linear-gradient(180deg, #2fbcb3, #12a79e); }

        .nodo .nombre-nodo { font-weight: 700; font-size: .9rem; line-height: 1.25; letter-spacing: -.01em; }
        .nodo .rol-nodo    { color: var(--tinta-suave); font-size: .76rem; margin-top: 3px; }

        .enlace {
            position: relative;
            z-index: 1;
            flex: none;
            margin-top: 16px;
            width: 26px; height: 26px;
            border-radius: 50%;
            display: grid; place-items: center;
            background: var(--superficie);
            color: var(--marca-claro);
            box-shadow: inset 0 0 0 1px var(--linea), 0 2px 6px -3px rgba(35, 35, 59, .22);
        }

        .enlace svg { width: 13px; height: 13px; }

        .titulo-conexion { text-align: center; }
        .titulo-conexion h1 { margin: 0 0 22px; }

        /* ── Bloque de identidad ─────────────────────────────────────────────
           Relieve hundido: parece una pastilla incrustada en la tarjeta. */
        .cuenta {
            background: var(--marca-nube);
            border-radius: var(--radio-chico);
            padding: 15px 17px;
            margin-bottom: 20px;
            box-shadow:
                inset 0 1px 2px rgba(95, 95, 255, .09),
                inset 0 0 0 1px var(--marca-pastel);
        }

        .cuenta .etiqueta {
            font-size: .67rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--marca-claro);
            margin-bottom: 4px;
        }

        .cuenta .nombre { font-weight: 700; }

        .cuenta .correo {
            color: var(--tinta-suave);
            font-size: .88rem;
            word-break: break-all;
        }

        .cuenta .cambiar {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--marca-pastel);
            font-size: .85rem;
            color: var(--tinta-suave);
        }

        .cuenta .cambiar button {
            background: none;
            border: 0;
            padding: 0;
            font: inherit;
            font-weight: 700;
            color: var(--marca);
            cursor: pointer;
            border-radius: 4px;
        }

        .cuenta .cambiar button:hover { text-decoration: underline; text-underline-offset: 3px; }

        /* ── Puntos ──────────────────────────────────────────────────────────
           Cada check en su pastilla redonda: pequeños volúmenes, no iconos planos. */
        .puntos { margin: 0 0 24px; padding: 0; list-style: none; }

        .puntos li {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            margin-bottom: 12px;
            font-size: .89rem;
            color: var(--tinta-suave);
        }

        .puntos li:last-child { margin-bottom: 0; }

        .puntos .check {
            flex: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(180deg, #f3f2ff, var(--marca-pastel));
            color: var(--marca);
            box-shadow:
                inset 0 1px 0 #fff,
                0 1px 2px rgba(95, 95, 255, .18);
        }

        .puntos .check svg { width: 13px; height: 13px; }

        /* ── Botones ─────────────────────────────────────────────────────────
           El canto inferior sólido es lo que da el volumen; al pulsar, el botón
           baja y el canto se encoge: se hunde de verdad bajo el dedo. */
        .acciones { display: flex; gap: 11px; }

        .boton {
            flex: 1;
            padding: 12px 18px;
            border: 0;
            border-radius: 14px;
            font: inherit;
            font-weight: 700;
            font-size: .93rem;
            cursor: pointer;
            transition: transform .12s ease, box-shadow .12s ease, background-color .12s ease;
        }

        .boton:focus-visible { outline: 2px solid var(--marca); outline-offset: 3px; }

        .boton--primario {
            background: linear-gradient(180deg, #6f6fff, var(--marca));
            color: #fff;
            box-shadow:
                0 4px 0 0 var(--marca-hondo),
                0 8px 18px -6px rgba(95, 95, 255, .55),
                inset 0 1px 0 rgba(255, 255, 255, .34);
        }

        .boton--primario:hover { background: linear-gradient(180deg, #7b7bff, #6666ff); }

        .boton--primario:active {
            transform: translateY(3px);
            box-shadow:
                0 1px 0 0 var(--marca-hondo),
                0 3px 8px -4px rgba(95, 95, 255, .5),
                inset 0 1px 0 rgba(255, 255, 255, .28);
        }

        .boton--secundario {
            background: linear-gradient(180deg, #fff, #f7f6fd);
            color: var(--tinta-suave);
            box-shadow:
                0 3px 0 0 #e4e2f4,
                0 6px 14px -8px rgba(35, 35, 59, .2),
                inset 0 0 0 1px var(--linea);
        }

        .boton--secundario:hover { color: var(--tinta); }

        .boton--secundario:active {
            transform: translateY(2px);
            box-shadow:
                0 1px 0 0 #e4e2f4,
                0 2px 6px -4px rgba(35, 35, 59, .18),
                inset 0 0 0 1px var(--linea);
        }

        @media (prefers-reduced-motion: reduce) {
            .boton { transition: none; }
            .boton:active { transform: none; }
        }

        @media (max-width: 520px) {
            .tarjeta { padding: 0 20px 22px; border-radius: 20px; }
            .logo { margin: 0 -20px 22px; padding: 24px 20px 20px; }
            .acciones { flex-direction: column-reverse; }
        }
    </style>
</head>
<body>

    <main class="tarjeta">

        <div class="logo">
            <img src="{{ asset('img/logo.png') }}" alt="Zync">
        </div>

        <div class="conexion">
            <div class="nodo">
                <div class="nodo-icono nodo-icono--ia">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="8" width="16" height="12" rx="3.5"/>
                        <path d="M12 8V4.5"/>
                        <circle cx="12" cy="3.2" r="1.3" fill="currentColor" stroke="none"/>
                        <circle cx="9" cy="14" r="1.4" fill="currentColor" stroke="none"/>
                        <circle cx="15" cy="14" r="1.4" fill="currentColor" stroke="none"/>
                        <path d="M2 13.5v3M22 13.5v3"/>
                    </svg>
                </div>
                <div class="nombre-nodo">{{ $client->client_name }}</div>
                <div class="rol-nodo">Asistente de IA</div>
            </div>

            <div class="enlace">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9.5 13.5a4 4 0 005.66 0l2.12-2.12a4 4 0 00-5.66-5.66l-1 1"/>
                    <path d="M14.5 10.5a4 4 0 00-5.66 0l-2.12 2.12a4 4 0 005.66 5.66l1-1"/>
                </svg>
            </div>

            <div class="nodo">
                <div class="nodo-icono nodo-icono--plataforma">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="7" width="18" height="13" rx="2.5"/>
                        <path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        <path d="M3 12.5h18"/>
                    </svg>
                </div>
                <div class="nombre-nodo">Gestión de Proyectos</div>
                <div class="rol-nodo">Plataforma Zync</div>
            </div>
        </div>

        <div class="titulo-conexion">
            <h1>Autoriza la conexión</h1>
        </div>

        <div class="cuenta">
            <div class="etiqueta">Autorizando como</div>
            <div class="nombre">{{ $user->name }}</div>
            <div class="correo">{{ $user->email }}</div>
            <div class="cambiar">
                ¿No eres tú?
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                    @csrf
                    <button type="submit">Cambiar de cuenta</button>
                </form>
            </div>
        </div>

        <ul class="puntos">
            <li>
                <span class="check">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span>Solo verá y podrá hacer lo mismo que tú en Gestión de Proyectos.</span>
            </li>
            <li>
                <span class="check">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span>Seguirá conectado mientras lo uses, sin repetir este paso.</span>
            </li>
        </ul>

        <form method="POST" action="{{ url('oauth/authorize') }}">
            @csrf

            <input type="hidden" name="client_id"             value="{{ $client->client_id }}">
            <input type="hidden" name="redirect_uri"          value="{{ $params['redirect_uri'] }}">
            <input type="hidden" name="state"                 value="{{ $params['state'] ?? '' }}">
            <input type="hidden" name="code_challenge"        value="{{ $params['code_challenge'] }}">
            <input type="hidden" name="code_challenge_method" value="S256">
            <input type="hidden" name="response_type"         value="code">
            <input type="hidden" name="scope"                 value="{{ implode(' ', $scopes) }}">
            <input type="hidden" name="resource"              value="{{ $params['resource'] }}">

            <div class="acciones">
                <button type="submit" name="accion" value="denegar" class="boton boton--secundario">
                    Cancelar
                </button>
                <button type="submit" name="accion" value="aprobar" class="boton boton--primario">
                    Autorizar
                </button>
            </div>
        </form>

    </main>

</body>
</html>
