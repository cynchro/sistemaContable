# Análisis: conversación con el contador (Federico Varetto) vs. estado del módulo IVA

> Fuente: `/home/alexis/Documentos/estudio haddad/Chat_de_WhatsApp_con_Federico_Varetto/`
> (chat 18/6 → 7/7/2026 + 5 capturas del Visual IVA + audios transcriptos + datasets de validación).
> Fecha del análisis: 2026-07-07.

## 0. Veredicto general de Federico
Le pasamos el MVP del front (link ngrok) y su devolución (audio 3/7 `WA0010`) fue:
**"está idéntico al Visual IVA"**. Confirmó que casi todo se usa. Marcó explícitamente
**3 faltantes** y 2 pedidos de UX. El núcleo de dominio (back) está validado por él en concepto.

---

## 1. YA ESTÁ (y quedó confirmado por el contador)

| Tema | Confirmación en el chat | Estado en el sistema |
|---|---|---|
| Percepciones **integran el total** (A1/A2) | Factura Saint-Gobain / "todo se suma al total" | ✅ migr. 0032, `PercepcionCalculator` |
| CF **100% computable** (A3) | Confirmado | ✅ |
| **F2002 → Portal IVA / IVA Simple** (A4) | "ya no de MIS APLICACIONES, ahora Portal IVA / IVA Simple" | ✅ IVA Simple F2051 |
| "IVA incluido" (A5) | No lo reconoce | ✅ se deja como está |
| Alícuotas / signo NC (A6/A7) | Confirmados | ✅ |
| Condición receptor → default **Consumidor Final** (A8) | RG 5616 | ✅ `CondicionReceptorResolver` |
| **Libro IVA Digital** (4 archivos) (A9) | 4 TXT reales de mayo/2026 | ✅ validado byte a byte |
| **Anulados**: NO se generan | "el efecto sobre el impuesto es nulo… la forma correcta es NC" | ✅ correctamente NO implementado |
| **TurIVA**: no se usa | Confirmado | ✅ omitido |
| **DJ IVA Simple por actividad — las 5 estrategias** | 26/6 (NAES): PV, alícuota, % fijos, por comprobante, por receptor | ✅ Fases 1-2-3 (migr. 0036-0038) |
| — por **punto de venta** (MAFAP/ANCASTI) | | ✅ |
| — por **alícuota** (construcción 10,5%→residencial / 21%→no residencial) | GRUPO MAZZUCO | ✅ + test `GrupoMazzucoDjE2ETest` |
| — por **% fijos** (ACEVEDO lubricentro) | | ✅ Fase 3 coeficientes |
| — por **comprobante** (Bruno Vega) | | ✅ override por comprobante |
| — por **receptor/CUIT** (Minera Galaxy → 99000) | | ✅ Fase 2 `actividad_receptor` |
| **Bienes de uso** separados | "el cliente informa, se discrimina" | ✅ `es_bien_uso` |
| **Concepto de compra** (servicios/alquiler/bienes de uso) | pto 3 del 26/6 | ✅ `concepto_dj` en compras |
| **Import CSV** de "Mis Comprobantes" ARCA + percepciones/IVA override | flujo 7/7 (bajan ZIP de ARCA, editan, re-suben) | ✅ importador + gap A (recién) |

**Conclusión:** todo el frente "dominio duro" que Federico validó en concepto ya está. Lo que
falta es **operativa/UX y reportes** que él nombró en los audios del 3/7 y 7/7.

---

## 2. LO QUE FALTA (nuevo, surge de los audios del 3/7 y 7/7)

### 🟠 FALTA 1 — Neto + IVA en la vista de listado (UX, rápido)
- **Pedido** (audio `WA0010` + IMG-0013/0015): en el listado de compras/ventas poder ver
  **NETO** e **IVA** por renglón **sin entrar** al comprobante.
- **Hoy:** el listado muestra `Fecha · Comprobante · Proveedor · CUIT · Total` (solo total).
- **Esfuerzo:** bajo. El endpoint del subdiario (`ReporteIvaRepository`) ya calcula neto/IVA;
  hay que exponerlos en el listado paginado (o agregar columnas derivadas) y sumarlos en el front.

### 🔴 FALTA 2 — Mayorización (imputación contable) + reportes de Mayor (grande, estructural)
- **Pedido** (audio `WA0010`, muy enfático — "lo uso bastante", "proveedores uso mucho, la
  mayorización, todo"): en el Visual IVA cada comprobante se **mayoriza** asignándole una
  **cuenta contable** (IMG-0015: "Cuenta Haber: 5008 SEGUROS PAGADOS", cuenta por línea de neto,
  y Cuenta Debe/Haber a nivel comprobante). Con eso arma dos reportes de "Otros Listados"
  (IMG-0016/0017) que usa todo el tiempo:
  1. **Resumen de Movimientos (Mayor de Cuentas)** — total por cuenta (ej. "Compra de combustible: $X").
  2. **Detalle de Movimientos de Cuentas** — qué comprobantes componen cada cuenta.
- **Hoy:** la **imputación contable fue podada** del modelo (`VTA_CTA_*`, debe/haber — ver
  `pendientes.md §F`). Solo quedó `rubro_id` a nivel cabecera (rubro F2002 — NO es la cuenta de
  mayorización). Existe el catálogo `cuentas` (plan de cuentas por empresa) pero no se imputa por
  comprobante ni hay reportes de mayor.
- **Esfuerzo:** medio-alto. Migración para agregar `cuenta_id` (debe/haber) por comprobante y/o
  por línea de discriminación; UI de imputación en los modales; 2 reportes nuevos (agregado +
  detalle por cuenta). Es el faltante más importante para su día a día.

### 🔴 FALTA 3 — Exportaciones IIBB por jurisdicción (SIFERE V4 y familia) (grande)
- **Pedido** (audios `WA0010`/`WA0018`/`WA0024` + IMG-0016/0017 + TXT de ejemplo): en "Otros
  Listados → Exportación IIBB" hay un desplegable con **muchos formatos por jurisdicción**:
  `Percepciones SI.FE.RE Convenio Multilateral V4` (el que más usa, mensual), Retenciones SIFERE
  V4, IIBB Santa Fe (ret/perc), Córdoba APIBCBA, SIRCAR (ret/perc), ATER, Catamarca, San Juan,
  Posadas-Misiones, ARCA Web 2.00. Genera un TXT por jurisdicción con las percepciones/retenciones
  que se cargaron en el sistema (para cargarlas en el sistema de convenio multilateral, separado
  de AFIP).
- **Caso real:** clientes de convenio (7-8: MAFAP, Acevedo, etc.). Ej. proveedor **R NETO SA**
  (agente local de Salta que no informa en COMARB) → las percepciones se cargan como Salta y se
  exporta el SIFERE. Formato de ejemplo (`Percepciones SIFERE -202605`):
  `917` (jurisdicción) + `CUIT` + `dd/mm/aaaa` + `PV(4)` + `número(8)` + `tipo(FA)` + `importe(coma decimal)`.
- **Hoy:** existe un **exportador TXT configurable** genérico (`iva_export_formatos`, migr. 0034)
  pero **no** los presets estandarizados por jurisdicción ni la lógica de seleccionar la
  jurisdicción/provincia de la percepción. El dato base (percepciones por tipo+provincia) SÍ lo
  tenemos (`reportes/percepciones`).
- **Esfuerzo:** medio. Empezar por **SIFERE Convenio Multilateral V4** (percepciones y
  retenciones), que cubre a la mayoría; los demás formatos se agregan de a uno con su spec.

### 🟡 FALTA 4 — Reportes por provincia para DDJJ anual de convenio (futuro)
- **Pedido** (audio `WA0024`): para la DDJJ **anual** de convenio necesita saber, de todo el año,
  cuánto compró/vendió **por provincia**, cruzado con el **mayor** (ej. "combustible en Salta:
  $X"). Requiere provincia del cliente/proveedor + mayorización (FALTA 2) + rango anual.
- **Esfuerzo:** medio, pero **depende de FALTA 2**. Es para el año que viene; no urge.

### 🟢 Menores / de UX que confirmó
- **Clientes**: casi no lo usan ("nunca le dimos utilidad") → despriorizar ABM de clientes.
- **Proveedores**: lo usan mucho (editar datos + mayorización) → priorizar.
- Reportes "muy chotos" del Visual → oportunidad de mejorarlos (él lo pidió: "meterle cabeza").

---

## 3. VALIDACIÓN PENDIENTE (datasets que mandó, para cerrar "IVA en producción")
No son features nuevas: son **pruebas de que lo que generamos coincide** con lo que él presenta.

1. **GRUPO MAZZUCO — mayo 2026 — VENTAS: ✅ VALIDADO E2E (2026-07-07)**. Se importaron los 6
   comprobantes del CSV de ARCA (`comprobantes_periodo_202605_ventas`) en una empresa/período
   limpios y se regeneraron `VENTAS_CBTE` y `VENTAS_ALICUOTAS` con nuestro sistema, comparándolos
   contra los TXT del Visual. **Resultado**: todo lo estructural (tipo, letra, PV, número, doc,
   CUIT, nombre, alícuota, IVA, moneda) es **byte-idéntico**. Las **únicas** diferencias son de
   **redondeo sub-peso**:
   - `VENTAS_CBTE`: 3 de 6 difieren **solo en el campo `total`** (±0,01–0,03). Causa: **nuestro
     sistema deriva `total = neto + iva`**, mientras el Visual **arrastra el total informado** por
     el comprobante/ARCA (que trae el redondeo propio de AFIP). Ej. cbte 434: neto 9.764.966,67 +
     iva 2.050.643,00 = **11.815.609,67** (nuestro) vs **11.815.609,70** (Visual) vs 11.815.609,65
     (ARCA) — los tres distintos.
   - `VENTAS_ALICUOTAS`: 5 de 6 idénticas; 1 (cbte 135) difiere solo en el **neto**: ARCA informa
     22.064.846,86 (lo que cargamos) y el Visual usó 22.064.846,90 (redondeo propio del Visual).
   - **Conclusión:** el pipeline es correcto. Las diferencias son centavos que AFIP tolera (el
     propio archivo del Visual tiene `total ≠ neto+iva` por 0,03 y ARCA lo aceptó). Para paridad
     **byte a byte** habría que **arrastrar el "total informado"** (columna Importe Total del
     comprobante/ARCA) en vez de derivarlo — mejora acotada y opcional (ver §5).
2. **GRUPO MAZZUCO — COMPRAS: ✅ VALIDADO E2E (129 de ARCA; 2026-07-07)**. Se importaron los 129
   comprobantes del CSV de ARCA (mapeando tipo, neto/IVA por alícuota, `cf_computable`, percepciones
   IIBB/IVA/municipales y `total_informado`) y se regeneraron `COMPRAS_CBTE`/`COMPRAS_ALICUOTAS`.
   - `COMPRAS_CBTE`: **99/129 byte-idénticas**. De las 30 con diferencia: **17 son solo el NOMBRE**
     del proveedor (la `ñ` de "COMPAÑIA" — el CSV de ARCA viene con encoding roto `COMPA�IA`, nuestro
     import tomó el carácter roto; cosmético, no afecta a AFIP). **13 son comprobantes (mayormente de
     SEGUROS/FIANZAS) donde `total ≠ neto+iva`**: el Visual guarda la diferencia (ej. 828,45) en un
     campo de "otros/imp. interno" que **el CSV de ARCA no desglosa** (la trae implícita en el total).
     Nuestro **total matchea** (vía `total_informado`); solo difiere dónde se ubica ese resto. Más un
     par de comprobantes exento-puro donde el Visual pone `cant_alic=0` y nosotros `1`.
   - `COMPRAS_ALICUOTAS`: **124/132 byte-idénticas**; las pocas diferencias son artefactos de
     alineación de comprobantes **multi-alícuota** en la comparación (mismo tipo/PV/número, distinta
     alícuota), no errores de monto.
   - **Conclusión:** el pipeline de compras reproduce correctamente montos, IVA, crédito fiscal y
     percepciones. Diferencias residuales: (a) encoding `ñ` del CSV fuente, (b) un "otros tributos" que
     ARCA no itemiza pero está en el total (seguros), (c) manejo de `cant_alic` en comprobantes
     exento-puro (posible refinamiento menor del writer, a confirmar con Federico).
   - Faltan las **6 compras manuales** (el "cai manual.xls") que se agregan fuera de ARCA — se cargan
     a mano/planilla; no bloquean la validación del subconjunto de ARCA.
3. **LAVALLE SRL — mayo 2026**: mismo método, pendiente (también con agregados manuales en compras).
4. **Prueba de subida al Portal IVA**: que Federico suba un archivo nuestro a ARCA y confirme que
   lo acepta (de su lado).

## 5. Mejora detectada por la validación — arrastrar el "total informado" (✅ HECHO)
Nuestro motor **deriva** el total (`neto + iva + percepciones`). Para el Libro IVA Digital, AFIP
espera el **total real del comprobante** (el de la factura / el que ARCA ya tiene), que puede diferir
del recalculado por redondeo. **Implementado** (migración 0041): campo opcional `total_informado` en
`ventas`/`compras`; el Libro IVA Digital usa `COALESCE(total_informado, total)` en el campo `total`;
el importador lo mapea desde la columna "Importe Total" del CSV de ARCA. Requests + whitelists de los
repos actualizados. 53 tests verdes.
- **Efecto medido** (re-validación MAZZUCO ventas con `total_informado` = total de ARCA):
  `VENTAS_CBTE` pasó de **3/6 → 4/6** líneas byte-idénticas. Los 2 restantes (cbte 434 y 135) son
  casos donde **el propio archivo del Visual difiere del CSV de ARCA** (por 0,01–0,05): Visual
  arrastra un total que no es ni el de ARCA ni `neto+iva` (redondeo/historial interno del Visual,
  no reproducible sin sus datos). Nosotros ahora matcheamos **ARCA** (la fuente canónica).
- Queda 1 diferencia en `VENTAS_ALICUOTAS` (cbte 135): el **neto** de ARCA (22.064.846,86) vs el que
  usó el Visual (22.064.846,90) — redondeo propio del Visual sobre el neto, fuera de nuestro control.
- **Todo dentro de la tolerancia de AFIP** (el propio archivo del Visual tiene `total ≠ neto+iva` y
  ARCA lo aceptó). El pipeline queda **validado** para ventas.

---

## 4. PLAN SUGERIDO (priorizado)

| # | Ítem | Tipo | Prioridad | Depende de |
|---|---|---|---|---|
| 1 | **Neto + IVA en el listado** de compras/ventas | Front | 🥇 Alta (rápido, alto impacto UX) | — |
| 2 | **Validar LAVALLE mayo/2026** (regenerar Libro IVA Digital y comparar) | Verificación | 🥇 Alta (cierra "producción") | — |
| 3 | **SIFERE Convenio Multilateral V4** (percep. + retenc.) | Back+Front | 🥈 Alta (mensual, 7-8 clientes) | datos de percepción por provincia (ya están) |
| 4 | **Mayorización**: `cuenta_id` por comprobante + 2 reportes de Mayor | Back+Front | 🥈 Alta (uso diario) | migración + UI |
| 5 | Resto de exportaciones IIBB por jurisdicción (Santa Fe, Córdoba, SIRCAR, ATER…) | Back | 🥉 Media | spec de cada layout |
| 6 | Reportes **por provincia** (anual de convenio) | Back+Front | 🥉 Media/Baja (futuro) | #4 (mayorización) |
| 7 | Pulido de reportes en general ("meterle cabeza") | Front | 🥉 Media | — |

**Orden recomendado para arrancar:** #1 (rápido, lo ve enseguida) → #2 (validación que cierra
producción) → #3 (SIFERE, dolor mensual concreto) → #4 (mayorización, la más estructural).

### Preguntas abiertas para Federico (para no inventar)
- **Mayorización:** ¿la cuenta se imputa **por comprobante** (una debe / una haber) o **por línea
  de neto** (como muestra IMG-0015)? ¿Alcanza con una sola cuenta por comprobante para los reportes?
- **SIFERE V4:** confirmar el **layout exacto** (posiciones/anchos) — tenemos un ejemplo pero
  conviene el diseño de registro oficial. ¿La jurisdicción sale de la **provincia de la percepción**
  cargada en el comprobante?
- **LAVALLE:** ¿qué cosas "manuales" agregan sobre lo de ARCA (para reproducirlo)?
