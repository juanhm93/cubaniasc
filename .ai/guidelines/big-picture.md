# Big picture de Cubania SC

Monolito Laravel para una academia de salsa cubana. Sirve tres superficies sobre el mismo dominio: landing pública, panel interno del equipo y API v1 para la app del alumno.

## Superficies y entradas

| Superficie | Rutas | Consumidor |
|---|---|---|
| Landing | `/`, `/pre-inscripcion`, `/api/horarios`, `/repaso` | Visitantes y alumnos |
| Panel interno | `/dashboard`, `/contenido`, `/admin/*`, `/settings/*` | Equipo de la academia |
| API v1 | `/api/v1/*` | App Flutter (Sanctum, token con ability `app`) |

`routes/web.php` + `routes/settings.php` para Inertia; `routes/api.php` para JSON. Contrato de la app en `docs/api/v1/openapi.yaml`: actualízalo con cada cambio de endpoint o respuesta. Las APIs internas bajo `api.` usan sesión web, no son públicas.

## Arquitectura de una petición

- Web: `routes → middleware → FormRequest → Controller → Action/Service/Repository → Eloquent/R2 → Inertia::render()`.
- App: `routes/api.php → auth:sanctum + app.student → Controller → mismos Services/Repositories → Http/Resources/V1`.
- Los errores bajo `api/*` siempre se renderizan en JSON (`bootstrap/app.php`).
- `HandleInertiaRequests::share()` expone `auth.user`, `auth.abilities`, `canLogin`, `canRegister`, `cubania`, `notifications.unreadCount` y `sidebarOpen`.
- Cadena típica del panel: `auth → verified → active → ability:<capacidad>`, más `owner` en acciones críticas. Aliases en `bootstrap/app.php`: `active` (`RedirectIfUserPending`), `ability` (`EnsureAbility`), `owner` (`EnsureOwnerUser`), `admin` (`EnsureAdminRole`), `app.student` (`EnsureAppStudentToken`).
- Usuarios nuevos nacen `status = pending` (`CreateNewUser`) y un admin los activa en `/admin/users`; mientras tanto ven `/account/pending`.
- Las notificaciones guardan ids, no URLs: el frontend construye el enlace. `Api\NotificationController` resuelve la notificación por la relación del usuario, nunca por route binding, para no marcar notificaciones ajenas.

## Roles y capacidades

No hay paquete de permisos. El enum `App\Enums\PlatformAbility` define `content`, `payments`, `courses`, `oneTimeSessions`, `students`, `adminUsers`. El mapa rol → capacidad vive solo en `app/Support/RoleAccess.php` (y su `mapFor`):

- Owner y `admin`: todas.
- `admin_staff`: payments, courses, oneTimeSessions, students.
- `teacher`: content, courses, oneTimeSessions.
- `staff`: courses, oneTimeSessions.

Al añadir una capacidad, tocar también `AuthAbilities` en `resources/js/types`, `use-abilities.ts`, el menú de `app-sidebar.tsx`, el grupo de rutas y `tests/Unit/RoleAccessTest.php`. Autorización fina en Policies (`DanceType`, `Level`, `Student`, registradas en `AppServiceProvider`) y `authorize()` de los FormRequest.

## Capas del backend

- Controladores Inertia en `app/Http/Controllers` (`Admin/`, `Settings/`): `Inertia::render()` y redirects.
- Controladores JSON en `app/Http/Controllers/Api`: catálogo de contenido, notificaciones y biblioteca multimedia.
- API de la app en `Api/V1/` con Resources en `Http/Resources/V1/`. El alumno pide un código OTP por correo (`StudentLoginCodeService`, tabla `student_login_codes`, hasheado, 10 min, 5 intentos) y lo canjea por un token Sanctum `mobile-app:<dispositivo>`.
- Repaso: `/repaso` (React), `/api/review/*` (panel web) y `/api/v1/review/*` (app). Las dos APIs comparten `app/Services/Review`, `app/Actions/Review` y `app/Repositories/Review`; solo controladores y Resources están separados. En v1, `next_step` de `ReviewProgressResolver` indica el paso donde continuar.
- Repositorios (`app/Repositories`): concentran las consultas Eloquent del repaso; los services no consultan modelos directamente.
- Lógica extraída en `app/Actions` (`BuildPublicWeeklySchedule`, `ReorderSortOrder`, `CompleteReviewSession`) y `app/Support` (`RoleAccess`, `AuthAccess`, `CubaniaLanding`, `PreRegistrationRecipients`, `UniqueSlug`, `YouTubeVideo`). `CourseController` y `PaymentController` aún concentran lógica: al tocarlos, extrae Actions.
- Toda validación en FormRequest (`app/Http/Requests`). Enums en `app/Enums`.

## Modelo de dominio

Esquema principal en `database/migrations/2026_04_29_000000_create_academy_schema.php`; el de repaso en `2026_07_11_040726_create_review_panel_schema.php`. Casi todas las tablas usan SoftDeletes.

- **Catálogo**: `DanceType → Level → LevelContent` (figura con `video_url` de YouTube, `sort_order` y columnas de archivo propio). `sort_order` de niveles único por `dance_type_id`.
- **Cursos**: `Course` (nivel actual, `place_id`, profesor `user_id`, precio, `company_id`) con `CourseScheduleSlot` (weekday 1=lun…7=dom), `CourseLevel` (historial de niveles), `CourseSession → Attendance` y `CourseLevelContentProgress` (figuras cubiertas).
- **Alumnos y cobros**: `Student`, `Enrollment` (única por curso-alumno), `Payment` (método, referencia, comprobante en `public`, estado y vencimiento).
- **Sesiones únicas**: `OneTimeSession` (workshop, private_class, event) → `OneTimeSessionAttendee`, con pago propio.
- **Organización**: `Company`, `Place`, `Role`, `User`. `company_id` solo se valida en `CourseController::authorizeCourseCompany()`; el resto no filtra por empresa. `schedules` es legado: el horario real usa `course_schedule_slots`.
- **Entrada**: `PreRegistration` se convierte en `Student` (y opcionalmente `Enrollment`) en `PreRegistrationEnrollmentController`, en transacción.
- **Repaso**: `ReviewSession → ReviewSessionFigures / ReviewSessionSongs / ReviewQuizResponse`; `StudentFigureViews` (historial), `StudentStreak` (racha), `QuizItem → QuizOption`, `RecommendedSong ↔ Level` (pivot).

## Flujos críticos

1. **Preinscripción**: formulario público con throttle → `NewPreRegistrationNotification` con canales `database` antes que `mail` a `PreRegistrationRecipients::staff()` (cada envío en `try/catch` + `report()`: un fallo de SMTP nunca rompe el formulario) → admin convierte en alumno desde `/admin/pre-registrations/{id}/enroll`.
2. **Clase y progreso**: el profesor crea sesiones del curso, marca asistencia y figuras vistas; ese historial y el progreso del nivel alimentan la selección de figuras del repaso.
3. **Repaso (una sesión por día)**: identificación (web por email/cédula; app por OTP) → `StudentLevelResolver` toma el nivel activo y anteriores → `FigureSelectionService` ofrece 4 figuras (últimas vistas → progreso del curso → aleatorias), el alumno elige 2 → canciones del nivel → quiz cronometrado → `CompleteReviewSession` marca la sesión y actualiza la racha. `review_duration_seconds` por nivel; límite diario calculado en `cubania.timezone` (`America/Caracas`) aunque se guarde en UTC. El modal de bienvenida previo es parte del flujo web.
4. **Multimedia**: videos de figuras y música en un bucket privado R2 (`config/cubania.php`); el navegador sube con URL firmada y reproduce/descarga con URLs temporales. El servidor no transcodea. Los comprobantes de pago van al disco `public`.

## Frontend

- Entrada `resources/js/app.tsx`; layout por nombre de página: `welcome` y `pre-registration` sin layout, `auth/*` y `account/*` → `AuthLayout`, `settings/*` → `[AppLayout, SettingsLayout]`, resto → `AppLayout`.
- Páginas en `resources/js/pages`; primitivas en `components/ui` (shadcn/Radix), landing en `components/base/cubania`, listas ordenables en `components/content` e `items`, pasos del repaso en `components/review`.
- Rutas tipadas con Wayfinder: importar desde `@/routes` o `@/actions`, nunca escribir URLs a mano en formularios Inertia.
- JSON con `services/apiClient.js` (axios separado, Inertia v3 ya no lo trae); para código nuevo valorar `useHttp` de Inertia.
- UI en español con i18next (`resources/js/i18n/locales/es.json`); `tests/Unit/FrontendTranslationKeysTest.php` falla si falta una clave. Toasts: `Inertia::flash('toast', ...)` + `use-flash-toast`.

## Entornos y despliegue

- Local: Herd + SQLite (`https://cubaniasc.test`). CI y producción: MySQL 8. `composer run dev` levanta servidor, cola, Pail y Vite.
- `develop` → staging (`cubania.purphura.com`) y `main` → producción (`cubaniasc.com`) por FTP (`.github/workflows/deploy.yml`).
- Sin SSH: migraciones y limpieza de caché desde las rutas owner `admin.maintenance.migrate` y `admin.maintenance.cache-clear`. Por eso las migraciones de repaso son idempotentes (`hasTable`/`hasColumn`) y deben funcionar con `migrate --force` sin intervención.
- Producción no tiene worker de colas: notificaciones y correos van síncronos a propósito (no usar `ShouldQueue`). HTTPS forzado, `DB::prohibitDestructiveCommands()` y contraseñas fuertes (12+ caracteres, mixtas, no filtradas).
- Registro y enlace de login conmutables con `AUTH_REGISTRATION_ENABLED` y `AUTH_LOGIN_VISIBLE` (`AuthAccess`).

## Pendientes conocidos

- Conviven dos APIs de repaso (`Api/Review` y `Api/V1/Review`) y hay helpers Wayfinder huérfanos de `single-day-class` sin controlador ni ruta PHP.
- `deploy.yml` no corre tests y usa `composer install` sin `--no-dev`; `tests.yml` corre aparte y no bloquea el deploy.
- CI mezcla PHP 8.3 (deploy, lint) y 8.4 (tests); `lint.yml` usa `composer update`.
- `DashboardController` tiene cifras de staff fijas (`staffExample`); la separación por `company_id` está incompleta fuera de cursos.
- El backlog está en el tablero de Notion "cubania salsa casino app"; cuando pidan tareas, usar el skill `/tareas`.

## Verificación

`composer run dev`, `php artisan test --compact --filter=NombreDelTest`, `vendor/bin/pint --dirty --format agent`, `npm run lint`, `npm run format`, `npm run types:check`, `composer run ci:check`, `php artisan migrate:fresh --seed`.

PHPUnit 12: feature tests en `tests/Feature` (subcarpetas `Admin`, `Auth`, `Settings`, `Academy`) y unitarios en `tests/Unit`. Usar factories de `database/factories`: para roles, `Role::factory()->create(['slug' => 'admin'])` y `User::factory()->create(['role_id' => $role->id])`; la factory crea usuarios `active`, así que usa `['status' => 'pending']` para probar la espera de aprobación. Todo cambio de comportamiento lleva test: acceso por rol (permitido y 403), validación y caso feliz.
