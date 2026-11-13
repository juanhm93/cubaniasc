---
name: tareas
description: Muestra las tareas del tablero de Notion "cubania salsa casino app" agrupadas por estado y, cuando el usuario elige una, lee su detalle para planificar e implementarla. Úsalo cuando el usuario pida ver las tareas, el backlog, el tablero o qué hay pendiente por hacer.
argument-hint: "[cubaniasc | app | todas]"
---

# Tareas del tablero de Notion

El backlog de Cubania vive en la base de datos de Notion **"cubania salsa casino app"**, que replica el GitHub Project `juanhm93/projects/3`.

- Tablero: https://app.notion.com/p/d33b07d3bada49c0a5085ff4bfa87a89?v=41635d32877781ae89ed000c24226ec2
- Vista "Tablero" (board por Estado, ordenada por Prioridad): `view://41635d32-8777-81ae-89ed-000c24226ec2`
- Data source: `collection://7b3ef551-b7b0-4f2d-bb8e-8452c66e683c`

Propiedades: `Tarea` (título), `Estado` (Backlog, Ready, In progress, In review, Done), `Prioridad` (P0, P1, P2), `Tamaño` (XS–XL), `Estimado`, `Repo` (`cubaniasc` = esta web, `cubania_app` = app Flutter), `Issue`, `PR vinculado`, `Asignado (GitHub)`, `Inicio`, `Fecha objetivo`.

## 1. Listar

1. Carga las herramientas de Notion con `ToolSearch` (`select:mcp__claude_ai_Notion__notion-query-data-sources,mcp__claude_ai_Notion__notion-fetch`).
2. Consulta la vista en modo `view`: `{"data": {"mode": "view", "view_url": "view://41635d32-8777-81ae-89ed-000c24226ec2", "page_size": 100}}`. Si `has_more` es true, sigue con `start_cursor`.
3. Filtra según `$ARGUMENTS`:
   - vacío o `cubaniasc`: tareas con `Repo = cubaniasc` o sin repo.
   - `app`: tareas con `Repo = cubania_app`.
   - `todas`: sin filtro.
4. Presenta las tareas agrupadas en este orden: **In progress**, **In review**, **Ready**, **Backlog**. Dentro de cada grupo, ordena por prioridad (P0, P1, P2 y luego sin prioridad). Numera las tareas de forma continua para que el usuario pueda elegir por número. En cada línea, muestra la prioridad, el tamaño/estimado y el issue si existen. De las `Done`, muestra solo el total.
5. Si ves en el código que alguna tarea ya parece resuelta, indícalo en una línea.
6. Termina preguntando cuál quiere implementar.

## 2. Al elegir una tarea

1. Lee la tarjeta con `notion-fetch` (su `url`) para obtener la descripción y los criterios. Si tiene `Issue`, léelo también con `gh issue view <url>`.
2. Si la tarjeta no tiene descripción o es ambigua, haz las preguntas que hagan falta antes de planificar.
3. Explora el código relevante y propón un plan breve siguiendo CLAUDE.md: tests por rol, FormRequest, textos en `es.json`, Wayfinder, etc.
4. Antes de cambiar el estado de la tarjeta en Notion, pide confirmación. El tablero es compartido y es espejo de GitHub.
   - Al empezar a implementar: `Estado = In progress` e `Inicio = hoy`.
   - Al abrir el PR: `Estado = In review` y `PR vinculado = <url del PR>`.
   - Para escribir, usa `notion-update-page` (cárgalo con `ToolSearch`).
