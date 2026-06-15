# Changelog

Todos los cambios relevantes de este proyecto se documentan acá.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.1.0/) y el
versionado es [SemVer](https://semver.org/lang/es/). Las versiones hasta la
`v1.2.0` están en los tags de git y el historial; este archivo arranca el
registro formal a partir de los cambios siguientes.

## [Unreleased]

### Documentación

- Secciones **Contact / Buy me a coffee / Star** (con badges de PayPal y GitHub) en el README,
  la home del sitio (ES/EN) y —vía el README— el wiki.
- Documentada la **instalación vía Packagist** con `composer create-project cynchro/modux`
  (el método recomendado para un skeleton `type: project`) en README y en la home del sitio
  (ES/EN). Corregidos dos bugs del Quick start: un `cd backend/src` obsoleto (ese directorio
  ya no existe) y el ejemplo de `/health` que mostraba el shape viejo (ahora `checks.db/cache`).

## [2.1.0] - 2026-06-10

### Añadido

- **`scripts/build-wiki.sh`** — regenera y publica la wiki de GitHub a partir de
  `README.md` + `docs/*.md` (espejo idempotente, con `--no-push` para revisar). Mantiene
  la wiki en sync con la doc del repo sin trabajo manual.
- **Workflow `.github/workflows/wiki.yml`** — publica la wiki automáticamente al pushear a
  `main` cuando cambian `README.md`, `docs/**` o el script (usa `GITHUB_TOKEN`).
- **Sitio de documentación (MkDocs Material) en GitHub Pages** — `mkdocs.yml` + workflow
  `docs.yml` generan un sitio navegable (sidebar, buscador, dark mode, "edit on GitHub")
  desde los mismos `docs/`, publicado en `https://cynchro.github.io/modux/`. Se quitaron los
  back-links repo-específicos de los `docs/*.md` y se añadió `docs/index.md` como home.
- **Branding del sitio de docs** — logo oficial Modux (`logo.png`) en el header e isologo
  (`isologo.png`) como favicon; paleta de marca azul-teal (navy `#24486c` + teal, editable en
  `docs/assets/extra.css`), header con degradé navy→teal y el logo en blanco para leer encima;
  tipografías Inter + JetBrains Mono; footer firmado por
  [CynchroLabs](https://cynchrolabs.com.ar/) (+ iconos sociales).
- **Sitio de docs bilingüe (español por defecto + inglés) con selector de idioma**
  (`mkdocs-static-i18n`). Estructura por sufijo: `page.md` (es) + `page.en.md` (en); nav y UI
  traducidos por idioma. Resuelve el "spanglish" (menú ES + contenido EN). El wiki sigue en
  inglés (lee las variantes `*.en.md`).

### Rendimiento

- **Conexiones PDO persistentes opt-in** (`DB_PERSISTENT`, false por defecto) — reutilizan la
  conexión entre requests del mismo worker (FPM/mod_php) y evitan el handshake por request.
  Ajustá `max_connections` antes de activarla.

### Cambiado

- **Health check más profundo** (`GET /health`): chequea DB **y** cache con severidades — la
  DB es crítica (`status: down` + 503), el cache se reporta como degradación sin cambiar el
  código de estado. *La forma de la respuesta cambió*: `db` pasó de top-level a `checks.db`
  (+ `checks.cache`), y el estado degradado es `down` (antes `degraded`). Los probes que miran
  el código HTTP (200/503) no se ven afectados.
- Nuevo accesor `Response::getBody(): ?array` (simétrico con `getStatus()`/`getHeaders()`).
- **Quitada la comparación "Why not Laravel?"** del README; reemplazada por una sección
  **Performance** con datos reales de benchmark (sin comparaciones con otros frameworks):
  `GET /` ~3.520 req/s (≈0,28 ms de overhead/req), `GET /health` ~1.910 req/s, p50/p95/p99 y
  0 fallos — medido sobre la imagen de producción (PHP 8.2 + Apache, MySQL 8) con ApacheBench.
  El benchmark también figura en la home del sitio de docs.

### Documentación

- Sincronizados los docs de usuario con el código de la v2.x: la sección de Feature
  tests (`docs/infrastructure.md`) ahora describe el harness real (`actingAsUser`,
  `postJson/getJson` → `['status','json','headers']`, sembradores, rollback por test,
  requisito de MySQL y skip sin DB) en vez de una API que no existía; documentados
  `LOG_CHANNEL` (con la portabilidad `php://stderr`), `composer audit` en el quality
  gate, el MySQL service del CI y el caveat de store compartido del anti-replay.
- `docs/cli.md`: completada la tabla de comandos del CLI — estaban faltando `queue:work`,
  `queue:failed`, `queue:retry`, `queue:flush` y `entitlements:roll-periods` (los 12 comandos
  reales del binario `modux` ahora figuran en la referencia).

## [2.0.1] - 2026-06-09

### Corregido / Seguridad

- **`Logger` rompía bajo SAPI web.** El driver `stderr` (y el fallback de escritura
  a archivo) usaban la constante `STDERR`, que **solo existe en CLI**: con
  `LOG_CHANNEL=stderr` bajo Apache/php-fpm, cada request lanzaba un `Error` y
  devolvía 500. Ahora se usa `php://stderr` (portable entre SAPIs). Cubierto por
  `LoggerTest`. Hallazgo del benchmark de la v2.0.0.
- **`firebase/php-jwt` 6.x → 7.0.5** — cierra `CVE-2025-45769` (weak encryption,
  severidad baja). La API usada (`JWT::encode` / `JWT::decode` + `Key`) no cambió.

### Añadido

- **`composer audit` en el CI y en el pre-push hook** — falla el build/push ante
  cualquier advisory de seguridad en dependencias.

## [2.0.0] - 2026-06-09

### ⚠ Cambios incompatibles (BREAKING)

- **`App\Support\Contracts\CacheInterface` declara `available(): bool`.** Quien
  tenga una implementación propia de la interfaz (p. ej. un cache Redis) debe
  añadir el método. Las implementaciones del base (`ArrayCache`, `ApcuCache`) ya
  lo traen. Por ser una adición a una interfaz pública, esta release es
  **major** (`v2.0.0`).

### Añadido

- **Red de Feature/integration tests contra MySQL real.** `FeatureTestCase`
  completo (PDO compartido test↔app, esquema vía migraciones, transacción por
  test con rollback, despacho por el Router real). 19 Feature tests: flujo de
  auth, CRUD multi-tenant, aislamiento entre tenants, gating de entitlements
  (402) y cuotas (429 + `Retry-After`), y API keys/scopes (incl. prevención de
  escalada de privilegios). Se saltan con elegancia si no hay MySQL disponible.
- **CI con base de datos real.** El job `quality` levanta un service `mysql:8.0`;
  `composer test` ahora corre Unit + Feature contra DB real.
- **`CacheInterface::available()`** — indica si el backend es operativo; las
  features que dependen de estado compartido por seguridad lo consultan.
- **Migración `0010_add_nombre_to_clientes`** — añade la columna de ejemplo
  `nombre` al CRUD de scaffolding (idempotente, reversible).
- **CRUD de ejemplo `clientes` funcional** — `create()`/`update()` reales (antes
  lanzaban "not implemented"), validación y aislamiento por tenant.

### Cambiado

- **`WebhookVerifier` falla cerrado** cuando el cache anti-replay no es operativo
  (rechaza en vez de aceptar reenvíos sin protección).
- **`Container`** — `makeWith()` y `autowire()` unificados en un único `build()`
  (sin cambios de API pública).
- **README** partido en una landing breve + manual temático en `docs/`
  (48 KB → 8 KB).
- **PHPStan** — baseline vaciado (84 → 0); se desactivó la regla
  `missingType.iterableValue` a nivel proyecto y se corrigieron los hallazgos
  reales (`$router` con `@var`, `Response` `final`, comparación muerta).

### Corregido / Seguridad

- **Anti-replay de webhooks**: cerrada la degradación silenciosa a inseguro
  cuando APCu no estaba operativo (multi-instancia / `apc.enable_cli=0`). El ADR
  0001 §1.3 documenta el requisito de un store compartido. Aviso al bootear.
- **Inmutabilidad de migraciones**: se revirtió la edición in-situ de `0004` y se
  trasladó el cambio a la migración nueva `0010`.

## Versiones previas

Hasta `v1.2.0`, ver los tags de git (`git tag -l`) y el historial de commits.
