# DJ IVA Simple — Apertura de otros conceptos (distribución por actividad)

Fuente oficial: `softContable/guia-generacion-dj/LID-Ajustes-y-otros-conceptos-para-generar-la-DJ.pdf`
(ARCA, "IVA simple — Especificación de Importaciones / Apertura de otros conceptos") +
ejemplo `softContable/guia-generacion-dj/ejemplo_operaciones_df (3).csv`.

El Portal IVA permite **importar** la apertura de operaciones de la DJ IVA Simple mediante
archivos CSV. Son **4 archivos** independientes, uno por grupo de conceptos. Formato común:
separador de campos `;`, separador decimal `,` (sin separador de miles), importes con hasta
2 decimales, una línea por registro terminada en **CRLF**.

## Los 4 archivos

### 1) Operaciones que generan Débito Fiscal (8 campos)
`Actividad ; Tipo de Operación ; Tipo de sujeto comprador ; Código de Alícuota ; Monto Neto Gravado ; Débito Fiscal Facturado ; Débito Fiscal O.D.P. ; Monto Neto Exento o No Gravado`
- **Tipo de Operación**: 1 = Venta de Cosas Muebles/Obras/Locaciones/Servicios · 2 = Venta de
  Bienes de Uso · 3 = Operaciones No Gravadas o Exentas (excepto exportaciones).
- **Tipo de sujeto comprador**: 1 = Responsable Inscripto · 2 = Monotributo · 3 = Consumidor
  Final/Exento/No Alcanzado. Vacío para tipo op = 3.
- **Código de Alícuota**: 3 = 0% · 9 = 2,5% · 8 = 5% · 4 = 10,5% · 5 = 21% · 6 = 27%. Vacío para tipo op = 3.
- Para tipo op = 3 sólo se completa el Monto Neto Exento o No Gravado.

### 2) Operaciones que generan Restitución de Débito Fiscal (7 campos)
Mismas columnas, pero **Tipo de Operación**: 1 = ventas (incl. bienes de uso) · 2 = no gravado/exento.
Es lo que **restituye** débito → en nuestro modelo, las **notas de crédito de ventas** (signo −1).

### 3) Operaciones que generan Crédito Fiscal (5 campos)
`Concepto ; Código de Alícuota ; Monto Neto Gravado ; Crédito Fiscal Facturado ; Crédito Fiscal Computable`
- **Concepto**: 1 = Compras de Bienes (excepto Bienes de Uso) · 2 = Locaciones · 3 = Prestaciones
  de Servicios · 4 = Inversiones de Bienes de Uso.
- **No lleva actividad** (la spec no la pide en compras).

### 4) Operaciones que generan Restitución de Crédito Fiscal (4 campos)
`Concepto ; Código de Alícuota ; Monto Neto Gravado ; Crédito Fiscal Facturado`.
Restituye crédito → las **notas de crédito de compras** (signo −1).

## Mapeo desde nuestro modelo

| Dato de la DJ              | Origen en el ecosistema                                              |
|---------------------------|---------------------------------------------------------------------|
| Actividad                 | `empresas.actividad1_id` (actividad **principal**) — ver supuestos   |
| Tipo de sujeto comprador  | `condiciones_iva` del receptor: RI(1)→1, Monotributo(3)→2, resto→3    |
| Código de alícuota        | `iva_alicuota` (%) → código AFIP                                      |
| Neto gravado / IVA / CF    | `venta_discriminaciones` / `compra_discriminaciones` (cf_computable) |
| Exento / no gravado       | `ventas.exento + ventas.neto_no_grav` (a nivel comprobante)          |
| Débito vs Restitución     | `tipos_comprobante.signo` (NC = −1)                                   |

## Supuestos de v1 (a confirmar con el contador → `preguntas.md` E)
Decisión del usuario (programador): implementar v1 con supuestos documentados, sin tocar el
schema ni los formularios de carga. El exporter es fiel para una empresa **monoactividad**.

1. **Actividad**: toda la operatoria de débito/restitución se imputa a la **actividad principal**
   de la empresa (`actividad1_id`). La distribución real multi-actividad requeriría capturar la
   actividad **por comprobante** (hoy no se guarda; campo podado en la migración 0014). Sin
   actividad principal cargada, el archivo de débito/restitución da 422.
2. **Sin "Venta de Bienes de Uso" (tipo op 2 del débito)**: todo lo gravado va como tipo op 1.
   No distinguimos bienes de uso (no hay flag en el comprobante).
3. **Crédito Fiscal con Concepto = 1** (Compras de Bienes). No clasificamos
   locaciones/servicios/inversiones de bienes de uso (no se captura).
4. **Débito Fiscal O.D.P. (dación en pago) = 0**: no modelamos dación en pago.
5. **Exportaciones excluidas**: el cliente del exterior (`condiciones_iva` id 9) se saltea
   ("excepto exportaciones").

## Implementación v1 (exporter base — HECHO)
- `app/Modules/Iva/Export/DjIvaSimpleWriter.php` — formateador puro (los 4 layouts, código de
  alícuota, tipo de sujeto, recorte de decimales, CRLF). Tests: `tests/Unit/Modules/Iva/DjIvaSimpleWriterTest.php`.
- `app/Modules/Iva/Repositories/DjIvaSimpleRepository.php` — agregación SQL por signo
  (ventas gravado/no gravado, compras gravado con cf_computable).
- `app/Modules/Iva/Services/DjIvaSimpleService.php` — valida empresa/período, resuelve actividad.
- `GET /empresas/{id}/periodos/{pid}/dj-iva-simple/{archivo}` (RBAC `iva.libro`), descarga CSV.
- Tests feature: `tests/Feature/DjIvaSimpleExportTest.php`.

---

## v2 — modelo REAL por actividad (respuestas del contador, 2026-06-26)

Fuente: respuestas A15 (ver `preguntas.md`) + ejemplos reales en `softContable/preguntas2/`
(`NAES - LISTADO CON DESCRIPCION.pdf` = nomenclador; 3 Excel de clientes reales). La distribución
por actividad **no cambia el IVA**, pero determina la alícuota de **IIBB** y **tasa municipal**, y
la apertura que pide la DJ. Las empresas son **multi-actividad** (código NAES).

### Estrategias para asignar la actividad a un comprobante
Un cliente puede combinar varias; el sistema debe soportarlas y permitir override por comprobante:

1. **Por punto de venta** (la más común). Mapa por empresa `{punto_venta → actividad NAES}`. Se
   agrupan los comprobantes por el PV → su actividad. Evidencia: `CALCULADORA ANCASTI` (451110→PV
   08/10/12/15; 451210→PV 11; 452990→PV 14; 453291→PV 13) y `LIQUIDADOR MAFAP` (475290→PV 11/30/45;
   471120 supermercado→PV 40/31/32/…). **La mayoría de los clientes: 2-3 actividades, 2-3 PV.**
2. **Por alícuota** (construcción). 10,5% → residencial (NAES 410011); 21% → no residencial (410021).
3. **Porcentajes fijos** (cuando un PV vende de todo y no hay sistema de gestión). Coeficientes por
   empresa `{actividad → coeficiente}` (suman 1) aplicados al **neto del período** por condición de
   IVA del receptor y signo. Evidencia: `ACEVEDO MARIO` (hoja IVA: tabla PARTICIPACION con códigos
   NAES y coeficientes; neto×coef por RI/CF/NC). Es distribución a nivel **período**, no por comprobante.
4. **Factura por factura / manual**. Actividad cargada por comprobante (clientes con pocas facturas
   y varias actividades; y como **override** de cualquier estrategia). Ej. Bruno Vega.
5. **Por receptor (CUIT)**. Mapa `{cliente → actividad}` (ej. todo a Minera Galaxy Lithium → 99000).

### Otros cambios que pide A15
- **Venta de bienes de uso**: flag por comprobante de venta (`es_bien_uso`). El cliente informa; va
  como tipo de operación **2** del archivo de débito. No paga IIBB ni tasa municipal.
- **Compras — concepto** (los 4 de la DJ): clasificación por compra → 1 bienes / 2 locaciones /
  3 servicios / 4 inversiones de bienes de uso. Pistas: servicios (luz/agua/gas/internet/teléfono,
  alícuota 27%), alquileres (por proveedor), bienes de uso (por proveedor + aviso del cliente).
- **Dación en pago**: confirmado 0 (no aplica).

### Plan de implementación (propuesto, por fases)
- **Catálogo NAES**: tabla `actividades_naes` (código + descripción) sembrada desde el PDF, o, más
  liviano, las actividades **por empresa** que el contador carga (cada empresa tiene 2-3).
- **Por comprobante**: `ventas.actividad_id` + `ventas.es_bien_uso`; `compras.actividad_id` +
  `compras.concepto_dj`. Override manual siempre disponible.
- **Config de estrategia por empresa** + tablas de mapeo: `actividad_punto_venta`,
  `actividad_alicuota`, `actividad_receptor`, `actividad_coeficiente` (porcentajes fijos).
- **Resolver de actividad** (puro): dado un comprobante + la config de la empresa, devuelve la
  actividad (precedencia: actividad del comprobante → estrategia configurada → default).
- **Reescribir `DjIvaSimpleRepository`/`Service`**: agrupar por actividad real (no la principal),
  emitir bienes de uso como tipo op 2, y el concepto real en compras. Para "porcentajes fijos",
  distribuir el neto del período por coeficientes.
- **UI**: ABM de actividades por empresa, config de estrategia + mapeos, y los campos nuevos
  (actividad / bien de uso / concepto) en la carga de ventas y compras.

**Fase 1 sugerida** (cubre a la mayoría): catálogo de actividades por empresa + **por punto de
venta** + **manual/override** + flag bien de uso + concepto de compras + reescritura del exporter.
**Fase 2**: por alícuota (construcción) y por receptor. **Fase 3**: porcentajes fijos (distribución
a nivel período).
