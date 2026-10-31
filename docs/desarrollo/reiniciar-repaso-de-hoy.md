# Reiniciar el repaso de hoy en local

Cada alumno puede hacer un solo repaso al día. Para volver a probarlo sin esperar a mañana, usa `review:reset-today`, que deja a los alumnos como si no hubieran empezado el repaso de hoy. Solo funciona en los entornos `local` y `testing`.

## Uso

```bash
# Todos los alumnos
php artisan review:reset-today

# Un solo alumno (ID de la tabla students)
php artisan review:reset-today --student=5
```

El comando informa cuántos registros borró:

```text
Sesiones de repaso borradas: 1. Vistas de figuras borradas: 0.
```

Después recarga `/repaso`. El panel lee el estado al abrirse, así que una pestaña abierta seguirá mostrando el bloqueo o una sesión que ya no existe.

Si la app Flutter apunta a tu backend local, el alumno también queda desbloqueado ahí, porque usa las mismas tablas.

## Cómo funciona el bloqueo diario

`ReviewSessionService::hasStartedToday()` busca en `review_sessions` una fila del alumno con `started_at` dentro del día de hoy. Si la encuentra, el repaso queda bloqueado hasta el día siguiente.

El bloqueo no depende de cookies ni de localStorage. En localStorage solo está el token de acceso (`cubania_review_auth`), así que borrarlo no desbloquea nada.

"Hoy" es el día local de la academia (`cubania.timezone`, `America/Caracas` por defecto), no el de UTC. El comando calcula el día con el mismo método que el bloqueo, `ReviewSessionService::localDayBounds()`, así que también funciona después de las 8 p. m. en Caracas, cuando en UTC ya es el día siguiente.

## Qué borra

| Tabla | Qué se borra |
|---|---|
| `review_sessions` | Las sesiones que empezaron hoy, estén terminadas o en curso. |
| `review_session_figures`, `review_session_songs`, `review_quiz_responses` | Lo asociado a esas sesiones, en cascada. |
| `student_figure_views` | Las vistas de figuras de hoy. Las figuras vistas hace poco salen primero en las opciones, así que si quedaran, el segundo intento no se parecería a un repaso nuevo. |

Los registros de días anteriores se conservan. El comando no pide confirmación y lo borrado no se puede recuperar.

## Qué pasa con la racha

El comando no toca `student_streaks`, y eso tiene tres efectos:

- `current_streak` y `last_review_at` conservan sus valores.
- Terminar otro repaso el mismo día no suma, porque `last_review_at` ya es hoy.
- `StreakService::recentDays()` marca hoy como no completado hasta que termines otro repaso, porque lee las sesiones borradas.

Para probar que la racha crece a mano, cambia `last_review_at` a ayer en la base local antes de terminar el repaso.

## Casos especiales

| Situación | Resultado |
|---|---|
| `APP_ENV` no es `local` ni `testing` | Muestra `Este comando solo se puede usar en local.`, termina con código 1 y no borra nada. Si te pasa en tu máquina, revisa `APP_ENV` en `.env`. |
| `--student` con un ID que no existe o sin repasos hoy | Informa 0 borrados y termina con código 0. |
| Sin registros de hoy | Informa 0 borrados y termina con código 0. |

## Código y tests

- Comando: [`app/Console/Commands/ResetTodayReviews.php`](../../app/Console/Commands/ResetTodayReviews.php)
- Borrado de sesiones: `deleteStartedBetween()` en [`ReviewSessionRepository`](../../app/Repositories/Review/ReviewSessionRepository.php)
- Borrado de vistas: `deleteViewsBetween()` en [`ReviewFigureRepository`](../../app/Repositories/Review/ReviewFigureRepository.php)
- Tests: [`tests/Feature/Review/ResetTodayReviewsCommandTest.php`](../../tests/Feature/Review/ResetTodayReviewsCommandTest.php)

```bash
php artisan test --compact tests/Feature/Review/ResetTodayReviewsCommandTest.php
```
