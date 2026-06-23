# Módulo IVA — Pendientes

> Estado: **núcleo funcional COMPLETO y utilizable vía API**. Cubre el ciclo
> operativo: cargar comprobantes (ventas/compras) → subdiario / libro IVA →
> libro detallado por alícuota → DDJJ F2002 (saldo técnico) → exportar CSV/TXT.
> Multi-tenant, transaccional, con motor de cálculos (`Decimal` + calculadoras).
>
> Este archivo lista lo **diferido** durante la ingeniería inversa (ver también
> `docs/ingenieria-inversa/iva.md`). Nada de esto bloquea la operación básica.

## A) Quick wins (ya factibles, sin dependencias externas)
- [x] **No borrar período con comprobantes**: `PeriodoService.delete` chequea
      `PeriodoRepository.hasComprobantes()` (ventas o compras del período) y devuelve 409.
      Evita el `ON DELETE CASCADE` que borraría los comprobantes en silencio.
- [x] **Mover comprobante entre períodos**: `POST /empresas/{id}/periodos/{pid}/ventas/{id}/mover`
      (e ídem compras) con `{periodo_destino_id}`. Valida origen y destino abiertos (de la
      misma empresa) y que la fecha del comprobante caiga en el rango del destino. Reasigna
      `periodo_id` en transacción. (Funcionalidad "Mover" del legacy.)
- [x] **Validación amable de FKs** + **ámbito cruzado**: nuevo `App\Support\ReferenceValidator`
      (existencia + scope) usado en `IvaClienteService`/`IvaProveedorService`: `condicion_iva_id`
      y `provincia_id` (catálogos globales), `cuenta_id` (de la empresa) y `rubro_id` (del tenant)
      → 422 con el campo exacto, en vez del 500 por FK. Reutilizable para otros verticales.
- [x] Mismo `ReferenceValidator` aplicado a **ventas/compras**: tipo_comprobante_id,
      tipo_documento_id, condicion_iva_id, provincia_id, tipo_operacion_{venta,compra}_id,
      tipo_moneda_id (globales), rubro_id (del tenant), cliente_id/proveedor_id (de la empresa)
      → 422 con el campo exacto en create/update.
- [x] **Validación de duplicados** (legacy `SISTEMA.VALIDA_DUPLICADOS`): al crear/editar
      un comprobante se chequea que no exista otro igual en la empresa (across períodos).
      Ventas: tipo+letra+punto_venta+número. Compras: + CUIT del proveedor (distintos
      proveedores pueden repetir pv/número). pv/número se comparan por valor numérico
      (ignora ceros a la izquierda). Devuelve 409. Se omite si falta pv o número.
      Pendiente menor: hacerlo configurable (el legacy tenía el flag on/off).
- [x] **ABM de `tipos_retencion` por tenant** (migración 0031 agrega `tenant_id` nullable):
      el estudio ve las estándar de AFIP (tenant_id NULL, read-only) + las propias, y solo edita/
      borra las propias. `/tipos-retencion` (CRUD, en Compartido). El resto de los catálogos AFIP
      (condiciones_iva, tipos_comprobante, etc.) quedan read-only a propósito: códigos fijos de los
      que dependen los resolvers de factura electrónica.
- [x] **Percepciones a nivel comprobante que integran el total** (respuestas.md A1/A2, RESUELTO
      por el contador + factura real Saint-Gobain). Migración 0032 (`venta_percepciones` /
      `compra_percepciones`, a nivel comprobante; `tipos_retencion.base_calculo`). `PercepcionCalculator`
      resuelve la base por estrategia: `neto_gravado` (IIBB/municipal), `neto_mas_imp_interno`
      (IIBB con imp. interno) e `iva_percepcion` (Perc. IVA 3% s/neto 21% + 1,5% s/neto 10,5%, por
      tramos). El total del comprobante suma Σ percepciones; el ABM de `tipos_retencion` configura
      `base_calculo`. importe/base/alícuota informados pisan los del tipo.

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

## D) Exportaciones AFIP / Contable (Fase 3)
- [x] **Libro IVA Digital / Portal IVA** (régimen vigente que reemplaza CITI/RG3685 — respuestas.md
      A9). HECHO los 4 archivos comunes: `VENTAS_CBTE` (266), `VENTAS_ALICUOTAS` (62), `COMPRAS_CBTE`
      (325), `COMPRAS_ALICUOTAS` (84). `Export/RegistroFijo` (formateador de ancho fijo) +
      `Export/LibroIvaDigitalWriter` (los 4 layouts, líneas CRLF; CbteTipo vía `CbteTipoResolver`,
      alícuota vía `AlicuotaIvaResolver`) + `LibroIvaDigitalRepository` (percepciones agrupadas por
      `tipo_rg3685`) + `LibroIvaDigitalService` + `GET /empresas/{id}/periodos/{pid}/libro-iva-digital/
      {ventas-cbte|ventas-alicuotas|compras-cbte|compras-alicuotas}` (descarga). Validado byte a byte
      contra los TXT de ejemplo (`imagenes/`). Layout oficial en `imagenes/disenio_registro_IVA_digital.pdf`.
      - ✅ **Layout confirmado contra el diseño oficial de ARCA** (`imagenes/disenio_registro_IVA_
        digital.pdf`): el writer coincide campo a campo. (1) En VENTAS efectivamente NO hay campo
        propio "Perc. IVA" → va en el campo 13 "Nacionales" (tipo_rg3685 1 y 2 juntos); en COMPRAS
        sí hay campo 12 "Perc. IVA" (rg3685=1) y campo 13 "otros nacionales" agrupa 2 y 5. (3) Código
        de operación y despacho de importación → en blanco (no operan esos casos; confirmar A12).
      - Confirmado por el contador (`imagenesreferencias.md`): **TurIVA NO se usa**; el de **ventas
        anuladas** "el Visual no lo genera". Tipos de comprobante: usan factura/ticket/recibo/ND/NC
        (A7); FCE MiPyME/liquidaciones/exportación aún sin confirmar (A11) → si aparece, `CbteTipoResolver`
        lanza en vez de inventar.
      - Pendiente (no usan hoy): importaciones de bienes/servicios. **Archivo de ventas anuladas**
        (`CBTES_VENTAS_ANULADOS`, 44 pos.): factible con nuestros datos, agregarlo si lo presentan (A11-bis).
      - [x] ✅ **Mapeo de tipos de comprobante ampliado (A11, `GUIA_LIQUIDACION.pdf`)**: el
        `CbteTipoResolver` ahora cubre, además de Factura/ND/NC/Recibo A/B/C/E/M, los comprobantes
        que el estudio usa de verdad: **Tique Factura** (TF → 81/82/83/118), **FCE MiPyME** (FE/DE/CE
        → 201-213), **Liquidación de Servicios Públicos A/B** (LA/LB → 17/18), **NC/Tique** (CZ/CA/CB/
        CN/CT → 110/112/113/114/109), **Factura T** (FT → 195) y **NC T** (NC letra T → 197). Tabla
        partida en `TABLA` (depende de letra) + `TABLA_FIJA` (tipos cuyo código no depende de la
        letra porque ya identifican la clase). Lo usa tanto el Libro IVA Digital como WSFE. Tests en
        `WsfeResolversTest`. Pendiente menor: tipos no-exportables / informes (TZ, TI, RE, LI, PC, CR,
        OT, DI) siguen sin mapear a propósito (lanzan); revisar con dato real si el estudio carga el Z.
- [ ] **Exportador TXT configurable** (réplica de `EXPOTXT_ARCHIVOS` /
      `EXPOTXT_CAMPOS` del legacy): archivos y campos definidos como datos.
- [ ] **Exportación a Contable** (`EXPOVCONTA`): generar asientos / mapeo de cuentas
      hacia el módulo Contable del ecosistema. ⚠️ depende del módulo Contable.
- [ ] `EXPORECE` (retenciones export).

## E) Facturación electrónica AFIP (Fase 4) — integración WS
- [x] **WSAA** (autenticación con certificado): `app/Modules/Iva/Afip/Wsaa/*`, TA cacheado
      en `afip_tickets`. CLI `php modux afip:wsaa <service>`.
- [x] **Padrón** (ws_sr_padron_a5, autocompletar por CUIT): `app/Modules/Iva/Afip/Padron/*`,
      `GET /padron/{cuit}`. CLI `php modux afip:padron <cuit>`.
- [x] **WSFEv1 — numeración + CAE**: `app/Modules/Iva/Afip/Wsfe/*` + `FacturaElectronicaService`
      + `POST /empresas/{id}/periodos/{pid}/ventas/{vid}/cae`. Migración 0029 (`puntos_venta`
      + columnas cae/cae_vto/afip_resultado/afip_obs/fch_serv_* en `ventas`). SOAP validado
      en vivo (FEDummy OK en homologación). CLI `php modux afip:wsfe-dummy`.
- [ ] **Probar en vivo con certificado de homologación** (tramitar CSR + asociar a `wsfe` y
      al padrón). Variables `.env`: `AFIP_CUIT`, `AFIP_CERT_PATH`, `AFIP_KEY_PATH`, `AFIP_ENV`.
- [x] **`CbtesAsoc`**: comprobantes asociados para NC/ND. Migración 0030
      (`venta_comprobantes_asociados`, parte del agregado venta); el alta/edición de venta
      acepta `comprobantes_asociados[]` (tipo_comprobante_id/letra/punto_venta/numero/cuit/fecha)
      y el mapper los emite como `CbtesAsoc → CbteAsoc[]` (Tipo resuelto por tipo+letra).
      También se corrigió el envoltorio del array `Iva` → `AlicIva` (ArrayOfAlicIva del WSDL).
- [x] **Array `Tributos`**: `imp_interno` (Id 4, Impuestos internos) **+ percepciones**. Las
      percepciones del comprobante integran el total (respuestas.md A1) y se emiten como
      `Tributos → Tributo[]` con el Id resuelto por `TributoResolver` (tipo_rg3685 → 1 PercIVA→6,
      2 Nac→1, 3 IIBB→7, 4 Munic→8, 5 no-cat→9). Su importe suma a `ImpTrib`, así la validación AFIP
      `ImpTotal = ImpNeto+ImpIVA+ImpOpEx+ImpTotConc+ImpTrib` cierra.
- [x] **ABM de `puntos_venta`**: CRUD por empresa (`/empresas/{id}/puntos-venta`), único por
      número. Pendiente menor: validar contra `FEParamGetPtosVenta` (sync desde AFIP) y, opcional,
      exigir en la emisión que el punto de venta esté registrado y activo.
- [x] **Autocompletar con padrón**: `GET /padron/{cuit}/sugerencia` devuelve los campos del
      alta de cliente/proveedor ya mapeados (nombre/cuit/domicilio/localidad) + el bloque crudo
      `padron` para que el front complete los desplegables (condición de IVA, provincia) contra
      los catálogos. No se mapea condición/provincia en el back (evita matching riesgoso).
- [ ] **Concurrencia en la numeración**: `FECompUltimoAutorizado`+1 sin lock; suficiente para
      un emisor secuencial, revisar si hay emisión concurrente.
- [ ] **WSMTXCA** (factura con detalle de ítem) y **WSFEXv1** (exportación, comprobante E):
      otros WS si el negocio los necesita.
- [ ] Campos legacy aún no usados: en `empresas` guardar `certificado`/`clave_privada` por CUIT
      (hoy el cert va por ruta en `.env`, un solo emisor); `ventas_cache_ws`.

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

## H) DDJJ IVA Simple (F.2051) — arrastres como insumos
La DDJJ IVA Simple (`GET …/iva-simple`, `IvaSimpleCalculator`, validada con el caso real
de `imagenes/pregunta4.jpeg`) calcula débito/crédito del período:
- [x] **Persistir la DDJJ del período** (HECHO, migración 0033 `iva_ddjj_simple`, una por
      empresa+período). `POST …/iva-simple` la presenta (upsert) y `DdjjSimpleRepository.findAnterior`
      busca la del período inmediato anterior (por `fecha_ini`). Ahora el `GET …/iva-simple` toma el
      `saldo_tecnico_anterior` (= saldo técnico a favor del contribuyente del período anterior) y el
      `saldo_libre_disponibilidad_anterior` (= saldo de libre disponibilidad del período anterior)
      **automáticamente** de la DDJJ presentada; pasarlos por query los sobrescribe. `LibroIvaService.
      presentarIvaSimple` orquesta. Tests en `DdjjSimplePersistenciaTest`.
- [ ] **Retenciones/percepciones/pagos a cuenta SUFRIDOS**: hoy es un insumo (`retenciones_
      percepciones_pagos`). Definir de dónde salen (constancias de retención sufridas vs.
      lo que el contribuyente practica). Las percepciones que ya modelamos integran el
      total del comprobante (lado débito), no son "sufridas" → confirmar con el contador.
- [ ] Restituciones / "neto de usos": hoy se asume que los montos ya vienen netos.
