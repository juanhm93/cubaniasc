# Cubania SC

> *Este es el primer proyecto más importante de mi vida.*

Plataforma web de gestión para **Cubania SC**, una academia de salsa cubana. Reúne en un solo lugar la cara pública de la academia y el panel interno con el que el equipo organiza cursos, alumnos, asistencia y pagos.

## ¿Qué hace?

### Landing pública

- Video hero, redes sociales, profesores y testimonios.
- Horario semanal de clases generado a partir de los cursos activos.
- Formulario de preinscripción que avisa al equipo por notificación interna y correo.

### Panel interno

- **Catálogo de figuras**: tipos de baile, niveles y figuras con video de YouTube, ordenables.
- **Cursos**: profesor, lugar, horario, precio, historial de niveles y progreso de figuras cubiertas.
- **Asistencia** por sesión de curso.
- **Alumnos y matrículas**, incluida la conversión de preinscripciones en alumnos.
- **Pagos** con método, referencia y comprobante.
- **Talleres, clases privadas y eventos** con sus propios asistentes y pagos.
- **Usuarios y roles**: los nuevos registros esperan la aprobación de un administrador.
- **Centro de notificaciones**.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3, Laravel 13, Fortify, Sanctum |
| Frontend | Inertia 3, React 19, TypeScript, Tailwind CSS 4, shadcn/Radix |
| Rutas tipadas | Laravel Wayfinder |
| Traducciones | i18next (UI en español) |
| Base de datos | SQLite en local, MySQL 8 en CI y producción |
| Tests | PHPUnit 12 |
| Calidad | Pint, ESLint, Prettier, `tsc` |

## Roles y permisos

El acceso se controla con capacidades definidas en `app/Support/RoleAccess.php` y el enum `App\Enums\PlatformAbility`.

| Rol | Capacidades |
|---|---|
| Owner / `admin` | Todas |
| `admin_staff` | Pagos, cursos, talleres, alumnos |
| `teacher` | Contenido, cursos, talleres |
| `staff` | Cursos, talleres |

## Puesta en marcha

Requisitos: PHP 8.3+, Composer, Node.js y npm. En local el sitio lo sirve [Laravel Herd](https://herd.laravel.com) en `https://cubaniasc.test`.

```bash
composer run setup              # instala dependencias, crea .env, genera la key, migra y compila
php artisan migrate:fresh --seed # roles, tipos de baile, empresa y usuario admin
composer run dev                # servidor, cola, logs y Vite
```

### Variables de entorno destacadas

| Variable | Uso |
|---|---|
| `AUTH_REGISTRATION_ENABLED` | Activa o desactiva el registro |
| `AUTH_LOGIN_VISIBLE` | Muestra u oculta el enlace de login en la landing |
| `CUBANIA_*` | Redes sociales, zona horaria y video hero de la landing (`config/cubania.php`) |

## Comandos útiles

```bash
php artisan test --compact                 # tests
vendor/bin/pint --dirty --format agent     # formato PHP
npm run lint                               # ESLint
npm run format                             # Prettier
npm run types:check                        # TypeScript
composer run ci:check                      # todo lo que valida CI
```

## Estructura

```
app/
├── Actions/            Lógica de negocio extraída
├── Enums/              Estados, roles y capacidades
├── Http/Controllers/   Controladores Inertia, Admin, Settings y Api
├── Http/Requests/      Validación con FormRequest
├── Models/
├── Notifications/
└── Support/            RoleAccess, CubaniaLanding, utilidades
resources/js/
├── pages/              Páginas Inertia
├── components/         UI (shadcn), landing y listas ordenables
└── i18n/locales/       Traducciones
tests/
├── Feature/
└── Unit/
```

## Despliegue

- `develop` se despliega a staging y `main` a producción mediante GitHub Actions (`.github/workflows/deploy.yml`) por FTP.
- El hosting no tiene SSH: las migraciones y la limpieza de caché se lanzan desde rutas de mantenimiento reservadas al owner.
- Las notificaciones y correos se envían de forma síncrona porque producción no tiene worker de colas.

## Licencia

MIT
