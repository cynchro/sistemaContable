# Borrador — mensaje para Juan Pablo (17/08/2026)

Retoma las 3 preguntas de `preguntas_respondidas.md` (11/08) que nunca llegaron a mandarse, ya
actualizadas con lo que se hizo esta semana. Pensado para copiar/pegar y ajustar el tono antes de
enviarlo.

---

Juan Pablo: avancé fuerte esta semana con el satélite y quiero mostrarte dónde quedó, y cerrar
con vos 2 cosas puntuales antes de seguir escalando.

**Lo que ya está funcionando, con tus datos reales** (no de prueba):
- El padrón único de proveedores: tus 6.481 proveedores reales, depurados, con la cuenta contable
  resuelta automáticamente para cada uno (91,9% de las compras ya se clasifican solas).
- Cargué el histórico completo de Visual IVA: 402.563 compras y 1.146.960 ventas reales, de 2015 a
  2026, de tus 329 contribuyentes.
- El padrón de proveedores y el de clientes ahora están separados en dos pantallas (como pediste
  en tu informe — ya no conviven mezclados).
- Saqué del menú lo que duplicaba con el SIGE: Vencimientos, Roles y Permisos, Actividades. Y
  separé Cuentas/Reportes de Mayor en un grupo de menú propio de Contabilidad, aparte de IVA.

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

Si me confirmás estos dos puntos, sigo con lo que falta (la navegación por contribuyente con el
cartel fijo que pediste, y terminar de sacar del sistema lo que sigue duplicado).

---

## Nota interna (no va en el mensaje)

Si la respuesta es "el destino sigue siendo mi programa contable propio", hay que reabrir el punto
7 del análisis (`analisis.md` §9) — reaparece la necesidad de construir la capa de exportación TXT
que hoy se dio por innecesaria, y el trabajo de esta semana (clasificación interna) sigue siendo
insumo válido para armar ese archivo, no se pierde.
