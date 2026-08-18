## A) IVA / Factura electrónica

### A1. 🔴 Percepciones: ¿integran el total de la factura?
*Contexto:* el sistema registra percepciones por comprobante (Percepción IVA, IIBB,
Nacionales, Municipales). Hoy, igual que el sistema viejo, esas percepciones **NO se suman al
total** de la factura (se registran aparte, para los regímenes de información).
*Pregunta:* al emitir una factura, ¿las percepciones deben *sumarse al importe total* que
paga el cliente (y entonces ir a AFIP y al libro IVA), o van por fuera del total como hasta ahora?
*Hoy asumimos:* van por fuera del total (no se informan como tributos del comprobante a AFIP).
*Respuesta: Si, las percepciones, tanto de IVA, como de IIBB y de tasa municipal forman parte del total de una factura que paga el cliente porque se le están haciendo al cliente. El que emite la factura (que al mismo tiempo es agente de percepción) tiene la obligación de discriminar el monto y el concepto de las percepciones al momento de hacerla y luego de hacer su declaración cono agente de percepción ante ARCA porque esas percepciones no le pertenecen, le pertenecen al cliente que esta pagando y luego por sistema aparecen en su declaración. En resumen si, las percepciones van discriminadas pero forman parte del total de una factura.

### A2. 🟡 Base de cálculo de las percepciones/retenciones
*Contexto:* una percepción se puede cargar con el *porcentaje* y el sistema calcula el
importe (base × porcentaje). Si no se aclara una base, usamos por defecto el *neto gravado*.
*Pregunta:* ¿sobre qué monto se calcula cada tipo de percepción? (IIBB sobre el neto;
Percepción de IVA, ¿sobre el IVA o el neto?; ¿sobre neto+IVA?). Si depende del tipo, decinos la
base de cada uno y la dejamos fija por tipo.
*Hoy asumimos:* base = neto gravado de la línea (salvo que se informe una base explícita).
*Respuesta: Depende del tipo de percepción y de la provincia. Percepción de IVA es siempre 3% calculado sobre el neto de lo facturado al 21% y del 1.5% calculado sobre el neto de lo facturado al 10,5%.
Percepción de Ingresos brutos depende la alícuota que establezca cada provincia para sus agentes y depende del tipo de producto. En el caso de Catamarca es el 2,5% sobre el neto total facturado (sin discriminar si es al 21% o al 10,5%) y en el caso de proveedores que tengan impuestos internos (por ej los que venden bebidas alcohólicas) la base de calculo es “Neto facturado + impuesto interno”
En el caso de percepción de tasa municipal en Catamarca es el 0,6% y se calcula sobre el Neto total facturado.

### A3. 🔴 Crédito fiscal computable de las compras
*Contexto:* en cada compra guardamos el IVA y un "crédito fiscal computable". Hoy, por defecto,
*computamos el 100% del IVA* de la compra como crédito fiscal.
*Pregunta:* ¿hay casos en que el IVA de una compra *no es 100% computable* (p. ej. ciertos
gastos, prorrateo por operaciones exentas, restricciones por tipo de bien)? ¿Cómo se determina
el porcentaje computable?
*Hoy asumimos:* crédito computable = IVA total de la compra (salvo que se informe otro).
*Respuesta: Nosotros computamos 100% el crédito fiscal sobre compras gravadas, hay una norma especial que establece que si el contribuyente realiza ventas Gravadas y Exentas solo puede reconocerse el IVA crédito fiscal de compras gravadas en un porcentaje equivalente al de sus ventas gravadas. Ventas Gravadas/Total de ventas (exentas+gravadas)= Porcentaje del crédito que se puede prorratear. Pero en el estudio actualmente no aplicamos esa regla.

### A4. 🔴 DDJJ de IVA (F2002): qué entra en el saldo
*Contexto:* calculamos el **saldo técnico = débito fiscal (ventas) − crédito fiscal computable
(compras)*. No estamos considerando todavía: retenciones/percepciones de IVA **sufridas*, ni
*saldo a favor del período anterior*, ni otros conceptos.
*Pregunta:* ¿el saldo a pagar de la DDJJ debe descontar las **retenciones y percepciones de IVA
sufridas** y arrastrar el *saldo a favor* del período anterior? ¿Hay otros conceptos del F2002
que necesiten (p. ej. saldo de libre disponibilidad, ingresos directos)?
*Hoy asumimos:* solo saldo técnico (débito − crédito computable), sin retenciones sufridas ni arrastre.
*Respuesta: En la declaración jurada propiamente dicha que ahora es por IVA Simple, ya no por F.2002 la cuenta es:
Debito fiscal (ventas) – Credito fiscal (compras) = Saldo Técnico del periodo (A favor mio o a favor de Arca según las ventas sean menor o mayor que las compras.
- Saldo Tecnico de periodos anteriores (si vengo teniendo mas compras que ventas)
- Saldo de libre disponibilidad de periodos anteriores Neto de compensaciones (si tuve retenciones y percepciones en periodos anteriores y no me dio a pagar) (y si no hice compensaciones para cubrir anticipos u otros conceptos impositivos que estuviera debiendo)
- Percepciones y Retenciones sufridas en el periodo.
= RESULTADO DEL PERIODO A FAVOR DEL CONTRIBUYENTE O DE ARCA.

### A5. 🟡 "IVA incluido" (campo iva_inc)
*Contexto:* cada línea tiene, además del IVA normal, un campo "IVA incluido" que el sistema
viejo sumaba al total. No tenemos claro qué representa.
*Pregunta:* ¿qué es el "IVA incluido"? ¿Forma parte del IVA que se informa a AFIP o es otra
cosa (percepción de IVA, IVA de otra alícuota)? ¿En qué casos se usa?
*Hoy asumimos:* se guarda y suma al total, pero no lo mandamos como IVA a AFIP.
*Respuesta: No se a que se refiere con IVA incluido, no lo vi a ese campo nunca, tal vez sea la suma de todos los IVA discriminados, al 21%, al 10,5% y al 27%

### A6. 🟡 Alícuotas de IVA habilitadas
*Contexto:* soportamos las alícuotas 0%, 2,5%, 5%, 10,5%, 21% y 27% (las de AFIP).
*Pregunta:* ¿son todas las que usan? ¿Falta o sobra alguna?
*Hoy asumimos:* ese conjunto (0 / 2,5 / 5 / 10,5 / 21 / 27).
*Respuesta: Efectivamente esas son todas las que habilita la ley, 21% es la general y la mas utilizada, 10,5% es para productos y servicios determinados (pan, leche, transporte de personas), 27% es para los servicios masivos que se prestan por red (luz, agua y gas) y la del 2,5% es exclusiva creo que para las imprentas, me aparece en un solo cliente y le hacen por los folletos.

### A7. 🟡 Signo de los comprobantes (qué resta en los totales)
*Contexto:* al sumar el libro IVA, las *notas de crédito restan* (signo −1) y el resto suma.
*Pregunta:* ¿es correcto que solo las notas de crédito resten? ¿Hay algún otro comprobante que
deba restar (o alguna NC que no deba)?
*Hoy asumimos:* restan las notas de crédito; todo lo demás suma.
*Respuesta: Solo las Notas de Crédito restan y van con signo negativo, ya sean A, B, C, ticket nota de crédito 112. Siempre van a decir “NOTA DE CREDITO” y siempre restan.
Factura, ticket, recibo y Nota de debito SUMAN.

### A8. 🟡 Condición de IVA del receptor (RG 5616)
*Contexto:* AFIP exige informar la condición frente al IVA del que recibe la factura. Tenemos
mapeadas: Responsable Inscripto, Monotributo, Exento, Consumidor Final, Cliente del Exterior.
Quedan sin mapear "Responsable No Inscripto" y "No Disponible".
*Pregunta:* ¿se siguen usando esas dos condiciones? Si sí, ¿qué condición de AFIP les
corresponde? ¿O ya no existen y las descartamos?
*Hoy asumimos:* no se usan (si aparece una, el sistema avisa que no la puede mapear).
*Respuesta: Efectivamente “Responsable No Inscripto” se elimino, no debería aparecer mas y “No disponible” es un contribuyente que nunca se dio de alta o nunca completo sus datos en AFIP. Deberia ser por defecto “Consumidor final” siempre que no se tenga información.

### A9. 🟢 Layout de los archivos CITI / RG 3685 (regímenes de información)
*Contexto:* AFIP pide exportar ventas/compras en archivos de texto con un formato exacto. No
tenemos el instructivo con el layout (posiciones/anchos de cada campo).
*Pregunta:* ¿nos pueden facilitar el *diseño de registro vigente* de CITI Compras/Ventas
(o el régimen que corresponda hoy), o un *archivo real de ejemplo*?
*Hoy asumimos:* pendiente, no implementado (no queremos inventar el formato).
*Respuesta: los archivos texto que subimos al PORTAL IVA que es el régimen de información vigente son 4 normalmente, uno para comprobantes de compra y uno para las alícuotas vinculadas a esas compras. Uno para comprobantes de ventas y uno para las alícuotas vinculadas a esos comprobantes de ventas. Te paso la guía y un ejemplo.

