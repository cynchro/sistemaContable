# Ingeniería inversa — sistemaCuarto (Synergys) → módulos Modux

> Fuente: app **Laravel 12** "Synergys"/Haddad (`/data/proyectos/cynchro/sistemaContable/sistemaCuarto`).
> CRM de contribuyentes + gestor de obligaciones fiscales + workflow de tareas + honorarios.
> Single-tenant en origen → se integra como un **tenant** del ecosistema.
> ⚠️ Las migraciones del repo están **incompletas vs producción**: el schema fiel de
> varias entidades sólo se obtiene de la DB de prod. Acá modelamos limpio para el ecosistema.
> Ver `ecosistema-unificacion.md` (regla: sin redundancia).

## 1. Unificación con la entidad canónica
- **`Persona` (contribuyente) = `empresas`** (entidad canónica de Compartido, ya usada por
  IVA y Sueldos). Vencimientos, tributos asignados, requerimientos, etc. referencian
  `empresa_id`. El "estudio" = `tenant`. Los `User` del estudio = usuarios del framework.
- Persona aporta campos CRM no presentes en `empresas` (email, contacto, tipo_persona,
  is_active...). Cuando se porte el CRM completo, se **extiende `empresas`** (o se agrega
  `empresa_contacto`) con esos campos — sin duplicar la identidad.

## 2. Módulos propuestos (de sistemaCuarto)
| Módulo Modux | Contenido (de sistemaCuarto) | Depende de |
|---|---|---|
| **Fiscal** (este hito) | Tributos (catálogo jerárquico) + Vencimientos (obligaciones con workflow de estados) + asignación tributo↔contribuyente | empresas (Compartido) |
| Requerimientos | Pedidos a clientes (`Requerimiento`) | empresas |
| Tareas | Workflow `Task*` (tipos, derivaciones, comentarios, historial, documentos, schedules) | usuarios |
| Honorarios | Honorarios del estudio | empresas |
| CRM contribuyente | Datos extendidos de `Persona` (socios, cuentas, tarjetas, documentación) | empresas |
| Integraciones | Ingesta API (`*ImportController`), BCRA, facilidades ARCA, DFE, sync | varios |
| (descartar / nativo) | Auth/roles (spatie→RBAC), chat, notificaciones, flota/ambulancias (ajeno), monitor desktop | — |

> Flota/ambulancias y RRHH del estudio son ajenos al dominio contable (heredados) → no se portan.

## 3. Workflow de estados del Vencimiento (de `Vencimiento`)
Estados (orden canónico, ingeniería inversa del RESUMEN y el modelo):
`creado → documentacion_recibida → documentacion_cargada → en_control → presentado`.
El cambio de estado registra usuario y observación. Se modela el estado como string
validado contra el conjunto conocido; las transiciones se permiten dentro de ese conjunto
(orden canónico documentado; reglas estrictas de transición = pendiente menor).

## 4. Esquema del módulo Fiscal (limpio, sin redundancia)
- `tributos`: catálogo por tenant, **jerárquico** (`parent_id`), con `nombre`,
  `tipo_persona`, `subcategoria`, `is_activo`.
- `empresa_tributo`: asignación N↔N tributo↔contribuyente (`empresa_id`, `tributo_id`,
  `fecha_desde`, `fecha_hasta`) — reemplaza `persona_tributo`.
- `vencimientos`: por `empresa_id` (contribuyente): `agencia`, `jurisdiccion`, `tributos`
  (json), `titulo`/`descripcion`, `fecha_vencimiento`, `estado`, `observaciones`,
  `usuario_creador_id`/`usuario_actualizador_id`, `is_activo`.

## 5. Riesgos / notas
- **Schema real de sistemaCuarto sólo desde la DB de prod** (migraciones incompletas) —
  para la futura **migración de datos** habrá que dumpear producción.
- **Dedup de contribuyentes** en la migración: el mismo CUIT existe en IVA, Sueldos y
  sistemaCuarto → matchear por CUIT contra `empresas`.
- Mucho de sistemaCuarto es **funcionalidad nueva** (no solapa con IVA/Sueldos): obligaciones,
  tareas, honorarios, requerimientos → se construye como módulos nuevos del ecosistema.

## 6. Fases
1. **(Ahora)** Módulo **Fiscal**: tributos + asignación + vencimientos con workflow.
2. Requerimientos.
3. Tareas (workflow) + honorarios.
4. CRM extendido del contribuyente (extiende `empresas`).
5. Integraciones / ingesta (requiere la operación real y la DB de prod).
