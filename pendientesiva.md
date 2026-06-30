# IVA — lo que necesito de vos para cerrarlo al 100%

> **El código de IVA ya está completo** (comprobantes, libro IVA, DDJJ F2002 + IVA Simple, Libro
> IVA Digital, DJ por actividad con las 5 estrategias, reportes, exportaciones, auditoría, AFIP
> WSAA/padrón/WSFE, y todo el frontend). **No hay dudas pendientes para el contador.**
>
> Lo que queda para decir "IVA 100% terminado y probado en producción" **no es código**: son
> **insumos, trámites y decisiones que dependen de vos / del estudio**. Esta es la lista, ordenada
> por impacto.

---

## 1. 🔴 Certificado de homologación de AFIP/ARCA (factura electrónica en vivo)

**Qué falta:** la emisión de CAE, el padrón y el WSAA están programados y validados contra el
servidor de ARCA (FEDummy dio OK en homologación), pero para **emitir de verdad** hace falta un
certificado. Hoy no podemos probar el circuito completo (numerar + pedir CAE) sin él.

**Qué necesito de vos:**
1. **Tramitar el certificado de homologación** en el portal de ARCA:
   - Generar el CSR (pedido de certificado) — te paso el comando exacto si querés.
   - Subirlo a ARCA y **asociar el certificado a los web services** `wsfe` (factura electrónica) y
     `ws_sr_padron_a5` (padrón).
2. **Pasarme los 2 archivos** que genera el trámite: el **certificado** (`.crt`/`.pem`) y la **clave
   privada** (`.key`), más el **CUIT del emisor** de prueba.
3. (Después) repetir lo mismo con el certificado de **producción** cuando se vaya a usar real.

**Con eso** configuro `AFIP_CUIT`, `AFIP_CERT_PATH`, `AFIP_KEY_PATH`, `AFIP_ENV` y probamos el
circuito completo de factura electrónica en vivo.

---

## 2. 🟡 Validar la DJ por actividad contra un caso real del estudio

**Qué falta:** implementamos las 5 formas de repartir por actividad (punto de venta, manual, por
alícuota/construcción, por receptor, porcentajes fijos) y los validamos con tests, **pero contra
ejemplos armados por nosotros**, no contra una presentación real.

**Qué necesito de vos (idealmente del contador):** para **1 o 2 clientes** de cada tipo, un período
real con:
- los **datos de ventas y compras** de ese mes (o el subdiario/Libro IVA), y
- el **resultado que ellos presentan hoy** (su Excel o los CSV que suben al Portal IVA).

Casos que conviene cubrir (son los que ya analizamos): **MAFAP/ANCASTI** (por punto de venta),
**ACEVEDO** (porcentajes fijos) y un cliente de **construcción** (por alícuota).

**Para qué:** comparar **lo que genera el sistema vs. lo que presentan** y confirmar que coincide
(o corregir diferencias finas de redondeo/criterio). Es el último paso para dar la apertura por
actividad como "probada en producción".

---

## 3. 🟡 Probar la importación de un archivo generado en el Portal IVA

**Qué necesito de vos:** que **suban al Portal IVA un archivo CSV generado por el sistema** (los 4
de la DJ IVA Simple y/o los 4 TXT del Libro IVA Digital) con un período de prueba y confirmen que
**ARCA los acepta sin error de formato**. Si algún campo no le gusta a ARCA, me pasás el mensaje y
lo ajusto.

**Para qué:** validar el formato de los archivos contra el importador real de ARCA (no solo contra
los ejemplos del instructivo).

---

## 4. 🟢 Decisión: exportación a "Contable" (asientos)

**Qué falta:** el legacy permitía exportar las ventas/compras como **asientos al sistema Contable**
(`EXPOVCONTA`). Eso **depende de un módulo Contable que todavía no existe** en el ecosistema.

**Qué necesito de vos:** una **decisión de alcance** — ¿se va a construir el módulo Contable y se
quiere ese export? Si sí, lo planificamos aparte (no es IVA puro). Si no, lo damos por **fuera de
alcance** y queda cerrado.

---

## 5. 🟢 Migración de datos reales (si arrancan con el histórico)

**Qué falta:** para empezar a operar con los datos que ya tienen en el Visual IVA, hay que migrar.
El plan está en `softContable/migracion/`, pero las migraciones extraídas están incompletas.

**Qué necesito de vos:** acceso a la **base de datos de producción del Visual IVA** (o **dumps**),
para mapear y cargar los datos reales (dedup de contribuyentes por CUIT, etc.). Es un proyecto en
sí mismo; lo encaramos cuando definan que quieren migrar el histórico.

---

## 6. 🟢 (Opcional) Catálogo NAES completo

**Hoy:** cada empresa carga a mano sus 2-3 actividades NAES (alcanza para operar). Tenemos el PDF
del nomenclador completo (`softContable/preguntas2/NAES - LISTADO CON DESCRIPCION.pdf`).

**Qué necesito de vos:** decidir si querés que **sembremos el nomenclador NAES completo** (para
elegir de una lista con buscador en vez de tipear el código). Es una mejora de comodidad, no un
bloqueo. Si lo querés, lo hago.

---

## Resumen

| # | Pendiente | Tipo | Bloquea |
|---|-----------|------|---------|
| 1 | Certificado de homologación AFIP | Trámite + archivos | Factura electrónica en vivo |
| 2 | Período real + resultado del contador | Insumo de datos | Validar DJ por actividad |
| 3 | Subir un archivo generado al Portal IVA | Prueba operativa | Confirmar formato vs. ARCA |
| 4 | ¿Se construye módulo Contable? | Decisión | Export a Contable |
| 5 | Acceso a la DB del Visual | Insumo de datos | Migración del histórico |
| 6 | Sembrar NAES completo (opcional) | Decisión | Comodidad de carga |

**Nada de esto es código de IVA pendiente**: el módulo está terminado. Son insumos/trámites/decisiones
para validarlo en producción.
