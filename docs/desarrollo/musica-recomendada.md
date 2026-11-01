# Música recomendada desde el bucket

Referencia del feature. Las canciones del repaso dejaron de ser enlaces externos: ahora son archivos de una carpeta del bucket R2. El alumno las reproduce dentro de la plataforma y también puede descargarlas, en el repaso web y en la app. El contrato exacto está en [`docs/api/v1/openapi.yaml`](../api/v1/openapi.yaml), con el tag `Música`.

## Dónde están los archivos

- Carpeta `musica-salsa-casino` del mismo bucket privado que los videos. Se cambia con `CUBANIA_SONGS_FOLDER`.
- Formatos aceptados: MP3 y WAV.
- La carpeta es de la academia: los archivos se agregan a R2 por fuera. El panel no sube, mueve ni borra nada, y `SongAudioStorage` no tiene ningún método para hacerlo.
- Cada entorno usa su bucket (`cubania-videos` o `cubania-videos-staging`). Si la carpeta no existe en uno, la biblioteca sale vacía.

**Agregar una canción:** subir el archivo a la carpeta en Cloudflare y crearla desde **Música del repaso** en el panel.

## Modelo de datos

Tabla `recommended_songs`:

| Columna | Qué guarda |
|---|---|
| `audio_file_path` | Clave del objeto en el bucket. Nunca se envía al alumno. |
| `audio_file_size_bytes` | Tamaño leído del bucket al guardar. |
| `audio_file_duration_seconds` | La lee el navegador al escuchar el archivo en la biblioteca. Puede ser `null`. |
| `audio_file_version` | Sube cada vez que la canción cambia de archivo. La app la compara con su copia. |
| `audio_or_link_url` | El enlace de antes. Es opcional, ya no se usa y ninguna API lo devuelve. |

- **Un archivo es una canción.** Se valida al guardar, sin índice único: así, el archivo de una canción borrada se puede volver a usar.
- **Una canción puede estar en varios niveles.** Es la tabla `level_recommended_song`, que ya existía.
- **Una canción sin archivo nunca se recomienda.** El filtro está en `RecommendedSongRepository`.

### Canciones anteriores al bucket

Las que tenían enlace siguen en la tabla y salen en el panel con la insignia **Sin archivo**. Para volver a recomendarlas hay que editarlas y escoger un archivo. Mientras estén inactivas se pueden editar sin archivo.

`audio_or_link_url` se borra en una migración posterior, cuando no quede ninguna canción que lo necesite.

## Panel

Ruta `/admin/recommended-songs`, con la capacidad `content`.

1. **Añadir canción** y **Escoger del bucket** abren `SongLibraryDialog`: los archivos de la carpeta, con buscador que ignora mayúsculas y acentos.
2. Al elegir un archivo se puede escuchar. Los que ya son otra canción aparecen deshabilitados.
3. **Usar este archivo** prellena título y artista desde el nombre (`Artista - Título`, sin número de pista ni etiquetas como `(Official Video)`). Solo llena lo que esté vacío. Nombres como `01 - Track 1.mp3` no traen artista: lo escribe quien la crea.
4. Al guardar, `SaveRecommendedSong` comprueba que el archivo exista y que sus primeros bytes sean de audio. Si se conserva el mismo archivo, no consulta el bucket.

Rutas JSON, con sesión y la capacidad `content`:

| Ruta | Para qué |
|---|---|
| `GET /api/song-library` | Archivos de la carpeta y qué canción usa cada uno. |
| `GET /api/song-library/preview-url?key=` | URL de 5 minutos para escuchar antes de crear. |
| `GET /api/recommended-songs/{id}/stream-url` | URL de 60 minutos para reproducir. |
| `GET /api/recommended-songs/{id}/download` | Redirige a una URL `attachment` de 5 minutos. |

## Repaso web

El paso de canciones muestra un `SongPlayer` por canción, con reproducción y descarga.

- `POST /api/review/sessions/{session}/songs/{song}/audio-url`, con `{"purpose": "stream"}` o `{"purpose": "download"}`.
- Solo sirve para las canciones asignadas a esa sesión.
- Funciona aunque el tiempo de la sesión haya vencido, a diferencia del video de las figuras.
- El panel usa un token en `localStorage`, así que la descarga no puede ser un enlace directo: primero pide la URL y luego navega a ella. Como responde `attachment`, el navegador guarda el archivo y la página no cambia.

## Reproductor: `SongPlayer`

[`resources/js/components/song-player.tsx`](../../resources/js/components/song-player.tsx) es el único reproductor de música de la plataforma: lo usan la tabla del panel, la biblioteca, el formulario y el repaso. Debajo usa `useFileMediaPlayer`, el mismo hook del video de las figuras.

| Prop | Para qué |
|---|---|
| `title`, `artist` | Lo que se muestra. |
| `loadUrl` | Función que devuelve la URL firmada. Se vuelve a llamar si la URL vence. |
| `durationSeconds` | Duración conocida, hasta que el navegador lea la real. |
| `downloadHref` | Descarga como enlace normal (panel). |
| `onDownload` | Descarga que primero pide la URL (repaso). |
| `autoLoad` | Pide la URL al montar en vez de al primer play. |
| `compact` | Para filas: sin tarjeta, y la barra aparece al empezar. |
| `onDuration` | Avisa la duración real al leerla. |

Comportamiento:

- Reproducir es la acción principal. La descarga solo aparece si se pasa `downloadHref` u `onDownload`.
- Suena una sola canción a la vez en toda la página.
- Si la URL vence, el botón ofrece reintentar y retoma en el mismo punto.
- `autoLoad` existe por iOS, que puede bloquear un play que llega después de pedir la URL. Si pasa, la canción queda lista y el siguiente toque la reproduce: nunca suena en silencio. La tabla del panel no usa `autoLoad`, para no firmar una URL por fila.
- En el repaso, la clase `cubania-review__song-player` aplica los colores de Cubanía redefiniendo los tokens `--color-*` de Tailwind.

## Detalle importante: nombres con acentos

Las claves con acentos del bucket están en **Unicode NFD** (`e` + acento combinado). La clave debe viajar sin cambios: normalizada a NFC apuntaría a un objeto que no existe. Solo el texto que se muestra pasa por `readableFileName()`.

Por eso no conviene escribir a mano claves con acentos, por ejemplo en un seeder.

## Guía para `cubania_app`

Todos los endpoints usan `Authorization: Bearer <token de la app>` y `Accept: application/json`. Hay respuestas de ejemplo en [`songs.json`](../api/v1/examples/songs.json) y [`song-audio-url.json`](../api/v1/examples/song-audio-url.json). Un test comprueba que tienen la misma estructura que las reales, así que sirven de fixtures.

| Método y ruta | Para qué |
|---|---|
| `GET /api/v1/review/sessions/{session}/songs` | Las 3 canciones de la sesión. Cambió: ya no trae `audio_or_link_url`, trae `audio`. |
| `GET /api/v1/songs` | Biblioteca: canciones del nivel del alumno y de los anteriores. No trae URLs. |
| `POST /api/v1/songs/{song}/audio-url` | URL firmada. `stream` (por defecto, 60 min, `inline`) o `download` (30 min, `attachment`). |

Errores de `audio-url`:

| Código | Qué significa |
|---|---|
| 403 | La canción no está en sus niveles, no tiene inscripción activa, o el token no es de la app. |
| 404 | No existe, se borró, está inactiva o no tiene archivo. |
| 429 | Más de 60 pedidos por minuto. |
| 503 | El almacenamiento no está disponible: reintentar más tarde. |

### Reproducción

1. Si hay copia local en la versión actual, reproducir el archivo local.
2. Si no, y hay conexión, pedir `audio-url` con `stream` y reproducir por streaming. Si falla por URL vencida, pedir otra.
3. Sin copia y sin conexión: indicar que hace falta conexión.

### Descarga

1. Pedir `audio-url` con `download` **justo antes** de empezar.
2. Hacer la petición a la URL firmada **sin headers propios**. No usar el cliente Dio de la API: agregaría `Authorization` y R2 rechazaría la firma.
3. Si una descarga se reanuda después de `expires_at`, R2 responde 403: pedir otra URL.
4. Guardar en el directorio de soporte de la app, por ejemplo `songs/{id}-v{version}.mp3`, usando `audio.format` como extensión. En iOS, excluir esa carpeta del backup de iCloud. No usar caché ni almacenamiento temporal.
5. Validar que el tamaño coincida con `size_bytes`. Si no coincide, descartar el archivo y volver a descargarlo.

### Estados de cada canción

| Estado | Condición |
|---|---|
| No descargada | `audio.available == true` y no hay copia local. |
| Descargando | Hay una tarea activa. Usar `size_bytes` como total. |
| Descargada | Hay copia local y `índice.version == audio.version`. |
| Actualización disponible | Hay copia local y `índice.version != audio.version`. |
| Ya no disponible | Hay copia local y la canción dejó de venir en `GET /songs`. |

Índice local sugerido: `song_id, version, path, size_bytes, downloaded_at`. `version` y `size_bytes` se toman de la respuesta de `audio-url` usada para esa descarga.

## Archivos principales

| Qué | Dónde |
|---|---|
| Acceso al bucket | `app/Services/Content/SongAudioStorage.php`, `Concerns/InteractsWithMediaBucket.php` |
| Acciones | `app/Actions/Songs/` |
| Panel | `Admin/RecommendedSongController.php`, `Api/RecommendedSongAudioController.php` |
| Repaso y app | `Api/Review/ReviewSessionController.php`, `Api/V1/SongController.php` |
| Consultas | `app/Repositories/Review/RecommendedSongRepository.php` |
| Frontend | `components/song-player.tsx`, `components/content/song-library-dialog.tsx`, `hooks/use-file-media-player.ts`, `lib/song-file-name.ts` |
| Tests | `tests/Feature/SongLibraryTest.php`, `Admin/RecommendedSongManagementTest.php`, `Review/ReviewSongAudioUrlTest.php`, `Api/V1/SongApiTest.php` |

## Pendientes

- Borrar `audio_or_link_url` cuando producción no dependa de esa columna.
- Copiar la carpeta de música al bucket de staging si no existe.
- Convertir a MP3 `02 carita de pasaporte.wav`: pesa 51 MB y las demás rondan los 8 MB.
- Implementar en `cubania_app` las pantallas de música.
