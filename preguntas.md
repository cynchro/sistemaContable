# Preguntas para el contador

> Dudas de **dominio contable/impositivo** que surgieron construyendo el sistema. Las
> implementamos con un supuesto razonable para no frenar, pero necesitamos confirmarlas para
> no quedar con algo mal modelado. Cada pregunta trae el **contexto**, la **pregunta concreta**
> y **qué asumimos hoy** (así alcanza con decir "está bien" o corregir).
>
> Cómo usarlo: escribí la respuesta en la línea **Respuesta:**. Si algo no aplica, poné "no
> aplica". 🔴 = nos cambia el cálculo / bloquea algo; 🟡 = ajuste o confirmación; 🟢 = necesitamos
> una tabla/instructivo oficial que no tenemos.

---

## A) IVA / Factura electrónica

### A1. 🔴 Percepciones: ¿integran el total de la factura?
**Contexto:** el sistema registra percepciones por comprobante (Percepción IVA, IIBB,
Nacionales, Municipales). Hoy, igual que el sistema viejo, esas percepciones **NO se suman al
total** de la factura (se registran aparte, para los regímenes de información).
**Pregunta:** al emitir una factura, ¿las percepciones deben **sumarse al importe total** que
paga el cliente (y entonces ir a AFIP y al libro IVA), o van por fuera del total como hasta ahora?
**Hoy asumimos:** van por fuera del total (no se informan como tributos del comprobante a AFIP).
**Respuesta:**

### A2. 🟡 Base de cálculo de las percepciones/retenciones
**Contexto:** una percepción se puede cargar con el **porcentaje** y el sistema calcula el
importe (`base × porcentaje`). Si no se aclara una base, usamos por defecto el **neto gravado**.
**Pregunta:** ¿sobre qué monto se calcula cada tipo de percepción? (IIBB sobre el neto;
Percepción de IVA, ¿sobre el IVA o el neto?; ¿sobre neto+IVA?). Si depende del tipo, decinos la
base de cada uno y la dejamos fija por tipo.
**Hoy asumimos:** base = neto gravado de la línea (salvo que se informe una base explícita).
**Respuesta:**

### A3. 🔴 Crédito fiscal computable de las compras
**Contexto:** en cada compra guardamos el IVA y un "crédito fiscal computable". Hoy, por defecto,
**computamos el 100% del IVA** de la compra como crédito fiscal.
**Pregunta:** ¿hay casos en que el IVA de una compra **no es 100% computable** (p. ej. ciertos
gastos, prorrateo por operaciones exentas, restricciones por tipo de bien)? ¿Cómo se determina
el porcentaje computable?
**Hoy asumimos:** crédito computable = IVA total de la compra (salvo que se informe otro).
**Respuesta:**

### A4. 🔴 DDJJ de IVA (F2002): qué entra en el saldo
**Contexto:** calculamos el **saldo técnico = débito fiscal (ventas) − crédito fiscal computable
(compras)**. No estamos considerando todavía: retenciones/percepciones de IVA **sufridas**, ni
**saldo a favor del período anterior**, ni otros conceptos.
**Pregunta:** ¿el saldo a pagar de la DDJJ debe descontar las **retenciones y percepciones de IVA
sufridas** y arrastrar el **saldo a favor** del período anterior? ¿Hay otros conceptos del F2002
que necesiten (p. ej. saldo de libre disponibilidad, ingresos directos)?
**Hoy asumimos:** solo saldo técnico (débito − crédito computable), sin retenciones sufridas ni arrastre.
**Respuesta:**

### A5. 🟡 "IVA incluido" (campo `iva_inc`)
**Contexto:** cada línea tiene, además del IVA normal, un campo "IVA incluido" que el sistema
viejo sumaba al total. No tenemos claro qué representa.
**Pregunta:** ¿qué es el "IVA incluido"? ¿Forma parte del IVA que se informa a AFIP o es otra
cosa (percepción de IVA, IVA de otra alícuota)? ¿En qué casos se usa?
**Hoy asumimos:** se guarda y suma al total, pero no lo mandamos como IVA a AFIP.
**Respuesta:**

### A6. 🟡 Alícuotas de IVA habilitadas
**Contexto:** soportamos las alícuotas 0%, 2,5%, 5%, 10,5%, 21% y 27% (las de AFIP).
**Pregunta:** ¿son todas las que usan? ¿Falta o sobra alguna?
**Hoy asumimos:** ese conjunto (0 / 2,5 / 5 / 10,5 / 21 / 27).
**Respuesta:**

### A7. 🟡 Signo de los comprobantes (qué resta en los totales)
**Contexto:** al sumar el libro IVA, las **notas de crédito restan** (signo −1) y el resto suma.
**Pregunta:** ¿es correcto que solo las notas de crédito resten? ¿Hay algún otro comprobante que
deba restar (o alguna NC que no deba)?
**Hoy asumimos:** restan las notas de crédito; todo lo demás suma.
**Respuesta:**

### A8. 🟡 Condición de IVA del receptor (RG 5616)
**Contexto:** AFIP exige informar la condición frente al IVA del que recibe la factura. Tenemos
mapeadas: Responsable Inscripto, Monotributo, Exento, Consumidor Final, Cliente del Exterior.
Quedan sin mapear "Responsable No Inscripto" y "No Disponible".
**Pregunta:** ¿se siguen usando esas dos condiciones? Si sí, ¿qué condición de AFIP les
corresponde? ¿O ya no existen y las descartamos?
**Hoy asumimos:** no se usan (si aparece una, el sistema avisa que no la puede mapear).
**Respuesta:**

### A9. 🟢 Layout de los archivos CITI / RG 3685 (regímenes de información)
**Contexto:** AFIP pide exportar ventas/compras en archivos de texto con un formato exacto. No
tenemos el instructivo con el layout (posiciones/anchos de cada campo).
**Pregunta:** ¿nos pueden facilitar el **diseño de registro vigente** de CITI Compras/Ventas
(o el régimen que corresponda hoy), o un **archivo real de ejemplo**?
**Hoy asumimos:** pendiente, no implementado (no queremos inventar el formato).
**Respuesta:** RESUELTO en respuestas.md (A9): el régimen vigente es el **Libro IVA Digital /
Portal IVA**; el contador aportó el spec y 4 TXT de ejemplo. Implementado. Las confirmaciones
puntuales que quedaron del Libro IVA Digital y de la DDJJ IVA Simple están en A10–A15.

---

## A-bis) IVA — confirmaciones que quedaron pendientes (Libro IVA Digital + F.2051)

> Estas surgieron **después** de las respuestas de la sección A, al construir el **Libro IVA
> Digital** (los 4 TXT de ARCA) y la **DDJJ IVA Simple (F.2051)**. Ya están implementadas con un
> supuesto; necesitamos un OK o la corrección.
>
> Nota: la **estructura del F.2051** (saldo técnico, arrastres de períodos anteriores, saldo de
> libre disponibilidad neto de compensaciones y retenciones/percepciones sufridas) **ya quedó
> confirmada en la respuesta A4** — acá solo queda lo **operativo** (de dónde salen los datos) y
> los detalles de **layout** del Libro IVA Digital.

### A10. ✅ Libro IVA Digital — Percepción de IVA en VENTAS (RESUELTO por el diseño de registro)
**Resuelto con `imagenes/disenio_registro_IVA_digital.pdf`:** el layout oficial de ARCA confirma
que en **ventas** (`LIBRO_IVA_DIGITAL_VENTAS_CBTE`) **no hay** campo propio de Perc. de IVA — los
campos de percepción son *11 a no categorizados*, *13 impuestos Nacionales*, *14 IIBB*, *15
Municipales*, *16 internos* → la Perc. de IVA se informa en el campo **13 (Nacionales)**. En
**compras** (`..._COMPRAS_CBTE`) sí hay campo propio (*12 percepciones del IVA*) + *13 otros
nacionales*. **Nuestra implementación coincide byte a byte con el layout.** No requiere respuesta.

### A11. ✅ Libro IVA Digital — tipos de comprobante que usan (RESPONDIDO por la guía → genera trabajo de código)
**Resuelto con `imagenes/GUIA_LIQUIDACION.pdf`** (puntos 1, 8, 16 y 18): el estudio opera con
**Factura A/B/C/E/M**, **ND**, **NC**, **Ticket Factura A/B/C** (cod. 81/82/83), **NC Tique A**
(cod. 112), **FCE MiPyME (FE)**, **NC Electrónica MiPyME**, **Liquidación de Servicios Públicos
A/B**, y comprobantes B (06/07/08/09/82) y C (11/12/13/15/83). TurIVA e importaciones NO se usan.
**✅ Resuelto en código:** el `CbteTipoResolver` se amplió para mapear esos comprobantes (Tique
Factura, FCE MiPyME, Liquidación de Servicios Públicos, NC/Tique, Factura T y NC T) además de
Factura/ND/NC/Recibo A/B/C/E/M. Lo usa el Libro IVA Digital y WSFE. Tests verdes. Ver
`app/Modules/Iva/pendientes.md §D`.

### A12. ✅ Libro IVA Digital — código de operación y despacho de importación (RESUELTO por la guía)
**Resuelto con la guía** (punto 6): el cliente solo tilda "Operaciones No Gravadas o Exentas";
nunca "Importación definitiva de bienes", "Importación de servicios" ni "TurIVA". → el código de
operación y el despacho de importación van en **blanco**, como hoy. No requiere respuesta.

### A11-bis. 🟢 Libro IVA Digital — archivo de comprobantes de VENTAS ANULADOS
**Contexto:** el diseño de registro incluye un 5º archivo, `LIBRO_IVA_DIGITAL_CBTES_VENTAS_ANULADOS`
(44 posiciones: fecha, tipo, punto de venta, número, fecha de anulación). Nos comentaste que el
sistema viejo (Visual) **no lo generaba**. Nosotros sí registramos comprobantes anulados, así que
podríamos generarlo.
**Pregunta:** ¿lo necesitan / lo presentan? ¿Vale la pena que el sistema genere ese archivo?
**Hoy asumimos:** no se genera (igual que el Visual). Lo agregamos si lo usan.
**Respuesta:**

### A12. 🟢 Libro IVA Digital — código de operación y despacho de importación
**Contexto:** el layout confirma que existen los campos "**código de operación**" (ventas/compras,
"según tabla Código de Operación") y "**despacho de importación**" (compras). Como no operan
importaciones/exportaciones, hoy los enviamos en **blanco**.
**Pregunta:** ¿confirmás que **no** usan operaciones con código especial ni despachos de importación
(y por eso esos campos van en blanco)? Si en algún caso sí, decinos cuáles.
**Hoy asumimos:** van en **blanco** (no operan esos casos).
**Respuesta:**

### A13. ✅ DDJJ IVA Simple (F.2051) — de dónde salen las retenciones/percepciones SUFRIDAS (RESUELTO por la guía)
**Resuelto con la guía** (puntos 10 y 13): las percepciones/retenciones **sufridas** se identifican
con **"Mis Retenciones" de AFIP** (percepciones de IVA), **"SIFERE consultas"** (percepciones de
IIBB) y los **comprobantes físicos** de percepción/retención. Para el F.2051 de IVA importan las de
**IVA**: las **percepciones de IVA en compras** ya las modelamos (`compra_percepciones`); las
**retenciones de IVA sufridas en cobranzas** son un insumo externo (constancias / "Mis Retenciones").
**Decisión de implementación pendiente** (no consulta): derivar la parte de percepciones de IVA de
compras + permitir cargar las retenciones sufridas como insumo del período.

### A14. ✅ IVA Simple (F.2051) — "neto de compensaciones" / restituciones (RESUELTO por la guía)
**Resuelto con la guía** (puntos 19-22): las **compensaciones** (usar saldos a favor para cubrir
Ganancias, Bienes Personales, anticipos) se hacen **por fuera** en el **"Sistema de Cuentas
Tributarias" de ARCA**; al formulario llega el saldo **"neto de usos"**. Confirma nuestro supuesto:
los importes arrastrados llegan ya netos y el sistema **no** calcula compensaciones ni restituciones.

### A15. 🔴 DJ IVA Simple — apertura de otros conceptos por actividad (CSV de ARCA)
**Contexto:** ARCA permite importar la apertura de la DJ IVA Simple en 4 CSV (débito fiscal,
restitución de débito, crédito fiscal, restitución de crédito). Spec en
`docs/ingenieria-inversa/dj-iva-simple-actividad.md`. Implementamos un exporter **v1** con supuestos.
**Preguntas:**
1. **Actividad por comprobante**: ¿las empresas del estudio operan **una sola actividad** (entonces
   imputar todo a la actividad principal es correcto), o hay empresas multi-actividad donde cada
   comprobante debería llevar su propio código de actividad? (Si es lo segundo, hay que capturar la
   actividad al cargar ventas/compras.)
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

---

## B) Sueldos (liquidación)

> La lógica de liquidación del sistema viejo estaba en el programa de escritorio (no en la base
> de datos), así que la reconstruimos desde las fórmulas y el modelo. Lo ideal para confirmar
> esto es **un recibo de sueldo real de ejemplo**.

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

### D1. 🟡 Estados de las obligaciones fiscales
**Contexto:** modelamos el seguimiento de obligaciones/vencimientos con estos estados:
creado → documentación recibida → documentación cargada → en control → presentado.
**Pregunta:** ¿ese circuito refleja cómo trabajan? ¿Falta o sobra algún estado (p. ej.
"pagado", "observado por AFIP", "vencido")?
**Hoy asumimos:** los 5 estados de arriba.
**Respuesta:**
