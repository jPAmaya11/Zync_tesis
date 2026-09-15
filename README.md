# Zync · Gestión de Proyectos

<div align="center">

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)

**Sistema de gestión de proyectos y tareas — Laravel 12 + Vue.js 3 + Inertia.js**

</div>

---

## Qué es

Aplicación de gestión de proyectos estilo SCRUM: espacios de trabajo, actividades,
subactividades, equipos, campos personalizados, historial de cambios y notificaciones
por correo. Incluye la gestión de usuarios, roles y permisos que el módulo necesita
para funcionar.

## Módulos

```
GestionProyectos  → Espacios, actividades, subtareas, equipos, SLA y correo
User              → Usuarios y perfil propio
Role              → Roles y permisos (spatie/laravel-permission)
```

## Stack

**Backend**
• Laravel 12 + PHP 8.2
• MySQL 8.0 / MariaDB 10.4
• Colas y tareas programadas sobre base de datos
• `laravel/mcp` — servidor MCP del módulo de proyectos

**Frontend**
• Vue.js 3 + Inertia.js
• Tailwind CSS + Vite

## Instalación (sin Docker)

### Requisitos

```
PHP 8.2+  |  Composer  |  Node.js 18+  |  MySQL 8.0+ o MariaDB 10.4+
```

### Pasos

```bash
# 1. Dependencias
composer install
npm install

# 2. Entorno
cp env-sample .env
php artisan key:generate

# 3. Base de datos (crear la BD vacía y configurar credenciales en .env)
#    DB_DATABASE=zync  DB_USERNAME=root  DB_PASSWORD=
php artisan migrate --seed

# 4. Enlace de almacenamiento público (avatares, adjuntos)
php artisan storage:link

# 5. Compilar assets
npm run build
```

### Levantar en desarrollo

Dos terminales:

```bash
# Terminal 1 — backend
php artisan serve          # http://127.0.0.1:8000

# Terminal 2 — frontend con recarga en caliente
npm run dev                # http://localhost:5173
```

O todo junto:

```bash
composer run dev           # servidor + colas + logs + vite
```

### Usuario inicial

El seeder crea un administrador:

```
admin@zync.test  /  password
```

Cámbialo antes de exponer la aplicación.

## Roles y permisos

```
admin        → acceso total
colaborador  → entra al módulo y participa en espacios (sin administrar)
```

```
Roles              → ver, crear, editar, eliminar
Usuarios           → ver, crear, editar, eliminar
Gestion Proyectos  → ver, miembro, admin
```

## Tareas programadas

Se declaran en `routes/console.php` (en Laravel 12 el `schedule()` del Kernel no se
ejecuta porque `bootstrap/app.php` usa `withKernels()`). Requieren un cron del sistema
apuntando a `php artisan schedule:run` cada minuto.

```
gp:oauth-gc                 04:00        Limpia códigos y sesiones OAuth del MCP
gp:mail:reset-quota         00:00        Reset diario de cuotas de correo
gp:mail:reset-monthly-quota día 1        Reset mensual de cuotas
gp:mail:sync-usage          cada hora    Sincroniza uso contra la API del proveedor
gp:mail:sla-check           cada 30 min  Alerta de vencimientos próximos
```

## Comandos útiles

```bash
# Limpiar cachés
php artisan config:clear && php artisan cache:clear && php artisan route:clear

# Reconstruir la base de datos desde cero
php artisan migrate:fresh --seed

# Tests
php artisan test
```

## Producción

```bash
composer install --no-dev --optimize-autoloader
npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan migrate --force
```

---

## Licencia

Proyecto privado. Todos los derechos reservados.
