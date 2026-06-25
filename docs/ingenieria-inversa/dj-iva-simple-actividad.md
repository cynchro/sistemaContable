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

## Implementación
- `app/Modules/Iva/Export/DjIvaSimpleWriter.php` — formateador puro (los 4 layouts, código de
  alícuota, tipo de sujeto, recorte de decimales, CRLF). Tests: `tests/Unit/Modules/Iva/DjIvaSimpleWriterTest.php`.
- `app/Modules/Iva/Repositories/DjIvaSimpleRepository.php` — agregación SQL por signo
  (ventas gravado/no gravado, compras gravado con cf_computable).
- `app/Modules/Iva/Services/DjIvaSimpleService.php` — valida empresa/período, resuelve actividad.
- `GET /empresas/{id}/periodos/{pid}/dj-iva-simple/{archivo}` (RBAC `iva.libro`), descarga CSV.
  `archivo`: `debito-fiscal | restitucion-debito | credito-fiscal | restitucion-credito`.
- Tests feature: `tests/Feature/DjIvaSimpleExportTest.php`.
