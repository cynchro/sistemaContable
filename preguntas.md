# Preguntas para el contador

> Dudas de **dominio contable/impositivo** que surgieron construyendo el sistema. Las
> implementamos con un supuesto razonable para no frenar, pero necesitamos confirmarlas para
> no quedar con algo mal modelado. Cada pregunta trae el **contexto**, la **pregunta concreta**
> y **qué asumimos hoy** (así alcanza con decir "está bien" o corregir).
>
> Marcadores: 🔴 = nos cambia el cálculo / bloquea algo · 🟡 = ajuste o confirmación ·
> 🟢 = necesitamos una tabla/instructivo oficial · ✅ = ya respondida.
>
> **Cómo está organizado (importante):** este archivo tiene **dos zonas**:
> 1. **PENDIENTES (abiertas)** — acá se escriben las respuestas (línea `**Respuesta:**`) y se
>    agregan las **preguntas nuevas** que vayan surgiendo. Cuando una se responde, se mueve al archivo.
> 2. **RESPONDIDAS (archivo)** — registro histórico de lo ya contestado. **No escribir acá.**
>
> Estado actual: **IVA está 100% respondido** (zona archivo, A1–A15 + A11-bis). Abiertas quedan
> **Sueldos (B)**, **Honorarios (C)**, **Fiscal (D)** y **Satélite Visual IVA / alertas
> estadísticas (E)**.

---

# 🟢 PENDIENTES (abiertas)

> Acá van las respuestas y cualquier pregunta nueva.

## B) Sueldos (liquidación)

> La lógica de liquidación del sistema viejo estaba en el programa de escritorio (no en la base
> de datos), así que la reconstruimos desde las fórmulas y el modelo. Lo ideal para confirmar
> esto es **un recibo de sueldo real de ejemplo**. Todas PENDIENTES (planteadas 2026-06-16/20).

### B1. 🟡 Antigüedad
**Pregunta:** ¿la antigüedad se calcula por **años cumplidos** y se paga **1% por año**? ¿Hay
convenios con otra escala (2% por año, por tramos, etc.)?
**Hoy asumimos:** años cumplidos, 1% por año (variable ANTIG = cantidad de años).
**Respuesta:**

### B2. 🟡 Conceptos no remunerativos
**Pregunta:** ¿el sistema debe acumular los conceptos **no remunerativos** por separado de los
remunerativos (para topes, aportes, etc.)?
**Hoy asumimos:** sí, se acumulan aparte (variable NOREM).
**Respuesta:**

### B3. 🟡 Tipos de concepto
**Pregunta:** ¿los conceptos se clasifican como **1 = remunerativo, 2 = no remunerativo,
3 = descuento**? ¿Hay otros tipos?
**Hoy asumimos:** esos tres tipos (tipo 3 = descuento al empleado).
**Respuesta:**

### B4. 🟡 Qué conceptos se liquidan
**Pregunta:** ¿la liquidación incluye **solo los conceptos cargados como novedad**, o hay
conceptos "automáticos" que siempre se liquidan (como el básico) aunque no se carguen?
**Hoy asumimos:** se liquida solo lo que tiene novedad cargada.
**Respuesta:**

### B5. 🟡 Sueldo básico
**Pregunta:** el básico, ¿sale del **legajo del empleado**, o de la **categoría** del convenio
cuando el legajo no tiene un básico propio cargado?
**Hoy asumimos:** del legajo; si está en 0, se toma el de la categoría.
**Respuesta:**

### B6. 🔴 Contribuciones patronales — detracción y topes
**Pregunta:** para las contribuciones patronales, ¿hay que aplicar la **detracción** (suma fija
que se resta de la base, Dto. 14/2020 y siguientes) y los **topes** (base imponible mínima/máxima)?
¿Con qué valores vigentes?
**Hoy asumimos:** contribución = base × % + monto fijo, **sin** detracción ni topes.
**Respuesta:** ✅ RESUELTO por el **manual oficial** "Contribuciones Patronales v5.80" (Visual Sueldos),
que es normativo (Dto 99/2019, Dto 814/2001). Implementado (migr. 0044): por contribución se marca
`aplica_detraccion` y `aplica_topes` (con `tope_min`/`tope_max`); la **detracción** es un monto a nivel de
empresa (`sueldos_empresa_config.detraccion_monto`) que se resta de la base de las contribuciones a SIPA.
`ContribucionCalculator`: base → topes(base) → base − detracción → × % + fijo. Los **valores vigentes**
(monto de detracción, topes) son **parámetros que carga el estudio por período** (como el % y el fijo), no
se hardcodean. El sistema soporta ambos casos (flags default 'N'), así que anda use o no el estudio la
detracción; solo resta confirmar con Federico si la aplican para dejar los flags/monto por defecto.

### B7. 🟢 Ganancias 4ta categoría
**Pregunta:** ¿necesitan el cálculo de retención de **Ganancias** sobre sueldos? Si sí,
necesitaríamos las **tablas y deducciones vigentes** (escala, deducciones personales, topes) —
cambian por período y no las podemos inventar.
**Hoy asumimos:** no implementado (pendiente de las tablas oficiales).
**Respuesta:**

### B8. 🟡 Convenios que liquidan
**Pregunta:** ¿qué **convenios** liquidan en la práctica (Comercio, UOCRA, UOM, FAECYS, etc.)?
Sirve para saber qué particularidades de cada convenio priorizar.
**Hoy asumimos:** liquidación genérica, sin particularidades de convenio.
**Respuesta:**

### B9. 🔴 SAC (aguinaldo) — base y proporcionalidad
**Contexto:** ya calculamos el SAC tomando la **mejor remuneración remunerativa del semestre**
(Ley 23.041) × 50%, proporcional por días trabajados.
**Pregunta:**
1. ¿La base es la **mejor remuneración del semestre**, o usan promedio / último sueldo?
2. ¿Qué conceptos integran esa base? (¿solo remunerativos? ¿algún no remunerativo por convenio?)
3. La **proporcionalidad** por períodos incompletos, ¿es por **días trabajados / 180**? ¿Cómo se
   cuentan los días (ingreso/egreso, licencias sin goce)?
4. ¿Se paga sobre lo **devengado** o sobre lo efectivamente liquidado/pagado?
**Hoy asumimos:** mejor remuneración remunerativa del semestre × 50% × (días/180).
**Respuesta:**

### B10. 🔴 Vacaciones — días y base de cálculo
**Contexto:** calculamos los días por antigüedad al 31/12 (Ley 20.744: 14 / 21 / 28 / 35) y el
importe como **remuneración mensual / 25 × días** (art. 155).
**Pregunta:**
1. ¿La **escala de días** es la de la LCT (14/21/28/35) o hay convenios con otra?
2. La **base** de la que sacamos el valor del día, ¿es el **básico** del legajo, el remunerativo
   total, o el mejor sueldo? (hoy usamos el básico)
3. ¿El divisor es **25** para todos, o cambia (p. ej. jornalizados)?
4. ¿Hay **proporcionalidad** cuando no se trabajó el año completo (art. 153: 1 día cada 20
   trabajados)? ¿La calculamos?
**Hoy asumimos:** escala LCT, base = básico del legajo, divisor 25, sin proporcionalidad por año incompleto.
**Respuesta:**

## C) Honorarios

> PENDIENTES (planteadas 2026-06-17).

### C1. 🟡 Fórmula de honorarios
**Pregunta:** confirmar que el honorario se calcula como **unidades (UC) × valor de la UC ×
factor de complejidad × cantidad**. ¿Es así? ¿Hay mínimos o redondeos especiales?
**Hoy asumimos:** esa fórmula, sin mínimos ni redondeos especiales.
**Respuesta:**

### C2. 🟡 Valor de la UC
**Pregunta:** el **valor de la UC**, ¿lo fija el Consejo Profesional y se actualiza
periódicamente? ¿Es un único valor vigente para todos los servicios, o varía por servicio?
**Hoy asumimos:** un valor de UC que se carga/edita manualmente; no hay actualización automática.
**Respuesta:**

## D) Fiscal / vencimientos (workflow del estudio)

> PENDIENTE (planteada 2026-06-17).

### D1. 🟡 Estados de las obligaciones fiscales
**Contexto:** modelamos el seguimiento de obligaciones/vencimientos con estos estados:
creado → documentación recibida → documentación cargada → en control → presentado.
**Pregunta:** ¿ese circuito refleja cómo trabajan? ¿Falta o sobra algún estado (p. ej.
"pagado", "observado por AFIP", "vencido")?
**Hoy asumimos:** los 5 estados de arriba.
**Respuesta:**

---

## E) Satélite Visual IVA — alertas estadísticas

> PENDIENTE (planteada 2026-07-31). Documento `satelite/documento-1 (1).pdf` §7: el propio
> documento deja la mecánica sin cerrar ("falta definir el umbral de desvío... se recomienda
> resolverlo antes de programar"). Se decidió construir una v1 igual, con un supuesto explícito,
> en lugar de esperar — ver `AlertaEstadisticaCalculator`.

### E1. 🟡 Umbral de desvío para la alerta estadística
**Contexto:** el motor compara el total de compras/ventas del último período de cada empresa
contra el promedio de sus períodos anteriores (mínimo 3 para evaluar). Si el desvío supera un
umbral, se marca como "fuera de lo habitual" (posible bien de uso u otro movimiento inusual).
**Pregunta:** ¿30% de desvío es un umbral razonable, o el estudio maneja otro criterio (por
ejemplo, distinto según el contribuyente o el rubro)? ¿Debería considerar solo desvíos hacia
arriba (suba) o también hacia abajo (caída fuerte)?
**Hoy asumimos:** 30% de desvío en cualquier sentido (suba o baja), con un mínimo de 3 períodos
históricos antes de evaluar (menos que eso, no se genera alerta).
**Respuesta:**

### E2. 🟢 ¿Comparte lógica con el "semáforo" de Monotributo?
**Contexto:** el documento sugiere reusar la lógica de semáforo (verde/amarillo/naranja/rojo) del
sistema de causales de exclusión de Monotributo, en lugar de construir un motor de alertas nuevo
y separado. Se verificó que ese semáforo **no existe todavía en el código** de este ecosistema.
**Pregunta:** ¿el semáforo de Monotributo es un sistema externo/legacy que hay que integrar, o
directamente no existe todavía y hay que diseñarlo desde cero? Si existe, ¿dónde vive?
**Hoy asumimos:** motor independiente (no comparte código con ningún semáforo, porque no se
encontró ninguno reutilizable).
**Respuesta:**

---

# ✅ RESPONDIDAS (archivo histórico — no escribir acá)

## A) IVA / Factura electrónica — RESPONDIDO COMPLETO

> El contador respondió la tanda original (A1–A9) el **2026-06-20** (ver `respuestas.md` +
> `imagenes/`). Las confirmaciones de Libro IVA Digital y F.2051 (A10–A14) se resolvieron entre
> el **2026-06-20** y el **2026-06-21** (spec del Libro IVA Digital + `GUIA_LIQUIDACION.pdf`).
> La apertura por actividad (A15) y los anulados (A11-bis) se respondieron el **2026-06-26**.

#### A1. ✅ Percepciones: ¿integran el total de la factura? — RESPONDIDA (2026-06-20)
**Respuesta:** **SÍ integran el total** (confirmado con factura real Saint-Gobain). Implementado
en migración 0032 (`venta_percepciones`/`compra_percepciones` a nivel comprobante); el total suma
Σ percepciones y a AFIP se emiten como `Tributos` (suman a `ImpTrib`).

#### A2. ✅ Base de cálculo de las percepciones/retenciones — RESPONDIDA (2026-06-20)
**Respuesta:** la base depende del tipo. Implementado por **estrategia** (`tipos_retencion.base_calculo`):
`neto_gravado` (IIBB/municipal), `neto_mas_imp_interno` (IIBB con imp. interno) e `iva_percepcion`
(Perc. de IVA por tramos: 3% s/neto 21% + 1,5% s/neto 10,5%). El ABM de tipos configura la base.

#### A3. ✅ Crédito fiscal computable de las compras — RESPONDIDA (2026-06-20)
**Respuesta:** se computa el **100% del crédito fiscal, sin prorrateo**. (Si alguna vez hay un caso
no computable, se carga el `cf_computable` de la línea a mano.)

#### A4. ✅ DDJJ de IVA: qué entra en el saldo — RESPONDIDA (2026-06-20)
**Respuesta:** el saldo **sí** descuenta retenciones/percepciones de IVA sufridas y arrastra el saldo
a favor del período anterior. Implementado como **DDJJ IVA Simple (F.2051)** con arrastres automáticos
del período anterior; el F2002 sigue mostrando el saldo técnico (débito − crédito computable).

#### A5. ✅ "IVA incluido" (campo `iva_inc`) — RESPONDIDA (2026-06-20)
**Respuesta:** el contador **no reconoce** ese concepto. Se deja como está (se guarda/suma al total
pero no se informa como IVA a AFIP); no requiere cambios.

#### A6. ✅ Alícuotas de IVA habilitadas — RESPONDIDA (2026-06-20)
**Respuesta:** el conjunto **0 / 2,5 / 5 / 10,5 / 21 / 27** es correcto. No falta ni sobra ninguna.

#### A7. ✅ Signo de los comprobantes — RESPONDIDA (2026-06-20)
**Respuesta:** correcto: **solo las notas de crédito restan** (signo −1); el resto suma.

#### A8. ✅ Condición de IVA del receptor (RG 5616) — RESPONDIDA (2026-06-20)
**Respuesta:** por defecto **Consumidor Final** (id 5). "Responsable No Inscripto" lo eliminó AFIP y
"No Disponible" → se emiten como Consumidor Final. Implementado en `CondicionReceptorResolver`
(constante `CONSUMIDOR_FINAL`, ya no lanza ante condición sin equivalencia).

#### A9. ✅ Layout CITI / RG 3685 — RESPONDIDA (2026-06-20)
**Respuesta:** el régimen vigente es el **Libro IVA Digital / Portal IVA** (reemplaza CITI/RG3685);
el contador aportó el spec y 4 TXT de ejemplo. Implementado y validado byte a byte.

#### A10. ✅ Libro IVA Digital — Perc. de IVA en VENTAS — RESUELTA (2026-06-20)
Con `imagenes/disenio_registro_IVA_digital.pdf`: en **ventas** no hay campo propio de Perc. de IVA
→ va en el campo **13 (Nacionales)**; en **compras** sí hay campo propio (12) + 13 (otros nacionales).
Nuestra implementación coincide campo a campo.

#### A11. ✅ Libro IVA Digital — tipos de comprobante que usan — RESUELTA (2026-06-21)
Con `imagenes/GUIA_LIQUIDACION.pdf`: Factura A/B/C/E/M, ND, NC, Tique Factura, NC Tique, FCE MiPyME,
NC Electrónica MiPyME, Liquidación de Servicios Públicos A/B. TurIVA e importaciones NO se usan.
Resuelto en código: `CbteTipoResolver` ampliado (lo usan Libro IVA Digital y WSFE).

#### A12. ✅ Libro IVA Digital — código de operación y despacho de importación — RESUELTA (2026-06-21)
Con la guía (punto 6): el cliente solo tilda "Operaciones No Gravadas o Exentas"; nunca importaciones
ni TurIVA → el código de operación y el despacho de importación van en **blanco**.

#### A13. ✅ DDJJ IVA Simple (F.2051) — retenciones/percepciones SUFRIDAS — RESUELTA (2026-06-21)
Con la guía (puntos 10 y 13): las sufridas se identifican con "Mis Retenciones" de AFIP (IVA), "SIFERE"
(IIBB) y los comprobantes físicos. Para el F.2051 importan las de IVA: las percepciones de IVA en
compras ya se modelan; las retenciones de IVA sufridas se cargan como **insumo** del período.

#### A14. ✅ IVA Simple (F.2051) — "neto de compensaciones" / restituciones — RESUELTA (2026-06-21)
Con la guía (puntos 19-22): las compensaciones se hacen por fuera en el "Sistema de Cuentas Tributarias"
de ARCA; al formulario llega el saldo **neto de usos**. Confirma el supuesto: los arrastres llegan netos
y el sistema no calcula compensaciones ni restituciones.

#### A15. ✅ DJ IVA Simple — apertura por actividad — RESPONDIDA (2026-06-26)
**Respuesta del contador** (ejemplos en `softContable/preguntas2/`: NAES PDF + 3 Excel reales).
Análisis y diseño: `docs/ingenieria-inversa/dj-iva-simple-actividad.md`.

1. **MULTI-actividad** (código NAES). Separar ventas por actividad **no afecta el IVA**, pero **sí**
   determina la alícuota de **IIBB provincial** y **tasa municipal**. La actividad de cada venta se
   resuelve por **distintas estrategias según el cliente** (un cliente puede combinar varias):
   - **Por punto de venta** (la más común): mapa `{punto_venta → actividad}` (MAFAP, ANCASTI).
   - **Por alícuota de IVA** (construcción): 10,5% → residencial (410011); 21% → no residencial (410021).
   - **Porcentajes fijos**: coeficientes `{actividad → %}` (suman 1) sobre el neto del período (Acevedo).
   - **Factura por factura / manual**: actividad cargada por comprobante (Bruno Vega).
   - **Por receptor (CUIT)**: mapa `{cliente → actividad}` (todo a Minera Galaxy Lithium → 99000).
2. **Bienes de uso: SÍ** (poco frecuente). El cliente informa y se discrimina en la factura. Se separa
   en la DDJJ porque **no paga IIBB ni tasa municipal** y en Ganancias va aparte → **flag por comprobante**.
3. **Compras — concepto: SÍ, discriminar**: servicios (luz/agua/gas/internet/teléfono, 27% y 21%),
   alquileres (por proveedor) y bienes de uso (por proveedor + aviso del cliente) → 1 bienes /
   2 locaciones / 3 servicios / 4 inversiones BU.
4. **Dación en pago: NO** (nunca se vio). O.D.P. = 0.

**Estado de implementación:** **Fase 1 HECHA** (por punto de venta + override manual + bien de uso +
concepto de compra; migración 0036, exporter reescrito, ABM + frontend). **Fases 2-3 pendientes** (por
alícuota/construcción, por receptor, porcentajes fijos) — ver el doc de ingeniería inversa.

#### A11-bis. ✅ Libro IVA Digital — comprobantes ANULADOS — RESUELTA (2026-06-26): NO se implementa
**Respuesta del contador:** los únicos "anulados" que el Visual mandaba a esa solapa eran
**comprobantes emitidos en cero** (total = 0; ej. cliente Reymundo Frías). Cargados desde el propio
portal de ARCA aparecen junto al resto sin diferencia, y el **efecto sobre el impuesto es nulo**. La
forma correcta de anular un comprobante con monto gravado es una **nota de crédito**. → **No tiene
sentido generar el archivo de anulados**; queda descartado (igual que el Visual).
