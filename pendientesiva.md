# IVA — lo que necesito de vos para cerrarlo al 100%

> **El código de IVA ya está completo** (comprobantes, libro IVA, DDJJ F2002 + IVA Simple, Libro
> IVA Digital, DJ por actividad con las 5 estrategias, reportes, exportaciones, auditoría, AFIP
> WSAA/padrón/WSFE, y todo el frontend). **No hay dudas pendientes para el contador.**
>
> Lo que queda para decir "IVA 100% terminado y probado en producción" **no es código**: son
> **insumos, trámites y decisiones que dependen de vos / del estudio**. Esta es la lista, ordenada
> por impacto.

---

## 1. 🟡 Certificado de AFIP/ARCA (factura electrónica) — HOMOLOGACIÓN HECHA, falta PRODUCCIÓN

**Avance (2026-06):** ✅ ya hay **herramientas y guía** para el certificado y **está validado en vivo
contra homologación** (CUIT 23321452639). Comandos CLI: `php modux afip:cert-key` (clave) y
`php modux afip:cert-csr "<Razón Social>"` (CSR). Guía paso a paso en
`backend/app/Modules/Iva/Afip/README.md`. El WSAA obtiene TA contra `wsfe` en homologación.

**Lo que falta para PRODUCCIÓN:**
1. **Tramitar el certificado de PRODUCCIÓN** en el WSASS de ARCA (subir el `request.csr`, descargar
   el certificado y **autorizar los servicios** `wsfe` y, si se usa padrón, `ws_sr_padron_a5`).
2. Apuntar el `.env` de producción a ese certificado (`AFIP_ENV=prod`, `AFIP_CUIT`, `AFIP_CERT_PATH`,
   `AFIP_KEY_PATH`).
3. **Emitir una factura real de prueba** (numerar + pedir CAE) y confirmar el circuito completo.

**✅ Circuito completo de CAE validado en homologación (2026-07-01):** no solo FEDummy/WSAA — se
ejecutó la cadena real WSAA→wsfe + `FECompUltimoAutorizado` + `FECAESolicitar` con una Factura B a
Consumidor Final (neto 100 + IVA 21 = 121) y **ARCA devolvió CAE** (resultado `A`, CAE
`86260518505470`, vto `2026-07-11`), sin observaciones ni errores. Queda probado que el certificado,
la firma CMS, el TA cacheado, la numeración y el mapper del `FeCAEReq` funcionan end-to-end. Lo único
que falta para producción es el trámite del certificado de PRODUCCIÓN (abajo).

---

## 2. 🟢 Validar la DJ por actividad contra un caso real del estudio — HECHO (construcción)

**Estado:** ✅ **validado END-TO-END** con un caso real del estudio. El contador pasó
**GRUPO MAZZUCO ARQUITECTOS ASOCIADOS SRL** (constructora, mayo 2026, carpeta `preguntas01-08-2026/`),
que combina **por receptor** (SANATORIO JUNÍN + DROGUERÍA MITRE → alquiler 681098) **y por alícuota**
(resto → construcción 21%→410021 / 10,5%→410011), con precedencia receptor→alícuota. Se cargó en el
sistema y se generó el CSV de la DJ IVA Simple por el código real: **coincide exacto** con la
distribución manual del contador (`DISTRIBUCION IVA.xlsx`); neto construcción = débito − restitución =
27.567.059,81. Test: `backend/tests/Feature/GrupoMazzucoDjE2ETest.php`.

**Cobertura de las 5 estrategias:** por punto de venta (MAFAP/ANCASTI) y porcentajes fijos (ACEVEDO)
ya estaban validadas con las planillas del contador; por alícuota + por receptor ahora validadas
end-to-end con GRUPO MAZZUCO; la manual (factura por factura) no tiene algoritmo. **Nada pendiente
acá salvo, si se quiere, sumar más casos reales de otros rubros.**

---

## 3. 🟡 Probar la importación de un archivo generado en el Portal IVA (paso NUESTRO)

**No es un pedido al contador** (él no tiene acceso al sistema nuevo, no puede generar ni subir un
archivo del sistema). Es una **validación interna nuestra**, dependiente del punto 2: cuando carguemos
un período real, el sistema genera el archivo (4 CSV de la DJ IVA Simple y/o 4 TXT del Libro IVA
Digital) y **lo subimos nosotros al Portal IVA** (con la clave fiscal del estudio) para confirmar que
**ARCA lo acepta sin error de formato**. Si algún campo no le gusta a ARCA, se ajusta.

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
| 1 | Certificado AFIP: ✅ homologación + tooling + **circuito CAE validado en vivo** · falta solo el trámite de **producción** | Trámite | Factura electrónica en producción |
| 2 | ✅ **HECHO** — DJ por actividad validada end-to-end con caso real (GRUPO MAZZUCO, construcción) | Insumo de datos | — |
| 3 | Subir un archivo generado al Portal IVA (**paso nuestro**, sale del #2) | Prueba operativa | Confirmar formato vs. ARCA |
| 4 | ¿Se construye módulo Contable? | Decisión | Export a Contable |
| 5 | Acceso a la DB del Visual | Insumo de datos | Migración del histórico |
| 6 | Sembrar NAES completo (opcional) | Decisión | Comodidad de carga |

**Nada de esto es código de IVA pendiente**: el módulo está terminado. Son insumos/trámites/decisiones
para validarlo en producción.
