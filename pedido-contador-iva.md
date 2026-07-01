# Pedido al contador — validación final del módulo IVA

> Estado: el código de IVA está **completo** y no quedan preguntas conceptuales (A1–A15 respondidas).
> Lo que falta para dar IVA por **"validado en producción"** son insumos/pruebas operativas, no código.
> Este documento es el mensaje a pasarle al contador. Creado 2026-07-01.

---

## Mensaje para copiar y pegar

**Asunto: Validación final del módulo IVA — necesito un par de casos reales**

Hola [nombre], el sistema de IVA ya está terminado. Antes de darlo por cerrado quiero **contrastarlo
contra casos reales tuyos** para asegurarme de que lo que genera coincide 100% con lo que hoy
presentás. Te pido dos cosas concretas (son datos, nada que tengas que hacer en el sistema nuevo):

### 1. Un caso de "construcción" para la DJ IVA Simple por actividad ⭐ (lo más importante)

Ya tengo cubiertos y validados los casos de **MAFAP/ANCASTI** (reparto por punto de venta) y
**ACEVEDO** (porcentajes fijos). Me falta el reparto **por alícuota de IVA** (el de construcción:
10,5% → residencial / 21% → no residencial). ¿Me podés pasar, de **un cliente de construcción**, de
un mes cualquiera:

- los **datos de ventas y compras** de ese mes (el subdiario o el Libro IVA de ese período), y
- **lo que vos presentás hoy** para ese cliente (tu planilla/calculadora o los CSV que subís al
  Portal IVA).

Así comparo lo que arma el sistema contra tu resultado. Si además tenés a mano un caso **por
receptor/CUIT** (tipo el de Minera Galaxy → una sola actividad), sumalo, pero no es imprescindible.

### 2. Los datos de origen del Libro IVA Digital que me pasaste (mayo 2026)

Me pasaste los 4 archivos del Libro IVA Digital de mayo/2026 (ABERTURAS HERFAS, EL SATÉLITE, etc.).
Para poder verificar que el sistema genera exactamente lo mismo, necesito **de qué cliente/empresa
salieron** y **los comprobantes de ese período** (ventas y compras cargadas). Con eso regenero el
archivo y lo comparo contra el que ya me diste.

Con esas dos cosas doy el IVA por **validado en producción**. (La prueba de subir un archivo al Portal
IVA la hacemos nosotros con el sistema una vez que carguemos ese período — no necesitás tocar nada.)
Gracias!

---

## Contexto interno (no enviar al contador)

Las 5 estrategias de la DJ por actividad (A15) y su estado de validación con casos reales:

| Estrategia | Ejemplo del contador | Ejemplo real que tenemos | Estado |
|---|---|---|---|
| Por punto de venta | MAFAP, ANCASTI | ✅ `softContable/preguntas2/` | validado (tests) |
| Porcentajes fijos | ACEVEDO | ✅ `softContable/preguntas2/` | validado (tests) |
| Por alícuota (construcción) | GRUPO MAZZUCO (mayo 2026) | ✅ `preguntas01-08-2026/` | ✅ **validado END-TO-END (CSV real, match exacto)** |
| Por receptor (CUIT) | GRUPO MAZZUCO (SANATORIO JUNÍN + DROGUERÍA MITRE → alquiler) | ✅ `preguntas01-08-2026/` | ✅ **validado END-TO-END (mismo caso)** |
| Manual (factura por factura) | Bruno Vega | ❌ | Bajo riesgo (sin algoritmo, no se pide) |

**Caso GRUPO MAZZUCO ARQUITECTOS ASOCIADOS SRL — construcción, mayo 2026** (`preguntas01-08-2026/`):
combina DOS estrategias con precedencia **receptor → alícuota** (exactamente la Fase 2):
- SANATORIO JUNÍN (30714341398) y DROGUERÍA MITRE (30668100615) → actividad **681098** (serv.
  inmobiliarios / alquiler), por receptor.
- Resto → **construcción** por alícuota: 21% → **410021** (no residencial), 10,5% → **410011**
  (residencial). En mayo solo hubo no residenciales.
- ✅ **VALIDADO END-TO-END** (2026-07-01): se cargó la empresa + actividades + reglas + los 6
  comprobantes en el sistema y se generó el CSV de la DJ IVA Simple **por el código real** (API →
  servicios → SQL → writer). Salida:
  - Débito fiscal: `681098;1;1;5;12554957,15;2636541;0;` (alquiler) y
    `410021;1;3;5;33197519,43;6971479,08;0;` (construcción).
  - Restitución de débito (la NC nº21): `410021;1;3;5;5630459,62;1182396,52;`.
  - Neto construcción = débito − restitución = 33.197.519,43 − 5.630.459,62 = **27.567.059,81**,
    idéntico a la distribución manual del contador (`DISTRIBUCION IVA.xlsx`). **Match exacto, sin
    diferencias de centavos.** Test de regresión: `backend/tests/Feature/GrupoMazzucoDjE2ETest.php`.

**Punto 2 (Libro IVA Digital):** los 4 archivos en `ecosistema/csv/` (`LIBRO_IVA_DIGITAL_*`, ancho
fijo, mayo 2026) son de **LAVALLE SRL (CUIT 30715402587)**, un corralón grande. Ya validados byte a
byte contra los ejemplos de ARCA; para reconfirmarlos contra este dato real hace falta cargar la
fuente (ventas/compras del período) y regenerar.

**Prueba de subida al Portal IVA = paso NUESTRO, no del contador.** El contador no tiene acceso al
sistema nuevo, así que no puede generar ni subir un archivo del sistema. Depende del punto 1/2: cuando
tengamos un período real cargado, el sistema genera el archivo (4 CSV DJ IVA Simple / 4 TXT Libro IVA
Digital) y lo subimos nosotros al Portal (con la clave fiscal del estudio) para confirmar que ARCA lo
acepta. Queda como validación interna posterior a cargar los datos, no como pedido al contador.

Ver también `pendientesiva.md` (puntos 2 y 3).
