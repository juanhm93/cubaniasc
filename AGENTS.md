<laravel-boost-guidelines>
=== .ai/big-picture rules ===

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

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
