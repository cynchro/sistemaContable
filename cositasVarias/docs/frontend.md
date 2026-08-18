# Frontend — stack y arquitectura

> Decisión de arquitectura de la interfaz web del sistema contable. El backend es una
> API REST (Modux, PHP) con JWT + RBAC + multi-tenancy ya resueltos; el frontend es una
> SPA que la consume.

## Stack

| Capa | Elección | Por qué |
|---|---|---|
| Framework | **React 18 + Vite + TypeScript** | Mayor ecosistema y soporte de LLMs; build rápido; tipado para los DTOs de la API. |
| UI / componentes | **CoreUI React** (sobre el *CoreUI Free React Admin Template*) | Admin template listo (sidebar/header/layout) basado en Bootstrap; componentes ricos para back-office. |
| Tablas | **CoreUI `CSmartTable`** | Paginación, filtros, búsqueda y orden integrados → calza con los listados paginados del backend. |
| Routing | **React Router** | Estándar; el admin template de CoreUI ya lo usa. |
| Datos / API | **TanStack Query** + cliente HTTP (axios/fetch) | Cache, reintentos y manejo de paginación contra la API. |
| Formularios | **React Hook Form + Zod** | Forms complejos (comprobantes con discriminaciones/percepciones) y validación que espeja las reglas del backend. |
| Auth | JWT en el cliente + `Authorization: Bearer` | El token ya transporta el `rol`; el RBAC se valida en el backend, el front solo muestra/oculta acciones. |
| Build / deploy | Vite build → estáticos por **nginx** (servicio Docker `frontend`) | Mismo patrón que el clientDashboard. |

> Nota: CoreUI tiene componentes **PRO** (de pago). Arrancamos con los **free**, que cubren
> el grueso (layout, formularios, tablas, modales, navegación). Si algún PRO se vuelve
> necesario, se evalúa puntualmente.

## Ubicación y estructura

Monorepo: el frontend vive en **`frontend/`** dentro de este repo (junto al backend PHP),
mismo patrón que el clientDashboard (`backend/` + `frontend/`).

```
frontend/
├── src/
│   ├── api/           # cliente HTTP + endpoints por módulo (tipados)
│   ├── auth/          # login, sesión, guard por rol (RBAC)
│   ├── components/    # componentes compartidos (sobre CoreUI)
│   ├── layout/        # layout del admin template (sidebar, header)
│   ├── modules/       # una carpeta por módulo, espejando el backend
│   │   ├── compartido/   # empresas, períodos, cuentas
│   │   ├── iva/          # comprobantes, libro IVA, DDJJ, exportaciones
│   │   ├── afip/         # factura electrónica, padrón
│   │   ├── sueldos/      # legajos, conceptos, liquidación, recibos
│   │   ├── gestion/      # vencimientos, tareas, honorarios
│   │   └── admin/        # usuarios, roles, permisos
│   ├── routes.tsx
│   └── main.tsx
├── index.html
├── vite.config.ts
├── Dockerfile         # build + nginx
└── nginx.conf
```

## Integración con la API

- **Base URL** configurable por env (`VITE_API_URL`); el backend ya expone CORS
  (`CorsMiddleware`).
- **Auth**: login → guarda `access_token` (y `refresh_token`); interceptor agrega
  `Authorization: Bearer`; en 401 intenta `refresh`, si falla redirige a login.
- **RBAC en el front**: el `rol` viaja en el JWT; el front decide qué menús/acciones
  mostrar, pero la autorización real la hace el backend (`PermissionMiddleware`). No es
  una barrera de seguridad, es UX.
- **Paginación**: las pantallas de listado consumen `page`/`per_page` + filtros y mapean
  la respuesta `{ total, cantidad_por_pagina, pagina, results }` a `CSmartTable`.
- **Tenancy**: transparente (el backend resuelve el tenant del usuario por el token).

## Orden de construcción sugerido

1. Scaffolding (Vite + React + TS + CoreUI template) y Docker/nginx.
2. Auth (login, sesión, guard por rol) + layout base.
3. Núcleo Compartido (empresas, períodos) — base para todo lo demás.
4. Módulo IVA (el más maduro en backend): comprobantes, libro IVA, DDJJ, descargas.
5. AFIP (factura electrónica, padrón).
6. Sueldos y Gestión del estudio.
7. Administración (usuarios, roles, permisos).
