@AGENTS.md

# Cubania SC

Plataforma de gestión de una academia de salsa cubana. Monolito Laravel 13 + Inertia 3 + React 19 (TypeScript, Tailwind 4, shadcn/Radix). Dos caras:

- **Landing pública** (`/`, `/pre-inscripcion`, `/api/horarios`): video hero, horario semanal y formulario de preinscripción.
- **Panel interno** para staff: catálogo de figuras, cursos, asistencia, alumnos, matrículas, pagos, talleres, usuarios y notificaciones.

Las reglas generales de Laravel Boost (skills, `search-docs`, Pint, PHPUnit, Wayfinder) están en `AGENTS.md` y se importan arriba.

## Comandos

- `composer run dev`: servidor, cola, logs (pail) y Vite. El sitio lo sirve Herd en `https://cubaniasc.test`.
- `php artisan test --compact --filter=NombreDelTest`: tests puntuales.
- `vendor/bin/pint --dirty --format agent`: formato PHP después de cualquier cambio en `.php`.
- `npm run lint`, `npm run format`, `npm run types:check`: calidad del frontend.
- `composer run ci:check`: todo lo que valida CI.
- `php artisan migrate:fresh --seed`: roles, tipos de baile, empresa `cubania` y usuario admin.

## Arquitectura

### Petición autenticada

`routes/web.php` y `routes/settings.php` agrupan rutas con la cadena de middleware `auth → verified → active → ability:<capacidad>`, más `owner` para acciones críticas. Aliases en `bootstrap/app.php`:

| Alias | Clase | Efecto |
|---|---|---|
| `active` | `RedirectIfUserPending` | usuarios `pending` van a `/account/pending` |
| `ability` | `EnsureAbility` | 403 si el rol no tiene la capacidad |
| `owner` | `EnsureOwnerUser` | solo `users.is_owner = 1` |
| `admin` | `EnsureAdminRole` | solo rol admin |

`HandleInertiaRequests::share()` envía a todas las páginas: `auth.user` (con rol), `auth.abilities`, `canLogin`, `canRegister`, `cubania` (config de landing), `notifications.unreadCount` y `sidebarOpen`.

### Roles y capacidades

No hay paquete de permisos. El mapa rol → capacidad vive solo en `app/Support/RoleAccess.php`; las capacidades son el enum `App\Enums\PlatformAbility` (`content`, `payments`, `courses`, `oneTimeSessions`, `students`, `adminUsers`).

- Owner y `admin`: todas.
- `admin_staff`: payments, courses, oneTimeSessions, students.
- `teacher`: content, courses, oneTimeSessions.
- `staff`: courses, oneTimeSessions.

Para añadir una capacidad nueva hay que tocar: el enum, `RoleAccess` (incluido el shape de `mapFor`), el tipo `AuthAbilities` en `resources/js/types`, `use-abilities.ts`, el menú en `app-sidebar.tsx`, el grupo de rutas y `tests/Unit/RoleAccessTest.php`.

Autorización fina: Policies para `DanceType`, `Level` y `Student` (registradas en `AppServiceProvider`) y `authorize()` en FormRequests. Borrar niveles o tipos de baile requiere admin u owner.

Los usuarios registrados nacen con `status = 'pending'` (`CreateNewUser`) y un admin los activa desde `/admin/users`. El estado es un string (`pending`/`active`), no un enum.

### Capas del backend

- **Controladores Inertia** (`app/Http/Controllers`, `Admin/`, `Settings/`): devuelven `Inertia::render()` y redirects.
- **Controladores API** (`app/Http/Controllers/Api/`): JSON para el catálogo de contenido (dance types, levels, level contents, reordenamiento) y el centro de notificaciones. Son rutas web con sesión, bajo el prefijo `api.`; no es una API pública.
- **Validación**: siempre FormRequest en `app/Http/Requests`.
- **Lógica extraída**: `app/Actions` (`BuildPublicWeeklySchedule`, `ReorderSortOrder`) y `app/Support` (`RoleAccess`, `AuthAccess`, `PreRegistrationRecipients`, `CubaniaLanding`, `UniqueSlug`, `YouTubeVideo`). `CourseController` y `PaymentController` todavía concentran mucha lógica; al tocarlos, prefiere extraer Actions.
- **Enums**: `EnrollmentStatus`, `PaymentStatus`, `AttendanceStatus`, `PreRegistrationCountry`, `RoleSlug`, `PlatformAbility`.

### Modelo de dominio

El esquema está casi todo en `database/migrations/2026_04_29_000000_create_academy_schema.php`. Casi todas las tablas usan SoftDeletes.

- **Catálogo**: `DanceType → Level → LevelContent` (figura con `video_url` de YouTube). `sort_order` de niveles es único por `dance_type_id`.
- **Cursos**: `Course` (nivel actual, lugar, profesor `user_id`, precio, empresa) con `CourseScheduleSlot` (weekday 1=lun…7=dom), `CourseSession` → `Attendance`, `CourseLevel` (historial de niveles; se crea el primero en `Course::booted`) y `CourseLevelContentProgress` (figuras cubiertas del nivel actual).
- **Alumnos**: `Student`, `Enrollment` (única por curso-alumno), `Payment` (comprobante en el disco `public`, `payments/receipts`; método `efectivo` sin referencia ni comprobante).
- **Talleres**: `OneTimeSession` (workshop, private_class, event) → `OneTimeSessionAttendee` (alumno opcional, pago propio).
- **Organización**: `Company`, `Place`, `Role`, `User`.
- **Entrada**: `PreRegistration` se convierte en `Student` (y opcionalmente `Enrollment`) en `PreRegistrationEnrollmentController`, dentro de una transacción que borra la preinscripción.
- La tabla `schedules` es legado; el horario real usa `course_schedule_slots`.

`company_id` solo se valida en `CourseController::authorizeCourseCompany()`. El resto de módulos no filtra por empresa.

### Notificaciones y correo

- **Producción no tiene worker de colas.** Notificaciones y mails se envían de forma síncrona a propósito. No implementes `ShouldQueue` en ellos.
- `NewPreRegistrationNotification` usa los canales `['database', 'mail']` en ese orden, para que la campana funcione aunque falle el SMTP.
- `PreRegistrationController` notifica a cada destinatario (`PreRegistrationRecipients::staff()`: owner, admin, teacher, admin_staff activos) dentro de `try/catch` + `report()`. Un fallo de correo nunca rompe el formulario público.
- El payload en base de datos no guarda URLs; el frontend construye el enlace a partir del id.
- `Api\NotificationController` busca por la relación del usuario (no por route binding) para que nadie marque notificaciones ajenas.

### Frontend

- Entrada `resources/js/app.tsx`. El layout se elige por nombre de página: `welcome` y `pre-registration` sin layout, `auth/*` y `account/*` → `AuthLayout`, `settings/*` → `[AppLayout, SettingsLayout]`, resto → `AppLayout`. Una página nueva fuera de esos patrones hereda `AppLayout`.
- Páginas en `resources/js/pages`, primitivas en `components/ui` (shadcn), landing en `components/base/cubania`, listas ordenables en `components/content` e `items`.
- Rutas tipadas con Wayfinder: importa desde `@/routes` o `@/actions`, que se regeneran solos con Vite. No escribas URLs a mano en formularios Inertia.
- Llamadas JSON: `services/apiClient.js` (axios instalado aparte, porque Inertia v3 ya no lo trae), usado por `levelService.js` y `notificationService.ts`. Para código nuevo, valora `useHttp` de Inertia.
- **Textos de UI en español** vía i18next: `resources/js/i18n/locales/es.json` y `useTranslation`. `tests/Unit/FrontendTranslationKeysTest.php` falla si usas una clave que no existe en `es.json`.
- Toasts: el backend hace `Inertia::flash('toast', ['type' => ..., 'message' => ...])` y `use-flash-toast` los muestra.
- Config de la landing (redes, video hero, profesores, slider) en `config/cubania.php` con variables `CUBANIA_*`; se expone en `CubaniaLanding::shared()`.

## Entornos y despliegue

- Local: Herd + SQLite. CI y producción: MySQL 8.
- `develop` se despliega a staging (`cubania.purphura.com`) y `main` a producción (`cubaniasc.com`), subiendo archivos por FTP con `.github/workflows/deploy.yml`. Configuración descrita en `config/deploy.php` y `App\Services\DeploymentTarget`.
- No hay SSH en el hosting. Las migraciones y `optimize:clear` en el servidor se ejecutan desde las rutas owner `admin.maintenance.migrate` y `admin.maintenance.cache-clear`. Las migraciones deben ser seguras con `migrate --force` y sin intervención manual.
- En producción: HTTPS forzado, `DB::prohibitDestructiveCommands()` y contraseñas fuertes (12+ caracteres, mixtas, no filtradas).
- Registro y enlace de login conmutables con `AUTH_REGISTRATION_ENABLED` y `AUTH_LOGIN_VISIBLE` (`AuthAccess`).

## Pendientes conocidos

- `deploy.yml` no ejecuta tests y hace `composer install` sin `--no-dev`. `tests.yml` corre por separado y no bloquea el deploy.
- CI mezcla PHP 8.3 (deploy, lint) y 8.4 (tests). `lint.yml` usa `composer update`.
- `DashboardController` tiene cifras de staff fijas (`staffExample`).
- La separación por empresa (`company_id`) está incompleta fuera de cursos.

## Tests

- PHPUnit 12, feature tests en `tests/Feature` (subcarpetas `Admin`, `Auth`, `Settings`, `Academy`), unitarios en `tests/Unit`.
- Usa las factories de `database/factories`. Para roles, el patrón es `Role::factory()->create(['slug' => 'admin'])` y `User::factory()->create(['role_id' => $role->id])` (la factory ya crea usuarios `active`; usa `'status' => 'pending'` para probar la espera de aprobación).
- Cada cambio de comportamiento lleva test: acceso por rol (permitido y 403), validación y caso feliz.
