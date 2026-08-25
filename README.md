# Ecosistema Contable

Sistema contable web multi-tenant para estudios contables (IVA, Sueldos, gestión del estudio),
hecho a medida para el Estudio Haddad. Backend en PHP propio (framework **Modux**) + frontend en
React, pensado para reemplazar Visual IVA (legacy) y unificar la carga de datos por contribuyente.

## Qué hay

```
ecosistema/
├── backend/     API PHP (Modux) — IVA, Sueldos, Fiscal, Tareas, Honorarios, AFIP/ARCA, Admin
├── frontend/    SPA React + Vite + TypeScript + CoreUI
├── extractor/   Bot Playwright que automatiza el Portal IVA de ARCA (traer/subir comprobantes)
└── docker-compose.yml
```

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

## El bot (`extractor/`)

Automatiza el Portal IVA de ARCA (traer comprobantes ya registrados / subir el Libro IVA Digital
calculado) — es lo que atiende el botón "Liquidar IVA" de la UI. Es parte del ecosistema: el
servicio `extractor-worker` **arranca solo** con el mismo `docker compose up -d` de arriba
(`restart: unless-stopped`) y queda escuchando la cola en segundo plano, siempre disponible para
cuando alguien use el botón.

Lo único manual es el **login inicial de cada CUIT** (una vez, con su Clave Fiscal — la sesión de
ARCA queda guardada y se reutiliza sola después):

```bash
cp extractor/.env.example extractor/.env   # completar ARCA_CUIT, ARCA_CLAVE_FISCAL, ECOSISTEMA_API_KEY

docker compose run --rm extractor npm run login
```

Detalle completo (modo manual sin Docker, verificación adicional de ARCA, formato de `.env`,
qué hace cada comando): `extractor/README.md`.

## Más documentación

- `backend/README.md` — framework Modux (CLI, módulos, arquitectura).
- `extractor/README.md` — cómo correr el bot de ARCA en detalle.
