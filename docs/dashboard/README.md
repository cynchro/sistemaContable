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

## Notas

- El cliente que ve el tablero se define en `progreso.json` → `project.client`
  (email/nombre) y el nombre del board en `project.name`. Si el usuario cliente no
  existe en el dashboard, el import lo crea con rol `client`.
  **Cambiá `project.name` y `project.client` por los datos reales del cliente.**
- Para agregar otro sistema del mismo cliente, sumá otro objeto al array `stages`
  (ej. `"Sistema de Ferretería"`) con sus features/tasks.
- El import lo dispara un usuario `admin` o `manager` del dashboard.
