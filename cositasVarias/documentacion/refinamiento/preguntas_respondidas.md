# Preguntas del cliente — respondidas

El informe del 10/08/2026 (`documentacion/INFORME-ALEXIS-SAUCEDO-10-08-2026_(actualizado).docx`)
hace tres preguntas directas. Este documento las responde una por una, con la base técnica
verificada en `documentacion/refinamiento/analisis.md`. No repite el análisis completo — lo
referencia donde corresponde.

---

## 1. "¿Qué limitaciones tiene?" — sobre el módulo Clientes / navegación por contribuyente

Pregunta completa, en contexto (informe, punto 5): el cliente pide una navegación tipo "elijo el
contribuyente → veo sus períodos → veo sus ventas", con un indicador fijo arriba tipo
"Contribuyente tal · IVA · Período tal", y pregunta qué limitaciones tiene ese esquema de trabajo.

**Respuesta:**

**Hoy esa navegación no existe.** El ítem "Clientes" del menú no es un directorio de
contribuyentes — apunta a los clientes *de la empresa activa* (para cargar ventas), que es un
concepto distinto al que el cliente tiene en mente. Elegir contribuyente y período se hace hoy con
dos menús desplegables en la parte de arriba de la pantalla (no con una pantalla dedicada), y no
hay ningún cartel fijo tipo "Contribuyente X · IVA · Período Y" que se repita en las pantallas de
trabajo — solo un link para volver al listado de empresas. Esto está confirmado como un pedido
100% válido y queda anotado como trabajo a construir (`analisis.md` §7, `planificacion.md` Fase 3).

**La limitación real y concreta que existe hoy** (no de navegación, sino de permisos): un usuario
con permiso para operar ventas puede operar las ventas de **cualquier** empresa/contribuyente del
estudio — no hay forma de restringir a un operador a trabajar solo con un contribuyente puntual.
Si mañana un asistente solo debería ver "Grupo AC SRL" y nada más, hoy no hay cómo configurar eso;
el permiso es a nivel de función (ventas, compras, libro IVA...), no a nivel de qué contribuyente.
Esto queda documentado como limitación conocida, y si es un requisito para el lanzamiento hay que
decirlo — es trabajo adicional, no algo que se resuelva solo con la navegación nueva.

---

## 2. "¿Se puede trabajar sobre el mismo cliente en paralelo? ¿Hay bloqueos?"

Pregunta completa (informe, punto 5): si un operador puede estar en ventas y otro en compras del
mismo contribuyente al mismo tiempo, y si hay bloqueos cuando dos personas abren la misma parte.

**Respuesta:**

**Sí, se puede trabajar en paralelo sin problema** cuando son partes distintas del mismo
contribuyente — por ejemplo, un operador cargando ventas y otro cargando compras al mismo tiempo.
Son tablas separadas, no hay ningún conflicto ahí.

**El caso que sí tiene un riesgo real es otro**: si **dos personas editan exactamente el mismo
comprobante** al mismo tiempo. Hoy **no existe ningún bloqueo** para eso — se revisó el código a
fondo y no hay ningún mecanismo que avise "esto lo está editando otra persona" ni que impida que
se pisen los cambios. Si pasa, gana el último que guarda, y el primero pierde su cambio sin que el
sistema le avise. En la práctica, es un escenario poco común (dos personas editando el mismo
comprobante puntual a la vez), pero hoy no está cubierto.

Hay dos caminos para resolverlo si se necesita: un aviso liviano ("Fulano está editando esto ahora
mismo") o un bloqueo real que impida guardar si alguien más ya lo está editando. Cuál conviene
depende de qué tan seguido pasa este escenario en la operación diaria del estudio — es una decisión
pendiente, no algo que valga la pena construir sin saber si hace falta (`planificacion.md`,
Fase 0, pregunta de concurrencia).

---

## 3. "¿Qué necesitás de mí para que te arme el satélite?"

Esta era la pregunta más urgente del informe, y la que más cambió de respuesta a medida que se
investigó a fondo. La respuesta corta: **no hace falta arrancar de cero** — gran parte de la lógica
que pide el satélite ya está construida. Lo que falta son unas pocas definiciones puntuales, no
trabajo de programación abierto.

### Lo que necesitamos que confirmes (en este orden)

**1. El destino.** El documento original del satélite (el que armaste para Alex/SIGE) dice que el
archivo final tiene que ir a *tu programa contable propio* — el que armaste vos y usás hoy para
cargar y sacar información (el mismo que mencionás en el punto 6 de tu informe, sobre las
Cuentas). Necesitamos que confirmes si eso sigue siendo así, o si ahora que vamos a construir un
módulo de Contabilidad dentro de este sistema, el destino cambia y pasa a ser directamente acá.
Esto define todo lo demás.

**2. Si el destino sigue siendo tu programa contable propio: el formato exacto del archivo.**
Columnas, orden, separador, encabezados — es el único dato técnico que el documento original dejaba
pendiente de definir con vos, y nunca se terminó de cerrar. Sin esto no se puede armar la
exportación final, aunque el resto ya esté resuelto.

**3. Confirmar la fuente de los comprobantes.** Encontramos que tu SIGE ya tiene un bot propio
("HaddyBot") que ya entra a ARCA, trae los comprobantes y los carga automáticamente. Nosotros
también teníamos un desarrollo aparte (`extractor/`) haciendo básicamente lo mismo, sin conectar
todavía a nada. No tiene sentido mantener las dos cosas — decinos si HaddyBot es la fuente que
querés usar, así no duplicamos ese trabajo tampoco.

**4. Los datos reales del padrón, si querés arrancar ya con volumen real.** Ya existe una base
depurada de 376.819 filas (proveedor + cuenta) del trabajo que hicimos en julio — está lista para
usarse en cuanto lo confirmes, no hace falta rearmarla.

### Lo que ya está construido y no hay que volver a hacer

Ya tenemos funcionando (aunque todavía no te lo mostramos con datos reales, y por eso probablemente
no lo tenías presente):

- El **padrón** que identifica a cada proveedor una sola vez para todo el estudio.
- La **regla de imputación contable**: cuenta por defecto del proveedor, con excepción por punto
  de venta (el caso que nos diste, MUCHAY SRL facturando distinto según el punto de venta) y
  excepción por contribuyente.
- La **clasificación de ventas** por punto de venta y tipo de comprobante.
- Una **bandeja de pendientes**: si un comprobante no matchea con ningún proveedor conocido, no se
  pierde ni se imputa mal — queda separado para que alguien lo revise a mano.
- Un **motor de alertas** que compara el mes contra el promedio histórico de cada contribuyente,
  para detectar compras o ventas fuera de lo normal.

Lo que falta construir, una vez que confirmes los puntos de arriba, es más acotado de lo que
parece: la lectura del archivo que exporta Visual IVA (o de lo que traiga HaddyBot, según lo que
confirmes en el punto 3) y la generación del archivo final en el formato que definamos. La parte
más difícil — las reglas de negocio — ya está resuelta.

**Nuestra propuesta concreta**: antes de programar la parte que falta, te mostramos en una sesión
corta lo que ya está andando (con datos de prueba), para que confirmes si es lo que tenías en
mente. Ahí mismo cerramos los 4 puntos de arriba y arrancamos con lo que falte, ya sin vueltas.

---

## Referencias

Detalle técnico completo y verificado detrás de cada respuesta: `documentacion/refinamiento/
analisis.md` (§0 el hallazgo de fondo sobre el SIGE, §7 el módulo Clientes, §9 el satélite) y
`documentacion/refinamiento/planificacion.md` (Fase 0 las preguntas pendientes, Fase 5 el plan del
satélite, Fase 6 estas mismas dos primeras respuestas en su versión de trabajo interno).
