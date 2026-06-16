# Módulo Contribuyentes — Pendientes

> Estado: **CRM del contribuyente operativo vía API**. El contribuyente es la entidad
> canónica `empresas` (Compartido) = `Persona` del legacy sistemaCuarto/Synergys; acá
> viven sus sub-entidades de CRM. Multi-tenant (estudio = tenant).
>
> Análisis y decisiones: `docs/ingenieria-inversa/sistemacuarto.md`. Este archivo lista
> lo diferido. Nada de esto bloquea la operación básica del CRM.

## Hecho
- [x] **Campos CRM en la empresa canónica** (migración 0026): email, contacto,
      tipo_persona, inscripcion, contabilidad.
- [x] **Socios/integrantes** (migración 0026, tabla `socios`): CRUD por empresa.
      Endpoints `/empresas/{id}/socios`.
- [x] **Credenciales de acceso** (migración 0027, tabla `credenciales_acceso`): CRUD por
      empresa. Unifica las tablas `cuentas` (portales fiscales AFIP/RENTAS) y `tarjetas`
      (procesadoras VISA/NARANJA) del legacy en una sola, discriminada por `tipo`
      (`fiscal`/`tarjeta`) — sin redundancia. La `clave` se guarda **cifrada en reposo**
      (`App\Support\Crypto`, AES-256-GCM, key=`APP_ENCRYPTION_KEY`) y la API la devuelve
      en claro (el estudio la necesita para operar el portal del cliente). Endpoints
      `/empresas/{id}/credenciales`. Estados: activa/inactiva/bloqueada/cerrada.

## A) Documentación del contribuyente — REQUIERE SUBIDA DE ARCHIVOS
- [ ] Gestión de documentos del contribuyente (constancias, formularios, escaneos):
      modelo + almacenamiento de archivos (filesystem/S3) + metadatos por empresa.
      No implementado: el framework aún no tiene capa de upload/almacenamiento de
      archivos; definir esa infraestructura primero.

## B) Integraciones / ingesta — REQUIERE DB DE PROD o APIs EXTERNAS
Estas funciones de sistemaCuarto traen datos de sistemas externos; se construyen recién
con acceso operativo / esquema real de prod (las migraciones del legacy están incompletas).
- [ ] **Cuentas tributarias (SCT de ARCA)**: tabla `cuentas_tributarias` del legacy es un
      snapshot por sync (secciones vencimientos/deudas/ddjj_pendientes en JSON, historial
      por fecha). Requiere el conector de ingesta (`source=api-import`).
- [ ] **Import compra-venta** (API de importación de comprobantes hacia el módulo Iva).
- [ ] **BCRA** (situación crediticia / central de deudores).
- [ ] **Facilidades de pago ARCA**, **DFE** (domicilio fiscal electrónico), y demás syncs.

## C) Descartado por diseño (no migrar)
- Chat / mensajería interna.
- Notificaciones push/email del legacy.
- Flota / vehículos.
- Monitor de escritorio (app desktop).

## D) RBAC / permisos
- [ ] Aplicar `PermissionMiddleware` por recurso (hoy Auth + Tenant):
      `socios.*`, `credenciales.*`.

## Notas de seguridad (credenciales)
- `APP_ENCRYPTION_KEY` debe existir en cada entorno (generar con `openssl rand -hex 32`).
  **Si se pierde, las claves cifradas no se pueden recuperar.** Está en `.env` (local,
  gitignored), `.env.example` (placeholder) y `phpunit.xml` (tests).
- `App\Support\Crypto` es cifrado **reversible** para secretos de terceros. NO usarlo para
  contraseñas de login propias del sistema → ésas siguen con `password_hash` (Auth).
- Rotación de `APP_ENCRYPTION_KEY`: requeriría re-cifrar las filas existentes
  (descifrar con la vieja, cifrar con la nueva). No hay rutina de rotación todavía.
