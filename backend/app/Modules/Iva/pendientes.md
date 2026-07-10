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
- [x] **HECHO**: las rutas del módulo usan `AuthMiddleware` + `TenantMiddleware` +
      `PermissionMiddleware` por recurso. Taxonomía (key + nivel read/write): `iva.clientes`,
      `iva.proveedores`, `iva.ventas`, `iva.compras`, `iva.libro` (totales/detalle/ddjj/iva-simple/
      reportes/exportar/libro-iva-digital; presentar DDJJ = write), `iva.padron`, `iva.facturacion`
      (CAE, write), `iva.puntos-venta`. GET = lectura, POST/PUT/DELETE = escritura.
      - **Ajuste de framework**: `PermissionChecker` ahora hace que el super-permiso 'Acceso Total'
        habilite cualquier key en nivel escritura (antes sólo `AdminMiddleware` lo respetaba). Así el
        admin pasa sin asignar cada permiso.
      - **JWT**: `JWTConfig::generateToken` ahora incluye el claim `rol` (login/refresh lo pasan), que
        `PermissionMiddleware` necesitaba (antes el token no lo llevaba → el middleware nunca tenía rol).
      - Keys sembrables para roles granulares: `seeders/PermisosIvaSeeder.php`. El admin (Acceso Total)
        no las necesita. Tests: `IvaRbacTest` (sin permiso → 403; lectura no habilita escritura).

## C) Reportes (Fase 2) — falta presentación y reportes secundarios
- [x] **Render a PDF** (HECHO vía frontend). La página de Libro IVA tiene una pestaña
      **Reportes** con el subdiario de ventas/compras (renglón por comprobante + totales) y las
      percepciones por tipo, con botón **Imprimir / PDF** (print del navegador + CSS `@media print`
      que oculta el chrome del layout). El maquetado fino de los `.fr3` no se replica; la impresión
      a PDF cubre la necesidad operativa.
- [x] **Reportes secundarios — retenciones/percepciones** (HECHO). `GET …/reportes/percepciones`
      agrupa `venta_percepciones`/`compra_percepciones` por tipo (y provincia) con base, importe y
      cantidad + totales. `ReporteIvaRepository::percepciones*` + `ReporteIvaService::percepciones`.
      Tests: `ReportePercepcionesTest`. (Listados varios / detalle por cuenta siguen diferidos.)
- [ ] **SIAP / DDJJ adicionales**: `IVASIAP.fr3`, `IVA_DDJJMonotributo.fr3`. **Monotributo**: los
      monotributistas no presentan DJ de IVA → conceptualmente N/A; pendiente hasta que el contador
      confirme un caso de uso y el layout.

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
      - Pendiente (no usan hoy): importaciones de bienes/servicios.
      - [x] **Archivo de ventas anuladas** (`LIBRO_IVA_DIGITAL_CBTES_VENTAS_ANULADOS`, 44 pos.) HECHO.
        Migración 0045 (`ventas.anulado` CHAR + `fecha_anulacion` DATE). `LibroIvaDigitalWriter::ventasAnulados`
        (5 campos: fecha/tipo/pv/número/fecha de anulación, validado contra el diseño oficial pág. 8) +
        `LibroIvaDigitalRepository::ventasAnulados` (WHERE anulado='S') + slug `ventas-anulados` en el Service.
        Frontend: checkbox "Comprobante anulado" + fecha en `VentaFormModal`, badge "Anulado" en el listado, y
        botón "Ventas — anulados" en la pestaña Descargas. Test `LibroIvaDigitalTest` (largo 44, byte-exacto).
      - [x] ✅ **Mapeo de tipos de comprobante ampliado (A11, `GUIA_LIQUIDACION.pdf`)**: el
        `CbteTipoResolver` ahora cubre, además de Factura/ND/NC/Recibo A/B/C/E/M, los comprobantes
        que el estudio usa de verdad: **Tique Factura** (TF → 81/82/83/118), **FCE MiPyME** (FE/DE/CE
        → 201-213), **Liquidación de Servicios Públicos A/B** (LA/LB → 17/18), **NC/Tique** (CZ/CA/CB/
        CN/CT → 110/112/113/114/109), **Factura T** (FT → 195) y **NC T** (NC letra T → 197). Tabla
        partida en `TABLA` (depende de letra) + `TABLA_FIJA` (tipos cuyo código no depende de la
        letra porque ya identifican la clase). Lo usa tanto el Libro IVA Digital como WSFE. Tests en
        `WsfeResolversTest`. Pendiente menor: tipos no-exportables / informes (TZ, TI, RE, LI, PC, CR,
        OT, DI) siguen sin mapear a propósito (lanzan); revisar con dato real si el estudio carga el Z.
- [x] **Exportador TXT configurable** (HECHO, réplica funcional de `EXPOTXT_ARCHIVOS` /
      `EXPOTXT_CAMPOS`). Migración 0034 (`iva_export_formatos` + `iva_export_formato_campos`).
      El tenant define formatos (delimitado o ancho fijo) eligiendo campos del subdiario, su
      orden y formato (longitud/relleno/alineación/decimales/fecha). A diferencia del legacy,
      los campos son una **lista blanca** en código (`Export/ExportCampoCatalogo`) sobre el
      subdiario de `ReporteIvaRepository` — sin SQL dinámico ni columnas arbitrarias.
      `Export/ExportTxtConfigurableWriter` (puro) + `ExportFormatoRepository`/`Service`/
      `Controller`. Endpoints: `/iva/export-formatos` (CRUD, tenant) y
      `GET …/exportar-config/{formatoId}?tipo=ventas|compras` (descarga). RBAC `iva.libro`.
      Tests: `ExportTxtConfigurableWriterTest` (unit), `ExportConfigurableTest` (feature).
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
- [x] **Circuito CAE completo validado en vivo (homologación, 2026-07-01)**: cadena real
      WSAA→wsfe + `FECompUltimoAutorizado` + `FECAESolicitar` (Factura B a Consumidor Final,
      neto 100 + IVA 21 = 121) → ARCA devolvió CAE (resultado `A`, CAE `86260518505470`,
      vto `2026-07-11`), sin observaciones ni errores. Certificado de homologación asociado a
      `wsfe` (CUIT 23321452639). Variables `.env`: `AFIP_CUIT`, `AFIP_CERT_PATH`, `AFIP_KEY_PATH`,
      `AFIP_ENV`. Falta solo el trámite del certificado de **producción** (fuera de código).
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
- [x] **Concurrencia en la numeración** (HECHO). `FacturaElectronicaService::autorizar` envuelve
      la sección crítica —leer `FECompUltimoAutorizado`+1 → solicitar CAE → persistir— en un lock
      consultivo `DB::withLock("cae:{empresaId}:{ptoVta}:{cbteTipo}", …, 30)` (GET_LOCK/RELEASE_LOCK
      de MySQL, con `finally`). Dos emisiones concurrentes del mismo punto de venta + tipo se serializan
      (la segunda espera hasta 30s por la primera) en vez de tomar el mismo número. `DB::withLock` es
      reutilizable para otras secciones críticas.
- [ ] **WSMTXCA** (factura con detalle de ítem) y **WSFEXv1** (exportación, comprobante E):
      otros WS si el negocio los necesita.
- [ ] Campos legacy aún no usados: en `empresas` guardar `certificado`/`clave_privada` por CUIT
      (hoy el cert va por ruta en `.env`, un solo emisor); `ventas_cache_ws`.

## F) Campos del legacy podados (re-incorporar si el negocio los pide)
- [~] **Imputación contable** en comprobantes: **HECHO a nivel comprobante** (migración 0042:
      `cuenta_debe_id` / `cuenta_haber_id` en ventas y compras, FK a `cuentas`). Alimenta el
      **Mayor de cuentas** (`GET …/periodos/{pid}/mayor` resumen + `…/mayor/{cuentaId}` detalle) que
      el estudio usa a diario. Pendiente v2: imputación **por línea** (separar neto/IVA en cuentas
      distintas, para asientos completos y export a Contable) y mayor por rango/anual.
- [~] `total_productos` / `total_servicios`, `id_actividad`, `campo_aux` /
      `nombre_campo_aux`, `reten_nro_fac` / `reten_vtaid` (ventas). **Parcial**:
      `id_actividad` HECHO (`ventas.actividad_id` / `compras.actividad_id`, migración 0036,
      resuelve la DJ IVA Simple por actividad); `campo_aux` HECHO (`campo_auxiliar`, migración
      0039, en ventas y compras); `numero_fin` HECHO en ventas (equivalente a `reten_nro_fac`,
      número final del rango). Siguen sin usar: `total_productos`/`total_servicios` y
      `reten_vtaid` (no hay caso de uso confirmado).
- [x] **Múltiples CAI** en proveedores (`cai2..5` + fechas). HECHO: migración 0040 agrega
      `iva_proveedores.cais` (JSON, hasta 5 `{numero, vencimiento}`); el `cai`/`fecha_cai` simple
      sigue como principal. El repo hace encode/decode del JSON; el request valida `cais` como
      array nullable (el tope de 5 se controla en el front). ABM en `SujetoFormModal` (field-array
      solo para proveedores).
- [x] **`esglobal`**: sujetos (clientes/proveedores) compartidos entre empresas del tenant.
      HECHO: `IvaCliente/IvaProveedorRepository::findAllByEmpresa($empresaId, $tenantId)` trae los
      propios + los globales de cualquier empresa del tenant. Front: checkbox "Compartir con todas
      las empresas", badge **Global**, Editar/Eliminar deshabilitados para un global de otra empresa
      (se administra desde su empresa de origen; la edición propaga por ser la misma fila).

## G) Otros / infraestructura
- [x] **Paginación y filtros en los listados de comprobantes** (HECHO). `GET …/ventas` y
      `GET …/compras` aceptan `page`/`per_page` (default 50, máx 200) y filtros por query:
      `fecha_desde`, `fecha_hasta`, `letra` y `cliente_id` (ventas) / `proveedor_id`+`cuit` (compras).
      Repositorios con `findPaginado` (WHERE con placeholders, sin SQL dinámico de valores) vía
      `PaginatorHelper`. Respuesta paginada `{ total, cantidad_por_pagina, pagina, results }`.
      Se corrigió un bug del `PaginatorHelper` (bindear `LIMIT`/`OFFSET` como int; MySQL rechaza
      `LIMIT '10'` con emulación de prepares desactivada). Tests: `VentaListadoTest`.
- [x] Manejo de `tipo_moneda` / `tipo_cambio` en reportes en moneda extranjera (HECHO). El subdiario
      (`ReporteIvaRepository::ventas`/`compras`) hace `LEFT JOIN tipos_moneda` y expone `moneda_codigo`
      (código AFIP) + `moneda_nombre` por comprobante; `tipo_cambio` ya venía en la fila. Los importes
      del libro IVA se mantienen en pesos (el motor no reconvierte por `tipo_cambio`: el Libro IVA
      argentino se lleva en moneda de curso legal; moneda/cotización son informativos del comprobante).
- [x] **Auditoría de operaciones** (HECHO, registro de cambios — el legacy tenía tabla `LOG`).
      Migración 0035 (`iva_audit_log`). `Audit/AuditMiddleware` (en el grupo de rutas de IVA)
      registra cada escritura exitosa (POST/PUT/PATCH/DELETE, status < 400): tenant, user_id,
      método, uri, route params y payload. Best-effort (nunca rompe la operación). Lectura
      paginada `GET /iva/auditoria` (RBAC `iva.auditoria`). `AuditoriaRepository`/`Controller`.
      Tests: `AuditoriaIvaTest`.

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
- [x] **Retenciones/percepciones/pagos a cuenta SUFRIDOS** (RESUELTO por diseño, guía A13/A14):
      salen de "Mis Retenciones" / SIFERE y de compensaciones del Sistema de Cuentas Tributarias
      de ARCA, fuera del sistema; llegan **netas** y se ingresan como **insumo** del período
      (`retenciones_percepciones_pagos` en el `GET/POST …/iva-simple`). No son las percepciones que
      modelamos (ésas integran el total del comprobante, lado débito). No hay nada más que construir
      en código; queda como entrada del usuario.
- [ ] Restituciones / "neto de usos": hoy se asume que los montos ya vienen netos.
