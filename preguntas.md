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
