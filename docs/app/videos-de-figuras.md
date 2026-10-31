# Videos de las figuras en la app (descarga y modo sin conexión)

Guía para implementar en `cubania_app` el guardado de videos en el teléfono. El contrato exacto está en [`docs/api/v1/openapi.yaml`](../api/v1/openapi.yaml), con el tag `Figuras`, y hay respuestas de ejemplo en [`docs/api/v1/examples/`](../api/v1/examples/) que sirven de fixtures para los fakes de `test/support`.

## Qué cambia en el backend

- Cada figura puede tener **dos fuentes de video que conviven**:
  - YouTube: `video_url` y `youtube_video_id`, como hasta ahora. **No se elimina.**
  - Un MP4 propio en un bucket privado de Cloudflare R2: el bloque `video`.
- Los MP4 llegan ya comprimidos (H.264, lado corto de 720 px, AAC, faststart). El servidor no transcodifica nada.
- El bucket es privado: solo se accede con URLs firmadas que vencen. La app nunca recibe claves de R2.

## Endpoints

Todos usan `Authorization: Bearer <token de la app>` y `Accept: application/json`.

| Método y ruta | Para qué |
|---|---|
| `GET /api/v1/level-contents` | Lista las figuras del alumno con el estado de su MP4. No trae URLs. |
| `POST /api/v1/level-contents/{id}/download-url` | Devuelve una URL firmada del MP4. Body `{"purpose": "download"}` (30 min, `attachment`) o `{"purpose": "stream"}` (60 min, `inline`). |

**Qué figuras ve el alumno:** las mismas que puede repasar, es decir, todas las de su nivel actual y todas las de los niveles anteriores del mismo tipo de baile. Si ya no tiene una inscripción activa, la lista viene vacía y `download-url` responde 403.

**Errores de `download-url`:**

| Código | Qué significa |
|---|---|
| 403 | La figura no está en sus niveles, o el token no es de la app. |
| 404 | La figura no existe o no tiene MP4. |
| 429 | Más de 60 pedidos por minuto. |
| 503 | El almacenamiento no está disponible: reintentar más tarde. |

## Estados de cada figura en la pantalla

El estado sale de comparar la respuesta de `GET /level-contents` con el índice local de descargas.

| Estado | Condición |
|---|---|
| Sin video propio | `video.available == false` y no hay copia local. Si hay YouTube, ofrecer abrirlo. |
| No descargada | `video.available == true` y no hay copia local. |
| Descargando | Hay una tarea activa en el gestor de descargas. Mostrar el progreso con `size_bytes` como total. |
| Descargada | Hay copia local y `índice.version == video.version`. |
| Actualización disponible | Hay copia local y `índice.version != video.version`, con `video.available == true`. |
| Video quitado | Hay copia local y `video.available == false`. Borrar la copia local o marcarla como obsoleta. |

`video.version` es un entero que **solo sube**: cada vez que el profesor sube, reemplaza o quita el MP4. Nunca se reutiliza.

## Índice local de descargas

Guardar una entrada por figura descargada:

```text
level_content_id, version, path, size_bytes, downloaded_at
```

- `version` y `size_bytes` se toman de la respuesta de `download-url` usada para esa descarga, no de la lista.
- Validar al terminar: el tamaño del archivo tiene que ser igual a `size_bytes`. Si no coincide, descartar el archivo y volver a descargarlo.
- Al recibir la lista, recalcular los estados con la tabla de arriba.

## Descarga

1. Pedir `POST /level-contents/{id}/download-url` **justo antes** de empezar. No guardar la URL ni pedir muchas por adelantado.
2. Descargar la `url` con un gestor en segundo plano. Se sugiere `background_downloader`.
3. **No usar el cliente Dio de la API para R2.** Agrega `Authorization` y el header `Host` de Herd, y R2 rechazaría la firma. La petición a la URL firmada va sin headers propios.
4. R2 comprueba la firma al empezar cada petición. Si la descarga falla o se reanuda (petición con `Range`) después de `expires_at`, R2 responde **403**: pedir una URL nueva y reanudar con ella.
5. Guardar el archivo en una carpeta privada que el sistema no purgue:
   - Android y iOS: el directorio de soporte de la app (`getApplicationSupportDirectory()` de `path_provider`), por ejemplo `figure-videos/{level_content_id}-v{version}.mp4`.
   - iOS: excluir esa carpeta del backup de iCloud (`NSURLIsExcludedFromBackupKey`).
   - No usar caché ni almacenamiento temporal: el sistema los borra.
6. Al terminar, validar el tamaño, escribir la entrada en el índice y borrar la versión anterior si existía.

### Descargar un nivel completo

- Usar `meta.levels` para agrupar y `level_id` de cada figura para elegir las del nivel.
- Encolar solo las figuras con `video.available == true` que no estén ya descargadas en su versión actual.
- Pedir la URL de cada una cuando le toque, no todas al principio. Con 60 pedidos por minuto sobra para un nivel.

### Espacio usado y borrado

- Espacio usado: la suma de `size_bytes` del índice, en total y por nivel.
- Borrar por figura o por nivel: eliminar el archivo y su entrada del índice.

## Reproducción

En este orden:

1. Si hay copia local en la versión actual, se reproduce el archivo local con `video_player`.
2. Si `video.available == true`, hay conexión y no hay copia local, se pide `download-url` con `{"purpose": "stream"}` y se reproduce por streaming. Si la reproducción falla por URL vencida, se pide otra.
3. Si solo tiene YouTube (`youtube_video_id`), se abre el enlace.

Controles del reproductor: velocidad de 0.25× a 2× y reproducción en bucle.

## Paquetes sugeridos (Flutter)

| Paquete | Uso |
|---|---|
| `background_downloader` | Descargas en segundo plano con progreso, pausa y reanudación. |
| `path_provider` | Directorio de soporte de la app. |
| `video_player` | Reproducción local y por streaming. |

## Ejemplos

- [`level-contents.json`](../api/v1/examples/level-contents.json): respuesta de `GET /level-contents`.
- [`level-content-download-url.json`](../api/v1/examples/level-content-download-url.json): respuesta de `download-url`.

Un test del backend comprueba que estas respuestas tienen la misma estructura que las reales, así que se pueden usar como fixtures.
