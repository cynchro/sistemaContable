# Preguntas para el contador

> Dudas de **dominio contable/impositivo** que surgieron construyendo el sistema. Las
> implementamos con un supuesto razonable para no frenar, pero necesitamos confirmarlas para
> no quedar con algo mal modelado. Cada pregunta trae el **contexto**, la **pregunta concreta**
> y **qué asumimos hoy** (así alcanza con decir "está bien" o corregir).
>
> Cómo usarlo: escribí la respuesta en la línea **Respuesta:**. Si algo no aplica, poné "no
> aplica". 🔴 = nos cambia el cálculo / bloquea algo; 🟡 = ajuste o confirmación; 🟢 = necesitamos
> una tabla/instructivo oficial que no tenemos; ✅ = ya respondida (se conserva como registro).
>
> **Organización:** dentro de cada módulo, primero las **RESPONDIDAS** (con la fecha de la
> respuesta) y después las **PENDIENTES** (con la fecha en que se plantearon). Lo único abierto
> hoy en IVA es **A15** (planteada 2026-06-25). El resto de IVA ya está respondido.

---

## A) IVA / Factura electrónica

### A.1) RESPONDIDAS

> El contador respondió la tanda original (A1–A9) el **2026-06-20** (ver `respuestas.md` +
> `imagenes/`). Las confirmaciones de Libro IVA Digital y F.2051 (A10–A14) se resolvieron entre
> el **2026-06-20** y el **2026-06-21** con el spec del Libro IVA Digital y la `GUIA_LIQUIDACION.pdf`.

#### A1. ✅ Percepciones: ¿integran el total de la factura? — RESPONDIDA (2026-06-20)
**Contexto:** el sistema registra percepciones por comprobante (Percepción IVA, IIBB, Nacionales,
Municipales).
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
Nuestra implementación coincide campo a campo. No requiere respuesta.

#### A11. ✅ Libro IVA Digital — tipos de comprobante que usan — RESUELTA (2026-06-21)
Con `imagenes/GUIA_LIQUIDACION.pdf`: Factura A/B/C/E/M, ND, NC, Tique Factura, NC Tique, FCE MiPyME,
NC Electrónica MiPyME, Liquidación de Servicios Públicos A/B. TurIVA e importaciones NO se usan.
Resuelto en código: `CbteTipoResolver` ampliado (lo usan Libro IVA Digital y WSFE).

#### A12. ✅ Libro IVA Digital — código de operación y despacho de importación — RESUELTA (2026-06-21)
Con la guía (punto 6): el cliente solo tilda "Operaciones No Gravadas o Exentas"; nunca importaciones
ni TurIVA → el código de operación y el despacho de importación van en **blanco**. No requiere respuesta.

#### A13. ✅ DDJJ IVA Simple (F.2051) — retenciones/percepciones SUFRIDAS — RESUELTA (2026-06-21)
Con la guía (puntos 10 y 13): las sufridas se identifican con "Mis Retenciones" de AFIP (IVA), "SIFERE"
(IIBB) y los comprobantes físicos. Para el F.2051 importan las de IVA: las percepciones de IVA en
compras ya se modelan; las retenciones de IVA sufridas se cargan como **insumo** del período.

#### A14. ✅ IVA Simple (F.2051) — "neto de compensaciones" / restituciones — RESUELTA (2026-06-21)
Con la guía (puntos 19-22): las compensaciones se hacen por fuera en el "Sistema de Cuentas Tributarias"
de ARCA; al formulario llega el saldo **neto de usos**. Confirma el supuesto: los arrastres llegan netos
y el sistema no calcula compensaciones ni restituciones.

### A.2) PENDIENTES (abiertas)

#### A15. 🔴 DJ IVA Simple — apertura de otros conceptos por actividad (CSV de ARCA) — PLANTEADA (2026-06-25)
**Contexto:** ARCA permite importar la apertura de la DJ IVA Simple en 4 CSV (débito fiscal, restitución
de débito, crédito fiscal, restitución de crédito). Spec en
`docs/ingenieria-inversa/dj-iva-simple-actividad.md`. Implementamos un exporter **v1** con supuestos.
**Preguntas:**
1. **Actividad por comprobante**: ¿las empresas del estudio operan **una sola actividad** (entonces
   imputar todo a la actividad principal es correcto), o hay empresas multi-actividad donde cada
   comprobante debería llevar su propio código de actividad? (Si es lo segundo, hay que capturar la
   actividad al cargar ventas/compras → migración + cambio en los formularios.)
2. **Venta de Bienes de Uso** (tipo de operación 2 del débito): ¿la registran? Hoy todo lo gravado
   se informa como tipo 1 (venta de cosas muebles/servicios). ¿Hace falta distinguir bienes de uso?
3. **Concepto del crédito fiscal** (compras): la DJ pide clasificar cada compra en 1 Bienes /
   2 Locaciones / 3 Servicios / 4 Inversiones de Bienes de Uso. Hoy informamos todo como
   **1 (Compras de Bienes)**. ¿Necesitan la apertura real por concepto?
4. **Débito Fiscal por Dación en Pago** (campo O.D.P.): ¿alguna vez tienen operaciones de dación en
   pago? Hoy lo informamos en 0.
**Hoy asumimos:** monoactividad (actividad principal de la empresa), sin bienes de uso, crédito
fiscal concepto 1, dación en pago 0, exportaciones excluidas.
**Respuesta:**

#### A11-bis. 🟢 Libro IVA Digital — archivo de comprobantes de VENTAS ANULADOS — PLANTEADA (2026-06-20)
**Contexto:** el diseño de registro incluye un 5º archivo, `LIBRO_IVA_DIGITAL_CBTES_VENTAS_ANULADOS`
(44 posiciones). Comentaste que el sistema viejo (Visual) **no lo generaba**. Nosotros sí registramos
comprobantes anulados, así que podríamos generarlo.
**Pregunta:** ¿lo necesitan / lo presentan? ¿Vale la pena que el sistema genere ese archivo?
**Hoy asumimos:** no se genera (igual que el Visual). Lo agregamos si lo usan.
**Respuesta:**

---

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
**Respuesta:**

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

---

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

---

## D) Fiscal / vencimientos (workflow del estudio)

> PENDIENTE (planteada 2026-06-17).

### D1. 🟡 Estados de las obligaciones fiscales
**Contexto:** modelamos el seguimiento de obligaciones/vencimientos con estos estados:
creado → documentación recibida → documentación cargada → en control → presentado.
**Pregunta:** ¿ese circuito refleja cómo trabajan? ¿Falta o sobra algún estado (p. ej.
"pagado", "observado por AFIP", "vencido")?
**Hoy asumimos:** los 5 estados de arriba.
**Respuesta:**
