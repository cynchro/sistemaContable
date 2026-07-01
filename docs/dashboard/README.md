# Tablero del cliente — sincronización de avances

Publica el avance del **Ecosistema Contable** en el tablero scrum del cliente
(app `clientDashboard`, repo `cynchro/clientDashboard`).

## Modelo

El tablero tiene 4 niveles: **Dashboard (project) → Stage → Feature → Task**.
El mapeo que usamos:

| Nivel del tablero | Qué representa acá                                  |
|-------------------|-----------------------------------------------------|
| Project           | El **cliente** ("Estudio Cynchro"). Un cliente puede tener varios sistemas. |
| Stage             | El **sistema/producto** ("Sistema Contable"). Mañana puede sumarse otro stage (ej. "Sistema de Ferretería"). |
| Feature           | Cada **módulo**: Infraestructura, Núcleo Compartido, IVA, Sueldos, Gestión del Estudio, AFIP. |
| Task              | Tarea concreta, con estado `pending` / `in_progress` / `completed`. |

La fuente de verdad es **`progreso.json`** en esta carpeta. Refleja lo que está
en el `CLAUDE.md` del proyecto.

## Cómo actualizar

1. Editás `progreso.json` (agregás/cambiás tasks, ajustás estados).
2. Corrés el sincronizador:

   ```bash
   docs/dashboard/sync.sh
   ```

   Variables opcionales: `BASE_URL` (default `http://localhost`), `ADMIN_EMAIL`,
   `ADMIN_PASS`.

El endpoint **`POST /api/import`** del dashboard es **idempotente**: hace *upsert*
por nombre (project/stage/feature) y por título (task) dentro de su padre. Es decir:
- Reenviar el mismo snapshot no duplica nada.
- Si una task cambia de estado, además queda registrado en el historial
  (`task_updates`).

Estados aceptados en el JSON (también se aceptan alias en español como
`pendiente` / `en_proceso` / `finalizado`):

- **Task**: `pending`, `in_progress`, `completed`
- **Feature**: `not_started`, `in_progress`, `paused`, `completed`

## Fecha de completado (`completed_at`)

Cada task puede llevar `"completed_at": "YYYY-MM-DD"` (opcional). El dashboard la
muestra en la tarjeta (columna Finalizado) y en el detalle. Reglas:

- Solo tiene sentido en tasks `completed`. Si la task no está completada, se ignora
  (y si estaba completada y pasa a otro estado, la fecha se limpia sola).
- El import la **backfillea**: aunque la task ya esté completada en el tablero, si el
  snapshot trae una `completed_at` distinta, la corrige (no hace falta cambiar el estado).
- Si una task se completa y no se pasa fecha, el server estampa la fecha del sync.
- Convención del proyecto: la fecha real sale del **historial de git** del commit que
  implementó la task (por eso las históricas se pudieron rellenar con su fecha real).

> ⚠️ **Requiere schema actualizado en el dashboard**: la columna `tasks.completed_at`
> se agrega con la migración `backend/database/migrations/2026-07-01_add_task_completed_at.sql`
> del repo `clientDashboard`. Correrla en la DB de producción **antes** de desplegar el
> código nuevo. Si se sincroniza `progreso.json` con `completed_at` contra un dashboard
> viejo (sin la columna), el campo simplemente se ignora (no rompe el import).

## Notas

- El cliente que ve el tablero se define en `progreso.json` → `project.client`
  (email/nombre) y el nombre del board en `project.name`. Si el usuario cliente no
  existe en el dashboard, el import lo crea con rol `client`.
  **Cambiá `project.name` y `project.client` por los datos reales del cliente.**
- Para agregar otro sistema del mismo cliente, sumá otro objeto al array `stages`
  (ej. `"Sistema de Ferretería"`) con sus features/tasks.
- El import lo dispara un usuario `admin` o `manager` del dashboard.
