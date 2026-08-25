# Ecosistema Contable

Sistema contable web multi-tenant para estudios contables (IVA, Sueldos, gestión del estudio),
hecho a medida para el Estudio Haddad. Backend en PHP propio (framework **Modux**) + frontend en
React, pensado para reemplazar Visual IVA (legacy) y unificar la carga de datos por contribuyente.

## Qué hay

```
ecosistema/
├── backend/     API PHP (Modux) — IVA, Sueldos, Fiscal, Tareas, Honorarios, AFIP/ARCA, Admin
├── frontend/    SPA React + Vite + TypeScript + CoreUI
├── extractor/   Bot Playwright que automatiza el Portal IVA de ARCA — repo propio, separado
├── cositasVarias/  Documentación, análisis y notas de trabajo (no es código de la app)
└── docker-compose.yml
```

`extractor/` es opcional para levantar el sistema: solo hace falta si vas a usar el botón
"Liquidar IVA" (traer/subir comprobantes contra ARCA). Tiene su propio README, su propio
`.env` y su propio repo git (`sistema_contable_iva_extractor`).

## Cómo ponerlo en marcha

```bash
docker compose up -d
```

- Backend: http://localhost:8077
- Frontend: **http://localhost:5173** (entrar por `localhost`, no `127.0.0.1` — con `127.0.0.1`
  el CORS bloquea el login y parece "credenciales incorrectas")
- MySQL: `localhost:3308` (user/pass/db: `modux`/`modux`/`modux`)

Primera vez (catálogos AFIP + usuario admin):

```bash
docker compose exec modux-backend php seeders/CatalogosIvaSeeder.php
docker compose exec modux-backend php seeders/AdminSeeder.php admin@admin.com admin123 "Mi Estudio"
```

Entrá a http://localhost:5173 con esas credenciales.

## Más documentación

- `backend/README.md` — framework Modux (CLI, módulos, arquitectura).
- `extractor/README.md` — cómo correr el bot de ARCA (login, traer, worker).
