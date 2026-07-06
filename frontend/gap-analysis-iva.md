# Matriz de brechas — Módulo IVA (frontend ↔ backend)

Objetivo: conectar **todo lo del módulo IVA** desde el frontend con el backend, consumir el
backend y **quitar lo hardcodeado**. Este documento recorre el manual del Visual IVA 6.10
(`softContable/manuales/deepresol/visual-iva/doc/Visual-IVA-6.10-Manual.md`) y mapea cada
función/campo contra el **modelo/endpoint del backend** y el **estado en el frontend**.

**Método**: manual = spec funcional → ¿lo soporta el backend? → ¿lo consume el front? → acción.

**Leyenda de estado**
- ✅ **Conectado**: el front consume el backend correctamente.
- 🟡 **Parcial / simplificado**: funciona pero difiere del manual o le falta una regla.
- 🔴 **Faltante (backend listo)**: el backend lo soporta y el front **no** lo usa → wire.
- 🟧 **Hardcodeado**: valores fijos en el front que deberían venir del backend (o decidir dejarlos).
- ⛔ **Fuera de modelo**: el manual lo tiene pero nuestro modelo no (requiere migración/decisión o módulo Contable).

Referencias backend: migraciones `0014` (ventas + venta_discriminaciones + venta_retenciones),
`0015` (compras), `0030` (venta_comprobantes_asociados), `0032` (venta/compra_percepciones +
tipos_retencion.base_calculo), `0039` (campo_auxiliar). Payload venta/compra en
`app/Modules/Iva/Services/{Venta,Compra}Service.php` (`create`→`preparar`).

---

## A. Listado de comprobantes (Compras / Ventas)

| Función (manual) | Backend | Front | Estado | Acción |
|---|---|---|---|---|
| Listado fecha/nombre/nº/total + paginado | `GET …/ventas\|compras` | sí | ✅ | — |
| Filtro por fecha (rango) | query `fecha_desde/hasta` | sí | ✅ | — |
| Buscar proveedor/cliente | query `nombre` (LIKE) + cuit | filtro por nombre | ✅ | **HECHO** — input Cliente/Proveedor (LIKE) en ambos listados |
| Filtro por comprobante (nº/letra) | query `letra` + `numero` (LIKE) | ambos | ✅ | **HECHO** — input Número (parcial) |
| Orden fecha↔proveedor/cliente | query `orden` (fecha\|nombre) | selector | ✅ | **HECHO** — selector Orden Fecha/Cliente-Proveedor |
| Multi-select borrar / borrar todos | `DELETE` x N | sí (multi-select) | ✅ | "borrar todos" opcional |
| Mover a otro período | `POST …/{id}/mover` | sí | ✅ | (falta mover en lote) |
| Imprimir → Reportes | Libro/Reportes | link | ✅ | — |
| Exportar período (varios formatos) | export CSV/TXT/config | en Libro IVA | ✅ | — |
| Filtro "sólo sin condición IVA" | — | no | 🔴 | útil post-import; requiere query |
| Estadísticas (gráficas) | — | no | ⛔ | fuera de alcance actual |
| Forzar recálculo de períodos | totales derivados on-the-fly | N/A | ✅ | no aplica (nuestros totales son derivados) |

---

## B. Ficha de carga del comprobante (el núcleo) — Compra/Venta

| Campo (manual) | Modelo backend | ¿Front lo carga? | Estado | Acción |
|---|---|---|---|---|
| Fecha de emisión (auto última) | `fecha` | sí + auto-última | ✅ | **HECHO** — pre-carga la última fecha del período en alta |
| Proveedor/Cliente + búsqueda inteligente | `cliente_id`/`proveedor_id` + autofill | **typeahead** por nombre/CUIT | ✅ | **HECHO** — `SujetoTypeahead` (filtra por nombre/CUIT, autocompleta al elegir; ocasional si se tipea) |
| CUIT / Nro. doc | `cuit` | sí | ✅ | — |
| Tipo de documento (ventas) | `tipo_documento_id` | sí | ✅ | — |
| Tipo de comprobante | `tipo_comprobante_id` | sí | ✅ | — |
| **Rubro / Actividad** (indispensable F2002) | `rubro_id` (cat. rubros) **y** `actividad_id` (empresa_actividades) | **ambos** | ✅ | **HECHO** — select "Rubro (F2002)" en ambos modales (`api/rubros` → `/rubros`), separado de Actividad IVA/IIBB. Verificado E2E. |
| Letra (auto según tipo responsable) | `letra` | sí (texto libre) | 🟡 | derivar letra de tipo/condición |
| Punto de venta + Número | `punto_venta`, `numero` | sí | ✅ | — |
| **Número fin** (rango; tiques/Z) | `numero_fin` (ventas) | sí | ✅ | **HECHO** (ventas) |
| **CAI/CAE** (código + vto) | `cai`, `fecha_cai` | sí | ✅ | **HECHO** — CAI/CAE + vto en ambos modales; numero_fin sólo ventas. E2E OK |
| Imputación a cuentas (Debe/Haber) | — (no existe en modelo) | no | ⛔ | depende de módulo Contable |
| Total (transcripto, "Diferencia=0") | `total` **derivado por el motor** | no se ingresa | 🟡 | por diseño el total lo calcula el motor; documentar (no hay "diferencia=0") |
| Subtotal (Neto+IVA) helper de carga | — | no | 🟡 | helper opcional de UX |
| Neto Gravado N + alícuota + IVA | `venta_discriminaciones[]` (multi) | sí (multi-línea) | ✅ | — |
| Override importe IVA (por redondeo, "*") | `iva_importe` (override en el motor) | **sí** | ✅ | **HECHO** — celda IVA editable (placeholder=computado); backend: normalizador acepta iva_importe y el calculador lo respeta. E2E OK |
| **Percepción/Retención** (+ "Otros importes", multi) | `venta_percepciones[]` `{tipo_retencion_id, alicuota?, base?, importe?, provincia_id?}` + `PercepcionCalculator` | **sí (multi)** | ✅ | **HECHO** — sección Percepciones/Retenciones en ambos modales (catálogo `tipos-retencion`, importe calculado por el motor, integra el total). Verificado E2E. |
| Fecha de pago percepción | — (verificar) | no | ⛔ | verificar si el modelo lo tiene |
| Importe Exento | `exento` | sí | ✅ | — |
| Importe No Gravado (Factura C → auto) | `neto_no_grav` | sí + hint Factura C | 🟡 | **HECHO (hint)** — letra C muestra aviso 'sin IVA, cargá en No gravado'. No hay auto-move: nuestro modelo no ingresa Total (lo deriva el motor) |
| Moneda de pago + Cotización | `tipo_moneda_id`, `tipo_cambio` | sí | ✅ | — |
| Impuestos Internos | `imp_interno` | sí | ✅ | — |
| Campo Auxiliar (nombre configurable) | `campo_auxiliar` | sí (nombre fijo) | 🟡 | nombre configurable (Utilidades > Config) |
| **Reintegro (Factura T)** | `venta_discriminaciones.reintegro_t` | sí | ✅ | **HECHO** — LibroIvaRepository netea el débito (`iva − reintegro`); subdiario ya lo hacía; DDJJ F2002 cubierto. Front: detecta Factura T (cód FT) → reintegro=IVA + aviso. ⚠️ exports (RG3685/LID/DJ) reportan el reintegro **aparte** (manual), no neteado — pendiente ese mecanismo. E2E: total 1210, débito 0 |
| IVA incluido | `venta_discriminaciones.iva_inc_*` | no | 🟡 | el contador no reconoce "IVA incluido" → probablemente descartar |
| Concepto del comprobante | `concepto` (SMALLINT) | no | 🟡 | verificar uso/semántica |
| **Comprobantes asociados (NC/ND)** | `venta_comprobantes_asociados[]` (mig. 0030) | sí (ventas) | ✅ | **HECHO** — sección en VentaFormModal (tipo/letra/PV/número/CUIT/fecha). E2E OK |
| `concepto_dj` (compras, DJ IVA Simple) | col. `concepto_dj` | sí (**🟧 enum fijo 1-4**) | 🟧 | dejar enum o exponerlo del backend |

---

## C. Catálogos y ABM

| Función (manual) | Backend | Front | Estado | Acción |
|---|---|---|---|---|
| Alícuotas de IVA (21/10.5/27/2.5/5/0) | **no hay catálogo** (WSFE fijas) | `ALICUOTAS` **hardcode** en ambos modales | 🟧 | decidir: catálogo nuevo o dejar fijas (regulatorias) |
| Tipos de comprobante / documento / moneda / operación / condición / provincia | catálogos read-only | consumidos | ✅ | — |
| **Tipos de retención/percepción** (ABM por tenant) | `/tipos-retencion` CRUD | ABM (Utilidades) | ✅ | **HECHO** — pestaña con estándar read-only + propios editables. E2E OK |
| **Rubros / Actividades** (archivo) | `/rubros` CRUD | ABM (Utilidades) + usado en carga | ✅ | **HECHO** — pestaña Rubros + select en la carga. E2E OK |
| Config: nombre campo auxiliar compras/ventas | — (verificar `sueldos_empresa_config`-style) | no | ⛔ | requiere tabla de config por empresa |

---

## D. Reportes / Libro / DDJJ / Export / Import (mayormente hecho)

| Función (manual) | Backend | Front | Estado |
|---|---|---|---|
| Libro IVA Compras/Ventas (subdiario) | reportes ventas/compras | Libro IVA → Reportes | ✅ |
| Totales | `GET …/totales` | Resumen + Inicio | ✅ |
| F2002 (débito/crédito) | DDJJ F2002 | pestaña DDJJ | ✅ |
| IVA Simple (F2051 + apertura por actividad) | IVA Simple + DJ actividad | pestaña IVA Simple + descargas | ✅ |
| RG3685 → Libro IVA Digital | Libro IVA Digital writer | descarga | ✅ (RG3685 reemplazado) |
| Export TXT configurable | formatos por tenant | (¿UI?) | 🟡 verificar UI de formatos |
| Export a ATP | — | no | ⛔ sin spec |
| Reportes por Rubro/Actividad (F2002) | reportes | parcial | 🟡 depende de wire de `rubro_id` |
| Importar Mis Comprobantes / Excel | import CSV genérico | ImportarPage | ✅ (v1; ver pendientes-fase3) |
| Importar RG3685 / WebServices / .pem | — | no | ⛔ requiere spec/insumo |
| Constatar en línea | WS ARCA | no | ⛔ requiere WS + certificado |

---

## E. Utilidades (manual) — estado

| Función | Backend | Front | Estado |
|---|---|---|---|
| Visores de catálogos base | `/catalogos/{slug}` | Utilidades → Catálogos | ✅ |
| Archivo de Logs / auditoría | `/iva/auditoria` | Utilidades → Auditoría | ✅ |
| Configuración (campo auxiliar, etc.) | — | no | ⛔ requiere tabla config |
| Copias de seguridad | — | no | ⛔ operación/infra |
| Cambiar clave / usuarios | Admin/Auth | módulo Admin | 🟡 (fuera de IVA) |
| Exportar/importar a Visual Conta | módulo Contable | no | ⛔ depende de Contable |

---

## Resumen de acciones priorizadas (para wire)

**P1 — alto valor, backend listo:**
1. ✅ **HECHO — Percepciones / Retenciones por comprobante** en ambos modales (catálogo `tipos-retencion`,
   payload `{tipo_retencion_id, alicuota?, base?, importe?, provincia_id?}`; el motor calcula el importe e
   integra el total). Verificado E2E (venta con IIBB 5% → importe 50, total 1260).
2. ✅ **HECHO — Rubro / Actividad** (`rubro_id` del catálogo `/rubros`, indispensable para F2002).
   Select "Rubro (F2002)" en ambos modales, separado de Actividad IVA/IIBB. Verificado E2E.
3. ✅ **HECHO — CAI/CAE** (`cai`, `fecha_cai`) en ambos + **`numero_fin`** en ventas. Verificado E2E.
4. ✅ **HECHO — Comprobantes asociados (NC/ND)** (`comprobantes_asociados[]`, ventas). Verificado E2E.

**P2 — reglas/UX del manual:**
5. ✅ **auto-última-fecha, typeahead, Factura C (hint) y override de importe IVA HECHOS** (override: motor respeta iva_importe si viene; celda editable; E2E OK).
6. ✅ **HECHO — Factura T** (libro/DDJJ/totales/subdiario netean el débito; front setea reintegro=IVA + aviso). **HECHO** el reporte del reintegro en export: `GET …/reintegro-t` (total + #comprobantes); la pestaña Descargas avisa el reintegro total a cargar en el aplicativo de ARCA. E2E OK.
7. ✅ **HECHO — ABM de tipos_retencion y de rubros** (pestañas en Utilidades; estándar read-only + propios editables). E2E OK.
8. 🟡 **Búsqueda inteligente** (typeahead) de cliente/proveedor por nombre/CUIT.

**P3 — hardcodes / decisiones:**
9. 🟧 `ALICUOTAS` y `concepto_dj`: **recomendación = quedan fijas** (alícuotas = regulatorias/WSFE, no hay catálogo; concepto_dj = enum de 4 de la DJ IVA Simple). No es un hardcode 'a corregir' sino valores estables. Confirmar con el usuario.
10. 🟡 Nombre configurable del **campo auxiliar** (requiere tabla de config por empresa).

**Fuera de alcance / insumo externo:** imputación a cuentas + Visual Conta (módulo Contable),
constatación/WS/.pem/RG3685-import (WS + certificado), estadísticas gráficas, backups.

**Nota**: verificar en el backend antes de wire — (a) "fecha de pago" de percepción, (b) semántica
de `concepto`, (c) si `venta_retenciones/compra_retenciones` (tablas 0014/0015) están vivas o son
legacy (el `create` sólo persiste `percepciones` + `asociados`).
