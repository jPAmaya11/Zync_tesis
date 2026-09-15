# DOCUMENTACIÓN — Módulo Gestión de Proyectos

Herramienta de gestión ágil de trabajo (**SCRUM Project**): espacios, actividades, subactividades,
tareas, reprogramaciones, evidencia y aprobación de estados. El mismo trabajo se puede operar por
**tres canales** —web, API REST y un servidor MCP— que comparten las mismas reglas de negocio.

---

## Herramientas que integra

- **Inertia + Vue 3** — interfaz tipo tablero (páginas bajo `resources/js/Pages/GestionProyectos/`).
- **Spatie Permissions + roles de espacio** — dos capas de autorización: permisos globales
  (`gestion-proyectos.ver` / `.miembro` / `.admin`) **y** roles por espacio guardados en
  `gp_space_members` (propietario, administrador, ejecutor, aprobador, lector, implementador).
  Toda la autorización fina vive en `GpSpaceMember` (ver punto crítico 1).
- **API REST (token Bearer)** — `api/gestion-proyectos/*`; el usuario genera su token desde la
  web. Reusa `ProyectoService` y las **mismas** policies por espacio.
- **Servidor MCP (Model Context Protocol) sobre HTTP** — `POST /mcp/gestion-proyectos`, ~46
  tools que exponen el CRUD SCRUM a clientes de IA. Misma auth por token, `throttle:120,1`.
- **Infra de correo multi-provider** — **SendGrid** (primario) + **Resend** (failover) con
  cuotas y orquestador (`Services/Mail/*`), panel admin de proveedores, y `SendMailJob` para
  envío asíncrono. 11 mailables `Scrum*` para asignación, cambios de estado, comentarios, etc.
- **Auditoría** — **dos** tablas con propósitos distintos (NO confundir, punto crítico 10):
  `gp_audit_log` (cambios de campo, append-only, lo escribe `ProyectoObserver`) y
  `gp_activity_history` (comentarios y **evidencia** de transiciones críticas, **inmutable**).
- **Logs / Trazabilidad** — `Log` de Laravel + `gp_mail_logs` (log técnico de cada envío) +
  el timeline unificado (audit + comentarios) que la UI muestra por actividad. Historial propio
  también en subtareas (`gp_sub_tarea_historial`).
- **Observers** — 6: auditoría (`ProyectoObserver`), notificaciones (`ProyectoNotificationObserver`),
  y sincronización de espacios/miembros/campos/historial.
- **PHPUnit** — testsuite `Modules` de `phpunit.xml` (convención: `Modules/<Modulo>/Tests/{Unit,Feature}`,
  `php artisan test --testsuite=Modules`). El módulo tiene **red de tests co-localizada**:
  `GestionProyectosTestCase` (fixtures: espacio + membresía + actividad, que es lo mínimo para
  ejercer el modelo de acceso), `Feature/GestionProyectosScopeTest` (invariantes anti-IDOR del
  punto crítico 1), `Unit/ProyectoServiceTransitionsTest` (puntos críticos 2, 3, 4 y 7) y
  `Feature/SubTareaCanalesTest` (reglas de tareas + consistencia web↔MCP del punto crítico 5).
  **Cubre SCRUM vía web, servicio y el MCP de tareas; NO cubre todavía API REST ni el resto de
  los ~46 tools MCP.**

---

## ⚠️ ADVERTENCIA — LECTURA OBLIGATORIA PARA CUALQUIER AGENTE DE IA

**Todo agente de IA que tome control de este módulo y haga cambios DEBE reescribir esta
documentación bajo los mismos principios y filosofía** (clara, natural, concisa; sin profundizar
en arquitectura ni estructura —el módulo es autoexplicativo—; señalando los puntos críticos del
modelo y la lógica de negocio). No la borres ni la dejes obsoleta: **reescríbela preservando los
conceptos clave de abajo**, que deben permanecer vigentes aunque el código cambie. Si un punto
crítico deja de aplicar, **explica por qué en este mismo archivo**. La seguridad del acceso
(IDOR), la integridad del flujo de aprobación y la consistencia entre los tres canales
(web / API / MCP) **dependen de que estos invariantes se mantengan**.

---

## Objetivo y flujo (resumen)

### SCRUM Project
1. Se crea un **espacio** (`GpProject`) y se le asignan **miembros** con un **rol** y/o **equipos**
   (globales; sincronizan miembros automáticamente).
2. Dentro del espacio se crean **actividades** (`Proyecto`), que pueden tener **subactividades**
   (mismo modelo, `-Sn`) y **tareas** (`GpSubTarea`, 1 nivel, sin aprobación).
3. La actividad avanza por el flujo **Pendiente → En Curso → En Revisión → Finalizado**
   (con desvíos: En Pausa, Reprogramado, Cancelado).
4. Pasar a un estado **crítico** (Finalizado / Reprogramado / Cancelado) exige **rol aprobador**
   **y evidencia** registrada en el historial. Finalizar exige además que los hijos estén cerrados.
5. **Reprogramar** crea una versión sucesora `-Rn` y deja el original en "Reprogramado".

**Datos SCRUM:** `gp_projects` (espacios) · `gp_proyectos` (actividades/subactividades/-Rn) ·
`gp_sub_tareas` (tareas) · `gp_space_members` (roles) · `gp_teams` + `gp_project_teams` (equipos) ·
`gp_custom_fields`/`_values` · `gp_labels` · `gp_audit_log` · `gp_activity_history`.

---

## 🔴 Puntos críticos del modelo / lógica de negocio

Son invariantes de seguridad y de correctitud. **No los rompas.**

1. **Autorización centralizada en `GpSpaceMember` (anti-IDOR).** TODO el control de acceso fino
   pasa por sus métodos estáticos: `canWrite`, `canApprove`, `canInlineEdit`, `canManage`,
   `canSeeProject`, `visibleProjectKeys`. La **Policy** y los **tres canales** (web, API, MCP)
   **delegan** aquí — **no reimplementes reglas de rol** en el controller/tool.
   - Las rutas web solo exigen `can:gestion-proyectos.ver` con un `{key}`/`{projectKey}`
     **adivinable**; la protección REAL por espacio está **dentro** del controller/servicio.
     **Todo endpoint o tool nuevo con un key adivinable DEBE llamar al gate correspondiente.**
     Esto **ya falló una vez**: `getActivityHistory`, `getTimeline`, `getTitleHistory` e
     `indexSubTareaHistorial` se quedaron sin gate y filtraban comentarios, evidencia y audit
     log de espacios ajenos a cualquiera con `.ver`. La causa es que `authorize('…ver')` +
     `firstOrFail()` **parece** una verificación pero solo prueba que la actividad EXISTA, no
     que sea tuya. Hoy los cuatro llaman a `canSeeProject` y `GestionProyectosScopeTest` lo
     fija como regresión. **Un endpoint de LECTURA necesita el gate tanto como uno de escritura.**
   - `roleInSpace()` **excluye membresías `suspended`**: una membresía suspendida conserva su rol
     pero **no concede acceso** (puerta cerrada reversible por equipo inactivo).
   - `admin` global (o `gestion-proyectos.admin`) es **bypass** en todos los métodos.

2. **Estados críticos = rol aprobador + evidencia específica.** Solo `canApprove` mueve a
   Finalizado / Reprogramado / Cancelado. La **evidencia debe ser de ESA transición**: la última
   entrada de `gp_activity_history` con `new_status` debe apuntar al estado destino. Un comentario
   viejo (p. ej. de una reprogramación previa) **no** habilita un Finalizado/Cancelado nuevo.

3. **Estados terminales son inmutables.** Una actividad Finalizada/Cancelada no cambia de estado
   ni de campos. **Única excepción por-espacio:** `produccion_editable_finalizado` permite corregir
   **solo** `fecha_aprobacion` ("Producción") en actividades **Finalizadas** (no Canceladas).

4. **Roll-up al finalizar.** No se puede Finalizar una actividad con subactividades/tareas aún
   **activas** (los hijos en estado terminal no bloquean).

5. **"El Aprobador solo cambia el estado" vive en UN sitio por entidad.** *(Este punto decía
   que la regla estaba triplicada en `ProyectoService::update`, el controller y el MCP
   `ActualizarTareaTool`; ya no aplica y se reescribió: la regla está centralizada y el
   `ActualizarTareaTool` nunca fue una copia —opera sobre TAREAS, otra entidad.)*
   - **Actividades** (`Proyecto`) → `ProyectoService::update`. El controller y el MCP
     `ActualizarActividadTool` **delegan**; no tienen copia.
   - **Tareas** (`GpSubTarea`) → el hook `GpSubTarea::updating`. Web y MCP delegan.
   **La regla rechaza con excepción; no filtra en silencio.** Un canal que descarte campos
   calladamente devuelve éxito y el llamador (sobre todo un cliente de IA) cree que guardó
   lo que no guardó.
   - ⚠️ **Lo que sí divergió fue el GATE DE ENTRADA, no la regla.** El web usaba
     `authorize('crear')` (= `canWrite`) para actualizar tareas, y `aprobador` está en
     `APPROVER_ROLES` pero **no** en `WRITE_ROLES`: el único rol que el hook nombra para
     finalizar no podía hacerlo por web, pero sí por MCP (que usa `registrarHistorial`).
     Hoy ambos usan `registrarHistorial`. **Actualizar una tarea es una acción de FLUJO
     (escritores ∪ aprobadores), no una edición de datos.** Al comparar canales, mirá el
     gate y la regla por separado: centralizar la regla no garantiza que la puerta coincida.

6. **Autogeneración de `key` concurrente.** El key se genera en `Proyecto::boot()` con
   `lockForUpdate` sobre la fila padre/espacio y `MAX(sufijo)` (no `COUNT`, para sobrevivir
   soft-deletes): `PROY-0001` (top-level), `-Sn` (subactividad), `-Rn` (reprogramación). **El
   llamador que crea actividades debe envolver en `DB::transaction`** o el lock no serializa.

7. **Exclusividad responsable equipo ↔ usuario.** Una actividad se asigna a un equipo **o** a un
   usuario, nunca a ambos: asignar uno limpia el otro. No se puede asignar un **equipo inactivo**.

8. **Equipos globales que sincronizan membresías.** Un equipo asignado a un espacio da de alta a
   sus miembros (`team_synced=true`). Desactivar el equipo **suspende** (no borra) esas membresías,
   preservando su rol; reactivarlo las restaura. Desasignarlo/eliminarlo las **borra**. Los
   miembros **manuales** y el **propietario** nunca se tocan (`syncMembersFromTeams`).

9. **Reprogramación (`-Rn`).** No se reprograma una `-Rn` ni una actividad terminal. El original
   pasa a "Reprogramado" y guarda el nuevo objetivo en `fecha_reprogramacion`; `fecha_limite` queda
   intacta como deadline original. **SLA y KPIs usan `COALESCE(fecha_reprogramacion, fecha_limite)`**
   / la última `-Rn` como deadline efectivo. El cierre es **manual**: no hay auto-cierre del root.

10. **Dos auditorías con propósito distinto.** `gp_audit_log` = **cambios de campo** (quién cambió
    qué, automático por observer). `gp_activity_history` = **comentarios + evidencia** de
    transiciones (inmutable, sin soft-delete). No mezcles su uso ni las trates como intercambiables.

11. **Cascade de borrado.** Borrar una actividad arrastra subactividades, tareas, versiones `-Rn` y
    campos personalizados. **Soft-delete** es reversible (los hijos vuelven con `restore`);
    **force-delete** además purga el historial y **sus adjuntos en disco**.

12. **Un solo tipo de espacio: `SCRUM_PROJECT`.** *(Este punto describía dos modalidades,
    SCRUM y Ticket Support; ya no aplica: Ticket Support se retiró junto con su módulo.)* La
    columna `space_type` se conserva por las migraciones históricas, y las validaciones de web y
    MCP solo aceptan `SCRUM_PROJECT`, así que no puede reaparecer un espacio huérfano.


---

## Comandos y despliegue

- `php artisan gp:mail:sla-check` (`SlaCheckCommand`) — avisa por correo de actividades por vencer.
- `php artisan` de correo: `SyncMailProvidersUsageCommand` (uso real de proveedores),
  `ResetMailProvidersQuotaCommand` y `ResetMailProvidersMonthlyQuotaCommand` (reinicio de cuotas).
  Requieren el **cron** del servidor (`* * * * * php artisan schedule:run`) para correr solos.
- **Panel de proveedores de correo** (solo admin): `/gestion-proyectos/mail/providers`.
- **Tokens de API/MCP:** cada usuario genera/revoca los suyos desde la web
  (`/gestion-proyectos/api-tokens`); autorizan por Bearer respetando roles y espacios.

**Despliegue:** subir archivos, `migrate` si hay migraciones nuevas, `config:cache`,
`npm run build` para assets, y mantener el cron de `schedule:run`. Verificar las credenciales de
**SendGrid** y **Resend** en el `.env`.

*— Módulo Gestión de Proyectos · Zync —*
