# Ecosistema Contable

Sistema contable del estudio (IVA, Sueldos, gestión del estudio, AFIP) construido sobre el
framework propio **Modux** (monolito modular, PHP 8.2+). Monorepo:

- **`backend/`** — API PHP (Modux): módulos de dominio, migraciones, seeders, tests.
- **`frontend/`** — SPA React + Vite + TypeScript + CoreUI.
- **raíz** — orquestación Docker y documentación del proyecto (`docs/`, `preguntas.md`, etc.).

El contexto técnico vivo de la sesión está en [`CLAUDE.md`](CLAUDE.md).

## Levantar el entorno

```bash
docker compose up -d
# Tras un clone limpio, instalar dependencias del backend (el volumen tapa el vendor del build):
docker compose exec modux-backend composer install
```

| Servicio        | URL / puerto                | Notas                                              |
|-----------------|-----------------------------|----------------------------------------------------|
| Frontend (SPA)  | http://localhost:5173       | nginx; consume la API en `localhost:8080`          |
| Backend (API)   | http://localhost:8080       | `GET /health` para smoke test                      |
| MySQL (externo) | `127.0.0.1:3308`            | user/pass/db: `modux` / `modux` / `modux`          |

## 🔑 Usuarios para ingresar al sistema

Se ingresa por el frontend (http://localhost:5173) con **email + clave**.

| Email             | Clave      | Rol           | Permisos      | Tenant (estudio)  |
|-------------------|------------|---------------|---------------|-------------------|
| `admin@admin.com` | `admin123` | Administrador | Acceso Total  | Estudio Cynchro   |

> ⚠️ Es el usuario **de desarrollo** sembrado por defecto. El rol *Administrador* tiene el
> super-permiso **"Acceso Total"**, así que ve y opera todos los módulos (RBAC por recurso
> incluido). Cambiar la clave antes de cualquier uso real.

### Crear / resembrar usuarios

```bash
# Crear un admin (email, clave, nombre del tenant/estudio):
docker compose exec modux-backend php seeders/AdminSeeder.php admin@admin.com admin123 "Estudio Cynchro"

# (Opcional) permisos granulares de IVA para roles que NO sean "Acceso Total":
docker compose exec modux-backend php seeders/PermisosIvaSeeder.php

# Catálogos AFIP (condiciones de IVA, comprobantes, provincias, etc.) — idempotente:
docker compose exec modux-backend php seeders/CatalogosIvaSeeder.php
```

El `AdminSeeder` es idempotente: si el usuario ya existe, asegura el rol *Administrador* y el
permiso *Acceso Total*. La clave se guarda con `password_hash` (no es recuperable; se resetea
volviendo a correr el seeder con la nueva clave).

## Comandos útiles

```bash
# CLI del framework (dentro del contenedor):
docker compose exec modux-backend php modux <migrate|migrate:fresh|routes|make:module|make:migration>

# Tests / calidad (backend):
docker compose exec -e DB_HOST=moduxdb -e DB_NAME=monolito_test -e DB_USER=root -e DB_PASS=root \
  modux-backend vendor/bin/phpunit
docker compose exec modux-backend composer analyse   # PHPStan nivel 6
docker compose exec modux-backend composer lint      # PHPCS PSR-12

# Frontend:
cd frontend && npm run build   # tsc -b + vite build  ·  npm run lint  ·  npm run dev
```

## Documentación

- `CLAUDE.md` — estado de la sesión y decisiones técnicas.
- `docs/ingenieria-inversa/` — análisis del sistema legacy por módulo.
- `preguntas.md` — dudas de dominio contable/impositivo para el contador (respondidas + pendientes).
- `docs/dashboard/` — tablero scrum del cliente (`progreso.json` + `sync.sh`).
