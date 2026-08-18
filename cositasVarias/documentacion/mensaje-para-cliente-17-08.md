# Borrador — mensaje para Juan Pablo (actualizado 18/08/2026)

Retoma las 3 preguntas de `preguntas_respondidas.md` (11/08) que nunca llegaron a mandarse, ya
actualizadas con todo lo que se hizo desde entonces (satélite + los 3 pedidos de navegación del
informe). Pensado para copiar/pegar y ajustar el tono antes de enviarlo.

---

Juan Pablo: avancé fuerte con el satélite y con varios de los puntos concretos de tu informe del
10/08, y quiero mostrarte dónde quedó todo — y cerrar con vos 2 cosas puntuales antes de seguir
escalando.

**Lo que ya está funcionando, con tus datos reales** (no de prueba):
- El padrón único de proveedores: tus 6.481 proveedores reales, depurados, con la cuenta contable
  resuelta automáticamente para cada uno (91,9% de las compras ya se clasifican solas).
- Cargué el histórico completo de Visual IVA: 402.563 compras y 1.146.960 ventas reales, de 2015 a
  2026, de tus 329 contribuyentes.
- El padrón de proveedores y el de clientes ahora están separados en dos pantallas (como pediste
  en tu informe — ya no conviven mezclados).
- Saqué del menú lo que duplicaba con el SIGE: Vencimientos, Roles y Permisos, Actividades. Y
  separé Cuentas/Reportes de Mayor en un grupo de menú propio de Contabilidad, aparte de IVA.
- **El CUIT ya no se carga dos veces**: si el CUIT que estás tipeando ya es un contribuyente
  propio tuyo, el alta de cliente/proveedor te lo trae solo (y al revés: si ya está en el padrón
  de proveedores/clientes, el alta de empresa lo trae solo). Es el pedido que me hiciste desde el
  principio.
- **La navegación guiada por contribuyente, tal cual la describiste**: elegís la empresa, apretás
  "Trabajar", y te lleva directo a sus períodos; desde ahí elegís el período y te lleva directo a
  Ventas. Y arriba de cada pantalla ahora queda fijo un cartel con "Contribuyente · IVA · Período
  [Abierto/Cerrado]" — la confirmación visual constante que pediste.

**Antes de seguir, necesito que confirmes una cosa que define el resto:**

Tu propuesta original del satélite decía que el archivo final tenía que ir a *tu programa
contable propio* — el que armaste vos y usás hoy para cargar y sacar información. Con todo lo que
armamos, terminé construyendo la clasificación para que se quede adentro de nuestro sistema (ya
que ahora la Contabilidad va a vivir acá) — pero es una decisión que tomé yo, no algo que me
hayas confirmado. **¿Es así? ¿O seguís necesitando que le mande un archivo a tu programa
contable?**

Y quedó pendiente el compromiso que hiciste el 10/08 de mandarme, en dos días, la estructura de
ese programa contable — lo sigo esperando, y lo necesito para terminar de definir bien la conexión
entre Cuentas y Contabilidad.

Si me confirmás estos dos puntos, sigo con lo que falta: definir junto a vos cómo se sincroniza el
alta de empresa con el SIGE (para que el CUIT tampoco se duplique entre los dos sistemas, no solo
adentro del nuestro) y qué hacemos con roles y permisos.

---

## Nota interna (no va en el mensaje)

Si la respuesta es "el destino sigue siendo mi programa contable propio", hay que reabrir el punto
7 del análisis (`analisis.md` §9) — reaparece la necesidad de construir la capa de exportación TXT
que hoy se dio por innecesaria, y el trabajo de esta semana (clasificación interna) sigue siendo
insumo válido para armar ese archivo, no se pierde.

Los 3 ítems de navegación del pedido 5b (CUIT único, banner de contexto, wizard empresa→períodos→
ventas) se completaron el 18/08/2026, verificados en vivo, sin esperar esta respuesta — están
commiteados en `frontend-visual-iva`. Detalle técnico en `CLAUDE.md`, sección "🎯 CHECKPOINT
18/08/2026".
