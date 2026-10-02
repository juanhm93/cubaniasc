# Planificación de nuevas ideas — Cubanía

> Documento vivo de brainstorming. Base ya construida (no se toca): gestión de cursos, cursos especiales, pagos/cuentas, preinscripciones y levels. Stack actual: Laravel + React (web). Meta a mediano plazo: exponer todo como API para consumir desde web y desde la futura app en Flutter.

---

## Ideas principales (objetivos más grandes)

### Idea 1: Panel interactivo de repaso para alumnos

**Descripción**

Sección web accesible sin login tradicional: el alumno se identifica con su correo o cédula (dato que ya existe en preinscripciones), y desbloquea un panel de sesión corta (3–5 minutos, con contador visible) tipo juego de repaso. Muestra 2 figuras para repasar y recomienda 3 canciones, filtrado según el level del alumno. Pensado como "anzuelo" de bajo compromiso, no reemplaza la futura app de Flutter.

Ideas de mecánica a futuro: racha de repaso (streaks) tipo "llevas 3 clases repasando", sin tabla de posiciones pública para no desmotivar a los que van más lento.

**Modelo de datos**

```
review_sessions
- id
- student_id (FK -> students)
- started_at
- expires_at
- level_id (FK -> levels)
- completed (boolean)

review_figures
- id
- name
- description
- video_url (opcional, clip de referencia)
- level_id (FK -> levels)

review_session_figures
- id
- review_session_id (FK -> review_sessions)
- figure_id (FK -> review_figures)

recommended_songs
- id
- title
- artist
- audio_or_link_url
- level_id (FK -> levels, opcional si aplica a varios)

review_session_songs
- id
- review_session_id (FK -> review_sessions)
- song_id (FK -> recommended_songs)

student_streaks
- id
- student_id (FK -> students)
- current_streak
- last_review_at
```

---

### Idea 2: Reproductor de video propio (multi-ángulo)

**Descripción**

Reproductor propio construido con el tiempo (no una librería de terceros pesada), inspirado en herramientas de análisis de video deportivo tipo Dartfish/OnForm/Coach's Eye. Empezar con lo mínimo que da más valor:

- Reproducción lado a lado de 2 videos (ej. mismo paso, dos ángulos, o "hoy vs hace 6 meses").
- Control de velocidad (0.25x, 0.5x, normal).
- Marcadores de tiempo con comentario adjunto (se conecta directo con la Idea 3, el feed de revisión).

Más adelante: anotaciones dibujadas sobre el video congelado, narración de voz superpuesta.

**Modelo de datos**

```
video_clips
- id
- owner_type (student | teacher | class)
- owner_id
- source_url (ej. link de YouTube o storage propio)
- duration_seconds
- created_at

video_markers
- id
- video_clip_id (FK -> video_clips)
- timestamp_seconds
- comment
- created_by (FK -> users, alumno o profesor)
- created_at

video_comparisons
- id
- title
- video_clip_a_id (FK -> video_clips)
- video_clip_b_id (FK -> video_clips)
- created_by (FK -> users)
- created_at
```

---

### Idea 3: Feed de revisión de videos (historial + feedback)

**Descripción**

El alumno se graba (ej. en YouTube, video no listado) y pasa el link. Se crea un feed cronológico (más reciente arriba) tipo Patreon pero de sus propios videos. Cumple doble función: es su historial de baile y al mismo tiempo el canal de revisión, donde los profesores dejan comentarios/correcciones. Al usar links externos (YouTube) se evita el costo de hosting propio de video en el MVP.

Se integra con la Idea 2: los comentarios de un profesor pueden apuntar a un `timestamp_seconds` exacto del video usando `video_markers`.

**Modelo de datos**

```
student_video_feed
- id
- student_id (FK -> students)
- video_clip_id (FK -> video_clips)  -- reusa la tabla de la Idea 2
- class_id / level_id (FK, opcional, para contexto)
- posted_at

feedback_entries
- id
- student_video_feed_id (FK -> student_video_feed)
- teacher_id (FK -> teachers/users)
- comment
- video_marker_id (FK -> video_markers, opcional, si el comentario apunta a un momento exacto)
- created_at

feedback_status
- id
- feedback_entries_id (FK -> feedback_entries)
- status (pendiente | revisado | resuelto)
- updated_at
```

---

## Ideas secundarias que dan valor

### Idea 1: Tarjeta de progreso compartible (#Cubanízate)

**Descripción**

Módulo simple en el panel del alumno que genera una tarjeta visual compartible (nivel alcanzado, racha de repaso, insignia) para que el alumno la suba a Instagram con el hashtag de marca. Aprovecha el ADN de marketing orgánico que ya tiene la academia, sin costo de pauta.

**Modelo de datos**

```
shareable_cards
- id
- student_id (FK -> students)
- type (nivel_alcanzado | racha | insignia)
- image_url (generada, ej. vía plantilla + datos)
- generated_at
```

---

### Idea 2: Recordatorios y pagos vía WhatsApp

**Descripción**

Automatizar recordatorios de pago o de clase por WhatsApp (canal fuerte de contacto de la academia), en vez de depender solo de email.

**Modelo de datos**

```
whatsapp_notifications
- id
- student_id (FK -> students)
- type (recordatorio_pago | recordatorio_clase | otro)
- message
- phone_number
- status (pendiente | enviado | fallido)
- scheduled_at
- sent_at
```

---

### Idea 3: Modelo de datos preparado para multi-sede

**Descripción**

Aunque hoy operan en una sola sede (Ministerio de Cultura), dejar el modelo de datos listo para múltiples ubicaciones evita una migración dolorosa si la academia crece "por todo el territorio", como dice su visión.

**Modelo de datos**

```
locations
- id
- name
- address
- active (boolean)

-- agregar location_id (FK -> locations) a:
-- classes, schedules, teachers_locations (tabla pivote si un profesor da clases en varias sedes)
```

---

### Idea 4: Dashboard de retención / asistencia

**Descripción**

Panel de alertas simple sobre asistencia (ej. "alumno lleva 2 semanas sin venir"), reutilizando los datos que ya existen de preinscripción y clases. Barato de construir, alto valor para retención del negocio.

**Modelo de datos**

```
attendance_records
- id
- student_id (FK -> students)
- class_id (FK -> classes)
- date
- attended (boolean)

retention_alerts
- id
- student_id (FK -> students)
- alert_type (ausencia_prolongada | pago_vencido | otro)
- triggered_at
- resolved (boolean)
```

---

## Notas abiertas / futuro

- **Inteligencia artificial**: sin definir aún. Candidato natural cuando llegue el momento: apoyar el feed de revisión (Idea 3) detectando automáticamente tiempos/compases de la música para sugerir marcadores, en vez de que el profesor los busque a mano.
- **Migración a API**: todo lo anterior debe pensarse API-first para que tanto la web (React) como la futura app en Flutter consuman los mismos endpoints.
