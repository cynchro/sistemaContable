# Modux

Un framework PHP **modular-monolith**, _dependency-injection-first_, liviano y sin magia.
Cada dominio de negocio vive en su propio módulo autocontenido. Sin facades, sin estáticos
mágicos, sin globales ocultos — cada dependencia es explícita e inyectada.

**Ideal para** equipos que quieren control total del código, un ciclo de request claro y
tests reales sin aprender las convenciones de un framework grande.

---

## El flujo de una request

```
Request → Kernel → Pipeline global (CORS, RequestSize, SecurityHeaders, Logger)
                 → Router → middlewares de ruta (Auth, Tenant, Scope, Entitlement, Quota)
                 → Controller → Service → Repository → PDO
                 → Response
```

## Quick start

```bash
# 1. Crear el proyecto desde Packagist
composer create-project cynchro/modux my-app && cd my-app

# 2. Configurá el entorno
cp .env.example .env          # set JWT_SECRET, DB_HOST, DB_NAME, DB_USER, DB_PASS

# 3. Migraciones
php modux migrate

# 4. Servidor
php -S localhost:8080 -t public
```

```bash
# Login
curl -X POST localhost:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"usuario":"user@example.com","clave":"secret-password"}'

# Health check
curl localhost:8080/health
```

## Performance

Medido sobre la imagen de producción (PHP 8.2 + Apache/mod_php, MySQL 8.0) con ApacheBench a
concurrencia 50:

| Endpoint | Qué mide | Req/s | p50 | p95 | p99 |
|---|---|---|---|---|---|
| `GET /` | Overhead del framework (routing + DI + pipeline) | ~3.520 | 13 ms | 21 ms | 27 ms |
| `GET /health` | Framework + un round-trip `SELECT 1` | ~1.910 | 25 ms | 38 ms | 45 ms |

Unos **0,28 ms de overhead de framework por request**, cero peticiones fallidas bajo carga.
Los números son indicativos (un host containerizado) y varían con el hardware y la carga.

## Por dónde seguir

- **[CLI](cli.md)** — `make:module`, `make:migration`, `migrate`, `routes`, `queue:*`…
- **[Módulos y ruteo](modules.md)** — crear un módulo, grupos de rutas, secuencia de arranque.
- **[HTTP](http.md)** — Request/Response, validación, excepciones → HTTP, middleware.
- **[Auth y multi-tenancy](auth-and-tenancy.md)** — JWT, API keys, scopes, webhooks, aislamiento por tenant.
- **[Infraestructura](infrastructure.md)** — config, logger, migraciones, **testing**.
- **[Plataforma](platform.md)** — eventos, RBAC, entitlements, cuotas, cola de jobs.
- **[Módulos opcionales](optional-modules.md)** — IA (LLM + RAG) y Billing (Stripe / Mercado Pago).

---

## Contacto

**alexissaucedo@gmail.com** · [cynchrolabs.com.ar](https://www.cynchrolabs.com.ar)

## ¿Me invitás un café?

Si Modux te ahorró tiempo, una donación ayuda a mantener el proyecto.

<a href="https://www.paypal.com/donate/?hosted_button_id=YX332RT7KSJ4Q">
  <img src="https://img.shields.io/badge/PayPal-Donar-blue?logo=paypal" alt="Donar con PayPal"/>
</a>

---

⭐ Si te gusta el proyecto, ¡dejá una estrella!

<a href="https://github.com/cynchro/modux">
  <img src="https://img.shields.io/github/stars/cynchro/modux?style=social" alt="GitHub stars"/>
</a>
