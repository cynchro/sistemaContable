# Consultas para el contador — IVA

> Hoja limpia con **lo único que falta resolver de IVA**. Todo el resto de IVA ya quedó
> respondido/confirmado (ver `preguntas.md`, sección A, ítems A1–A14). Estas son 2 consultas:
> la **1 es prioritaria** (puede cambiar cómo cargamos los comprobantes); la **2 es opcional**.

---

## 🔴 1) Declaración Jurada de IVA Simple — apertura por actividad (PRIORITARIA)

ARCA permite presentar la DJ de IVA Simple con la operatoria **abierta por actividad**. Hoy lo
generamos con algunos supuestos; necesitamos confirmarlos:

1. **¿Una sola actividad o varias?**
   ¿Las empresas del estudio suelen tener **una sola actividad** registrada en AFIP, o hay varias
   con **distintas actividades** donde cada factura/compra iría a una actividad diferente?
   - Si es **una sola** → ya está, imputamos todo a la actividad principal.
   - Si hay que **discriminar por comprobante** → tenemos que sumar el dato "actividad" al cargar
     cada venta y compra (es un cambio de carga).

2. **¿Registran venta de bienes de uso?**
   ¿Venden a veces **bienes de uso** (una máquina, un rodado de la empresa, etc.)? La DJ los separa
   de las ventas comunes. Hoy mandamos todo como venta común.

3. **Tipo de compra (crédito fiscal).**
   En las **compras**, ¿necesitan diferenciar entre **bienes / locaciones / servicios / inversiones
   en bienes de uso**? La DJ pide esa clasificación. Hoy mandamos todo como "compra de bienes".

4. **¿Tienen dación en pago?**
   ¿Alguna vez cancelan una operación entregando un bien en lugar de dinero (**dación en pago**)?
   Hoy lo informamos en cero.

**Lo que hoy asumimos:** una sola actividad (la principal de la empresa), sin bienes de uso, las
compras como "bienes", sin dación en pago, y sin exportaciones.

---

## 🟢 2) Libro IVA Digital — archivo de comprobantes ANULADOS (opcional)

¿Necesitan presentar el **archivo de comprobantes de ventas anulados** del Libro IVA Digital?
Nos comentaste que el sistema viejo (Visual) **no lo generaba**.

- Nosotros sí registramos las anulaciones, así que **podemos generarlo** si lo presentan.
- Si no lo usan, lo dejamos como está (no se genera).

---

### Prioridad
- La única que puede **cambiar trabajo nuestro** es la **1, sub-pregunta 1 (una vs. varias
  actividades)**. El resto son confirmaciones de "sí/no".
