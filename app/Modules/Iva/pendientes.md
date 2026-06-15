# Módulo IVA — Pendientes

> Estado: **núcleo funcional COMPLETO y utilizable vía API**. Cubre el ciclo
> operativo: cargar comprobantes (ventas/compras) → subdiario / libro IVA →
> libro detallado por alícuota → DDJJ F2002 (saldo técnico) → exportar CSV/TXT.
> Multi-tenant, transaccional, con motor de cálculos (`Decimal` + calculadoras).
>
> Este archivo lista lo **diferido** durante la ingeniería inversa (ver también
> `docs/ingenieria-inversa/iva.md`). Nada de esto bloquea la operación básica.

## A) Quick wins (ya factibles, sin dependencias externas)
- [ ] **No borrar período con comprobantes**: hoy hay un `TODO` en `PeriodoService`
      (cuando no existían ventas/compras). Ya existen → implementar el guard
      (período con ventas o compras no se puede eliminar).
- [ ] **Mover comprobante entre períodos** (`POST /ventas/{id}/mover`, idem compras):
      reasignar `periodo_id` validando que el destino esté abierto y la fecha entre.
      (Funcionalidad "Mover" del manual de Ventas/Compras.)
- [ ] **Validación amable de FKs**: hoy un `condicion_iva_id`/`cuenta_id`/`rubro_id`
      inexistente provoca error de FK (500). Validar existencia en el Service y
      devolver 422.
- [ ] **Validación de ámbito cruzado**: que `cuenta_id` pertenezca a la misma
      empresa y `rubro_id` al mismo tenant del sujeto/comprobante.
- [ ] **Validación de duplicados** (legacy `SISTEMA.VALIDA_DUPLICADOS`): evitar
      cargar dos veces el mismo comprobante (tipo+pv+número+proveedor/cliente).
- [ ] **ABM de catálogos por-tenant** que hoy son sólo lectura/seed
      (`tipos_retencion`, etc.), si el negocio lo requiere.
- [ ] **Cálculo del importe de retención** (hoy se recibe del input): opción de
      calcularlo por `base × porcentaje` según tipo de retención.

## B) RBAC / permisos
- [ ] Hoy los endpoints usan `AuthMiddleware` + `TenantMiddleware`. Falta aplicar
      `PermissionMiddleware` por recurso (mapeo de los `PERFILES` del legacy:
      `empresas.*`, `ventas.*`, `compras.*`, `periodos.cerrar`, `reportes.ver`, …).
      Ver §7 de `docs/ingenieria-inversa/iva.md`.

## C) Reportes (Fase 2) — falta presentación y reportes secundarios
- [ ] **Render a PDF** de los reportes (los 64 `.fr3` mapeados en
      `softContable/analisis/reportes_iva.{md,json}`). Hoy entregamos los **datos**
      (subdiario ventas/compras + libro detallado + DDJJ). Falta el maquetado
      (matriz de puntos / A4) — probablemente trabajo de frontend o un servicio PDF.
- [ ] Reportes secundarios aún no hechos: **retenciones/percepciones**, listados
      varios, detalle por cuenta, factura. (Categorías del análisis: 4 Retenciones,
      6 Listados, 7 Detalle/cuentas, 1 Factura elec.)
- [ ] **SIAP / DDJJ adicionales**: `IVASIAP.fr3`, `IVA_DDJJMonotributo.fr3`.

## D) Exportaciones AFIP / Contable (Fase 3) — REQUIERE SPEC EXTERNO
- [ ] **CITI / RG 3685** (`REGINFO_CV_*`, ancho fijo): el layout exacto NO está en
      las fuentes (sin config `EXPOTXT`, manual vacío). Falta el instructivo AFIP o
      la config de producción para implementarlo sin inventar. Campos ya disponibles:
      `tipos_comprobante.cod_citi`, `condiciones_iva.codigo_afip`, alícuotas, CUIT.
- [ ] **Exportador TXT configurable** (réplica de `EXPOTXT_ARCHIVOS` /
      `EXPOTXT_CAMPOS` del legacy): archivos y campos definidos como datos.
- [ ] **Exportación a Contable** (`EXPOVCONTA`): generar asientos / mapeo de cuentas
      hacia el módulo Contable del ecosistema. ⚠️ depende del módulo Contable.
- [ ] `EXPORECE` (retenciones export).

## E) Facturación electrónica AFIP (Fase 4) — integración WS
- [ ] **WSAA / WSFE**: emisión de comprobantes electrónicos. Tablas/campos del legacy
      diferidos: `ventas_cache_ws`, y en `empresas` los `certificado`, `clave_privada`,
      `token`/`sign` y vencimientos. Campos `ELECTRO_*` de `ventas` (tipo, estado,
      fechas de otorgamiento/servicio).

## F) Campos del legacy podados (re-incorporar si el negocio los pide)
- [ ] **Imputación contable** en comprobantes/discriminación: `*_CTA_*`
      (cuenta total/neto/iva/imp interno), `*_CTA_DEBE`/`*_CTA_HABER`. Necesarios
      para asientos y export a Contable.
- [ ] `total_productos` / `total_servicios`, `id_actividad`, `campo_aux` /
      `nombre_campo_aux`, `reten_nro_fac` / `reten_vtaid` (ventas).
- [ ] **Múltiples CAI** en proveedores (`cai2..5` + fechas) — hoy sólo el principal.
- [ ] **`esglobal`**: sujetos (clientes/proveedores) compartidos entre empresas del
      tenant. Hoy el campo se conserva pero las consultas filtran por empresa.

## G) Otros / infraestructura
- [ ] Paginación y filtros en los listados de comprobantes (fecha, cliente/proveedor)
      — el manual menciona filtros por fecha y búsqueda; hoy se listan completos.
- [ ] Manejo de `tipo_moneda` / `tipo_cambio` en reportes en moneda extranjera.
- [ ] Auditoría (el legacy tenía tabla `LOG`): usar el Logger / eventos del framework.
