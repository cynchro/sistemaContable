# Preguntas para Federico — 07/07/2026

> Surgen del análisis de la conversación completa (18/6 → 7/7) y de la validación E2E contra los
> datos reales de GRUPO MAZZUCO (mayo 2026). Contexto de cada una en
> `docs/ingenieria-inversa/analisis-chat-federico-julio2026.md`.
> Formato: cada pregunta trae **contexto**, la **pregunta** concreta y **lo que hoy asumimos**.

---

## 1. 🔴 Mayorización — ¿qué monto y a qué nivel?
**Contexto:** ya se puede imputar cada comprobante a una **Cuenta Debe** y una **Cuenta Haber** del
plan, y con eso se arma el Mayor de Cuentas (resumen por cuenta + detalle). Hoy el importe del
movimiento es el **total del comprobante** (con signo: las NC restan).
**Pregunta:**
1. En el reporte de mayor, ¿querés ver el **total** del comprobante, o el **neto** (el gasto sin IVA)?
   En contabilidad el neto va a la cuenta de gasto y el IVA a "IVA crédito fiscal" por separado.
2. ¿Alcanza con **una cuenta por comprobante** (Debe/Haber a nivel comprobante), o necesitás imputar
   **por línea** (cuando un comprobante tiene varios conceptos/cuentas distintas)?
**Hoy asumimos:** una cuenta Debe + una Haber por comprobante; el movimiento es el total con signo.

## 2. 🟡 Mayor por rango de fechas / anual (convenio)
**Contexto:** para la DDJJ **anual** de convenio dijiste que necesitás saber, de todo el año, cuánto
compraste/vendiste **por provincia** cruzado con el **mayor** (ej. "combustible en Salta: $X"). Hoy el
Mayor es **por período (mensual)**.
**Pregunta:** ¿querés un reporte de mayor **por rango de fechas** (o anual) que además cruce con la
**provincia** del cliente/proveedor? ¿Qué columnas te sirven (cuenta, provincia, CUIT, total)?
**Hoy asumimos:** mayor mensual, sin corte por provincia (queda para v2).

## 3. 🟡 Compras de seguros — el "resto" que no es neto ni IVA
**Contexto:** en la validación de MAZZUCO, varias compras de **seguros (FIANZAS, etc.)** tienen
`total ≠ neto + IVA`. Ejemplo: total 91.958,01, neto 75.313,69, IVA 15.815,87 → sobran **828,45** que
ARCA no desglosa en ninguna columna, pero el Visual sí lo ubica en un campo de "otros".
**Pregunta:** ese importe que sobra en las facturas de seguros (≈ tasas/impuestos no discriminados),
¿qué es y **dónde debe informarse** en el Libro IVA Digital (otros tributos, imp. interno, etc.)?
**Hoy asumimos:** el total lo tomamos del informado por ARCA (`total_informado`), pero no ubicamos ese
"resto" en una columna aparte.

## 4. 🟡 Comprobantes exento / no gravado puro — cantidad de alícuotas
**Contexto:** en compras **sin neto gravado** (solo exento/no gravado), el Visual pone
`cantidad de alícuotas = 0` y **no** genera línea de alícuota; nuestro sistema pone `1` y genera una
línea en cero.
**Pregunta:** ¿está bien que esos comprobantes vayan con `cant_alic = 0` y sin línea de alícuota (como
el Visual), o el aplicativo de ARCA acepta ambos?
**Hoy asumimos:** generamos 1 línea en cero (funciona, pero difiere del Visual).

## 5. 🟢 LAVALLE / MAZZUCO — agregados manuales en compras
**Contexto:** al validar, las **ventas** salen enteras de ARCA, pero en **compras** hay comprobantes
**manuales** que agregás fuera de ARCA (el "cai manual.xls"): en MAZZUCO, 129 de ARCA vs 135 finales.
**Pregunta:** ¿qué tipo de comprobantes son esos agregados manuales y de dónde salen (facturas sin CAE,
tickets, servicios)? Con un ejemplo del "cai manual" reproducimos el 100% del período.
**Hoy asumimos:** validamos el subconjunto que viene de ARCA; los manuales se cargan a mano.

## 6. 🟢 SIFERE / exportaciones IIBB — cuáles priorizar
**Contexto:** ya está **SIFERE Convenio Multilateral V4 — Percepciones** (validado byte a byte contra
tu ejemplo). El desplegable del Visual tiene muchos más formatos por jurisdicción.
**Pregunta:** de los que aparecen en "Exportación IIBB", ¿cuáles usás de verdad y conviene sumar?
(SIFERE V4 **Retenciones**, IIBB **Santa Fe** ret/perc, **Córdoba APIBCBA**, **SIRCAR** ret/perc,
**ATER**, Catamarca, San Juan, Posadas-Misiones, ARCA Web 2.00). **Necesitamos un TXT de ejemplo real
de cada uno** (uno que ya hayas presentado): revisamos el legacy y los layouts byte a byte de estos
formatos NO están en la base ni en el manual (estaban en el programa de escritorio), así que sin un
ejemplo no los podemos reproducir con exactitud (no queremos inventar el formato). Con el TXT de
ejemplo los replicamos byte a byte como hicimos con el SIFERE Percepciones.
**Nota adicional para SIFERE Retenciones:** hoy modelamos percepciones **sobre comprobantes** (compras);
las **retenciones sufridas** suelen aplicarse sobre **pagos**, que todavía no modelamos. Para SIFERE
Retenciones necesitaríamos definir de dónde salen esos datos (¿los cargás como una percepción más, o
van por otro lado?).
**Hoy asumimos:** solo SIFERE V4 Percepciones (validado byte a byte); el resto espera tu ejemplo.

## 7. 🟢 Prueba de subida al Portal IVA (pendiente de tu lado)
**Contexto:** los archivos que genera el sistema (Libro IVA Digital, DJ IVA Simple, SIFERE) coinciden
con los tuyos. Falta la confirmación final de ARCA.
**Pregunta:** cuando puedas, subí **un archivo generado por nuestro sistema** a Portal IVA con un
período de prueba y avisanos si ARCA lo acepta o si tira algún error de formato.
**Hoy asumimos:** formato correcto (validado byte a byte contra tus archivos), falta el OK de ARCA.
