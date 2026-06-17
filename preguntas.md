# Preguntas para el contador

> Dudas de **dominio contable/impositivo** que surgieron construyendo el sistema y que
> necesitamos confirmar para no implementar supuestos equivocados. Cada una incluye el
> contexto y **qué asumimos hoy** (para que sea fácil decir "está bien" o corregir).
>
> Formato: marcá cada pregunta con la respuesta debajo. Lo que esté ✅ ya está confirmado.

---

## A) IVA / Factura electrónica (AFIP WSFEv1)

### A1. Percepciones: ¿integran el total de la factura?
**Contexto:** el sistema registra percepciones por comprobante (Percepción IVA, IIBB,
Nacionales, Municipales). Hoy, igual que el sistema viejo, esas percepciones **NO se suman
al total** de la factura (se registran aparte, para los regímenes de información).
**Pregunta:** cuando emitimos una factura electrónica, ¿las percepciones deben **sumarse al
importe total** que paga el cliente (y por lo tanto aparecer en AFIP y en el libro IVA), o
van por fuera del total como hasta ahora?
**Hoy asumimos:** van por fuera del total (no se informan a AFIP como tributos del comprobante).
**Respuesta:**

### A2. "IVA incluido" (iva_inc) en la discriminación
**Contexto:** cada línea de la factura tiene, además del IVA normal, un campo "IVA incluido"
(`iva_inc`) que el sistema viejo sumaba al total. No tenemos claro qué representa (¿percepción
de IVA?, ¿IVA de otra alícuota?).
**Pregunta:** ¿qué es el "IVA incluido"? ¿Forma parte del IVA que se informa a AFIP (ImpIVA) o
es otra cosa? ¿En qué casos se usa?
**Hoy asumimos:** se guarda y suma al total, pero **no** lo mandamos como IVA a AFIP.
**Respuesta:**

### A3. Condición de IVA del receptor (clientes "raros")
**Contexto:** AFIP exige (RG 5616) informar la condición frente al IVA del que recibe la
factura. Tenemos mapeadas: Responsable Inscripto, Monotributo, Exento, Consumidor Final,
Cliente del Exterior. Quedan sin mapear: "Responsable No Inscripto" y "No Disponible".
**Pregunta:** ¿se siguen usando esas condiciones? Si sí, ¿qué condición de AFIP les corresponde?
(¿o ya no existen y las podemos descartar?)
**Hoy asumimos:** no se usan (si aparece una, el sistema avisa que no la puede mapear).
**Respuesta:**

### A4. Layout de los archivos CITI / RG 3685 (regímenes de información)
**Contexto:** AFIP pide exportar ventas/compras en archivos de texto con un formato exacto.
No tenemos el instructivo con el layout exacto (posiciones/anchos de cada campo).
**Pregunta:** ¿nos podés facilitar el diseño de registro vigente de CITI Compras/Ventas
(RG 3685 o el régimen que corresponda hoy), o un ejemplo de archivo real generado?
**Hoy asumimos:** pendiente, no implementado (no queremos inventar el formato).
**Respuesta:**

---

## B) Sueldos (liquidación)

> La lógica de liquidación del sistema viejo estaba en el programa de escritorio (no en la
> base de datos), así que la reconstruimos desde las fórmulas y el modelo. Necesitamos
> confirmar estos supuestos contra una **liquidación real** (un recibo de ejemplo ayudaría).

### B1. Antigüedad
**Pregunta:** ¿la antigüedad se calcula por **años cumplidos** y se paga **1% por año**?
¿Hay convenios con otra escala (p. ej. 2% por año, o por tramos)?
**Hoy asumimos:** años cumplidos, 1% por año (la variable ANTIG = cantidad de años).
**Respuesta:**

### B2. Conceptos no remunerativos
**Pregunta:** confirmar que el sistema debe acumular los conceptos **no remunerativos** por
separado de los remunerativos (para topes, aportes, etc.).
**Hoy asumimos:** sí, se acumulan aparte (variable NOREM).
**Respuesta:**

### B3. Tipos de concepto
**Pregunta:** ¿los conceptos se clasifican como **1 = remunerativo, 2 = no remunerativo,
3 = descuento**? ¿Hay otros tipos?
**Hoy asumimos:** esos tres tipos (tipo 3 = descuento al empleado).
**Respuesta:**

### B4. Qué conceptos se liquidan
**Pregunta:** ¿la liquidación incluye **solo los conceptos cargados como novedad** para ese
empleado/período, o hay conceptos "automáticos" que siempre se liquidan (como el sueldo básico)
aunque no se carguen?
**Hoy asumimos:** se liquida solo lo que tiene novedad cargada.
**Respuesta:**

### B5. Sueldo básico
**Pregunta:** el básico, ¿sale del **legajo del empleado**, o de la **categoría** del convenio
cuando el legajo no tiene un básico propio cargado?
**Hoy asumimos:** del legajo; si está en 0, se toma el de la categoría.
**Respuesta:**

### B6. Contribuciones patronales — detracción y topes
**Pregunta:** para las contribuciones patronales, ¿hay que aplicar la **detracción** (suma fija
que se resta de la base, Dto. 14/2020 y siguientes) y los **topes** (base imponible mínima/máxima)?
¿Con qué valores vigentes?
**Hoy asumimos:** contribución = base × % + monto fijo, **sin** detracción ni topes (pendiente).
**Respuesta:**

### B7. Ganancias 4ta categoría
**Pregunta:** ¿necesitan el cálculo de retención de **Ganancias** sobre sueldos? Si sí,
necesitaríamos las **tablas y deducciones vigentes** (escala anual, deducciones personales,
topes) — cambian por período y no las podemos inventar.
**Hoy asumimos:** no implementado (pendiente de las tablas oficiales).
**Respuesta:**

---

## C) Honorarios

### C1. Fórmula de honorarios
**Pregunta:** confirmar que el honorario se calcula como
**unidades (UC) × valor de la UC × factor de complejidad × cantidad**. ¿Es así? ¿Hay mínimos
o redondeos especiales?
**Hoy asumimos:** esa fórmula, sin mínimos ni redondeos especiales.
**Respuesta:**
