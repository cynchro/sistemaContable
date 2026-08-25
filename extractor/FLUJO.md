# Flujo completo — paso a paso

> Validado en producción el 2026-07-27 contra una cuenta real de ARCA
> (Responsable Inscripto). Este documento describe el flujo tal como
> funciona hoy, no el historial de cómo se llegó hasta acá (eso está en
> `plan-factibilidad-portal-iva.md`).

Pipeline completo: **extraer de ARCA → csv → xlsx tipo "Visual IVA" →
(pendiente) subir al nuevo sistema**. Las primeras dos fases (login +
extracción) tocan ARCA; la tercera (conversión) es procesamiento local
puro, no necesita sesión ni credenciales.

## Cómo se dispara

```bash
npm run login                              # 1 — solo la primera vez o cuando expira
npm run traer -- --periodo 07/2026         # 2 — cada vez que se quiere extraer
npm run convertir -- --compras <csv> --ventas <csv> --salida <xlsx>  # 3 — cuando se quiere el xlsx
```

Los tres comandos corren igual dentro o fuera de Docker (ver README §Uso).

---

## Fase 1 — Login (`npm run login` → `src/cli/login.ts`)

1. Lee `ARCA_CUIT` y `ARCA_CLAVE_FISCAL` de `.env`.
2. Abre un browser Chromium (headless por defecto — ver `PLAYWRIGHT_HEADLESS`)
   y navega a `https://auth.afip.gob.ar/contribuyente_/login.xhtml`.
3. Completa el formulario de Clave Fiscal: CUIT → "Siguiente" → clave →
   "Ingresar" (`src/auth/login.ts`).
4. Confirma que salió del dominio de login dentro de 20s. Si ARCA pidió una
   verificación adicional (SMS, pregunta de seguridad — pasa con
   dispositivos/sesiones nuevos) y se quedó trabado ahí, tira
   `LoginNoConfirmadoError` con instrucciones de reintentar en modo
   headed (`PLAYWRIGHT_HEADLESS=false`) para resolverlo a mano una vez.
5. Guarda la sesión (`storageState` de Playwright: cookies + localStorage)
   en `.sessions/{cuit}.session.json`.

**Nota importante confirmada en vivo**: ARCA invalida la sesión en
aproximadamente **40-45 minutos**. No alcanza con loguear una vez y asumir
que sirve para siempre — ver Fase 2.

---

## Fase 2 — Extracción (`npm run traer -- --periodo MM/YYYY` → `src/cli/traer.ts`)

### 2.1 Verificar/renovar la sesión (`asegurarSesionVigente`)

6. Abre el browser reusando la sesión guardada y navega al home del Portal
   de Clave Fiscal (`portalcf.cloud.afip.gob.ar/portal/app/`).
7. Si ARCA redirige a `.../expiredSession` o al login, la sesión expiró:
   repite la Fase 1 automáticamente (necesita `ARCA_CLAVE_FISCAL` en `.env`)
   y persiste la sesión renovada. Si no hay clave disponible para renovar
   sola, tira un error pidiendo correr `npm run login` de nuevo.

### 2.2 Entrar a Portal IVA (`irAPortalIva`)

8. Busca el link "Portal IVA" en la sección "Servicios | Más utilizados"
   del home (espera hasta 15s a que cargue — esa sección aparece de forma
   asíncrona). Si la cuenta nunca lo usó, puede no estar ahí — ese caso
   (buscarlo por el buscador + posible alta de servicio) todavía no está
   implementado.
9. Lo clickea — ARCA abre una **pestaña nueva** (`target="_blank"`), que es
   sobre la que trabaja todo el resto del flujo.

### 2.3 Abrir la declaración jurada del período (`abrirDdjj`)

10. En "Portal IVA", click en **"Ingresar"** (tarjeta "Nueva declaración
    jurada").
11. Selecciona el período en el `<select>` (formato interno `YYYYMM`, ej.
    `202607`) y click **"Continuar"**.
12. Si ya existe un borrador para ese período, ARCA salta directo a las
    tarjetas de "Registración y declaración" (confirmado en vivo). Ahí,
    click **"Ingresar"** de esa tarjeta — la navegación cambia de dominio, a
    `liva.afip.gob.ar` (el "Libro IVA" real).
    - Si NO existe un borrador, aparecería la pantalla "Datos Iniciales /
      CON MOVIMIENTOS" — ese camino no está confirmado todavía; el código
      lo detecta y corta con un error en vez de improvisar.

En este punto se está parado en `Libro IVA | {periodo} — Original -
Borrador`, con las tarjetas Libro Ventas / Libro Compras / Ajustes.

### 2.4 Por cada libro (Ventas, después Compras)

13. **Ir al libro** (`irALibro`): navega directo por URL
    (`verVentas.do?t=31` / `verCompras.do?t=21` — códigos de sección fijos,
    no cambian por período). No depende de qué botones haya en la pantalla
    actual.
14. **(Opcional, con `--importar`)**: si se pasa ese flag, abre "IMPORTAR ▾"
    → "Importar desde ARCA..." → confirma el modal (`importarDesdeArca`), y
    espera a que el job termine consultando `ajax.do?f=listaTareas` hasta
    que el más reciente quede en estado `"TE"` (Terminada —
    `esperarImportacion`). **Sin `--importar` (default), se asume que el
    borrador ya tiene los comprobantes que corresponden** (cargados a mano
    por el contador o importados en una corrida anterior) y no se dispara
    nada nuevo — solo se lee lo que ya está.
15. **Extraer** (`extraerLibroCsv`): click en el botón **"CSV"** de la
    grilla (primer botón de `.dt-buttons`, se valida el texto antes de
    clickear) e intercepta la descarga.
16. El archivo descargado puede venir **comprimido en un zip** (pasa con
    esta cuenta; con los ejemplos bajados a mano por el usuario no pasaba)
    — si arranca con la firma de zip (`PK`), se descomprime con `jszip` y
    se toma el único archivo de adentro.
17. Se detecta el encoding por los bytes (BOM UTF-8 vs. ISO-8859-1 sin BOM)
    y se decodifica en consecuencia — no se asume uno fijo.
18. Se parsea el CSV (separador `;`, decimal con coma) y se mapea cada fila
    a un `ComprobanteScrapeado` con el desglose completo: neto/exento/no
    gravado, percepciones (IIBB, IVA, otros impuestos nacionales),
    impuestos municipales/internos, crédito fiscal computable (compras), y
    el detalle por alícuota (0/2,5/5/10,5/21/27%).

### 2.5 Guardar

19. **xlsx** (`guardarComprobantesXlsx`): todo el detalle del punto 18,
    con el desglose por alícuota expandido en columnas — `salida/
    {cuit}_{periodo}_{libro}_{timestamp}.xlsx`.
20. **csv original** (`guardarCsvOriginal`): el mismo archivo que entregó
    ARCA (ya descomprimido si hacía falta, bytes sin tocar) — `salida/
    {cuit}_{periodo}_{libro}_{timestamp}.csv`.

Se repiten los pasos 13-20 para Compras.

### 2.6 Cierre

21. Se cierra el browser.

---

## Fase 3 — Conversión a plantilla "Visual IVA" (`npm run convertir` → `src/cli/convertir.ts`)

No toca ARCA ni Playwright — lee los CSV que ya quedaron en `salida/` (de
esta corrida o de una anterior) y arma un xlsx con la misma forma que usaba
el estudio con el sistema legacy. Ver `plan-factibilidad-portal-iva.md`
§"Conversión a plantilla Visual IVA" para el detalle completo de cómo se
reconstruyó la plantilla (catálogos extraídos del xls real, formato de las
4 hojas, casos límite encontrados).

22. Lee y parsea los dos CSV indicados (`--compras`/`--ventas`,
    `leerComprobantesDesdeArchivo` — mismo parser que usa la extracción,
    reusado).
23. Arma 4 hojas (`generarPlantillaVisualIva`):
    - **Compras / Ventas**: texto legible ("001 FACTURAS A", "80 - CUIT",
      "PES PESOS"), igual layout de columnas que el original (28 y 31
      columnas respectivamente).
    - **Hoja2 / Hoja3** ("VISUAL IVA IMPORTACION DE COMPRAS/VENTAS"):
      mismos datos con los códigos puros (traducidos contra los catálogos
      de `src/output/catalogosVisualIva.ts`), como **valores ya
      calculados** — a diferencia del original, que usa fórmulas `VLOOKUP`
      en vivo (innecesario acá: el dato ya viene resuelto de ARCA).
    - Tipo de Comprobante: el código de ARCA (sin ceros) se convierte al
      código CITI de 3 dígitos con `padStart(3, "0")`.
    - Tipo de Operación de Compra/Venta, Actividad y Concepto (categorización
      contable que ARCA no manda): se usa un default fijo — mismo criterio
      que `ecosistema` en el DJ IVA Simple v1 (actividad principal, sin
      bienes de uso, concepto "Productos").
    - **Crédito Fiscal Computable se deja siempre vacío** — confirmado
      contra el archivo real que así se usa en la práctica (el software de
      escritorio lo calcula al importar).
24. Guarda el xlsx en la ruta indicada por `--salida`.

**Si un comprobante tiene "Otros Tributos"** (la plantilla no tiene columna
para eso, ni en Compras ni en Ventas): se avisa por consola con el
punto de venta/número/importe exacto, y ese comprobante queda con el
"VERIFICADOR" de su fila distinto de cero — no se inventa dónde ponerlo. El
dato no se pierde del todo: sigue en el CSV original en `salida/`.

---

## Si algo falla en el medio

Todo corre headless (sin pantalla) — si un paso falla, no hay forma de
"ver" qué pasó a simple vista. Por eso:

- Se guarda una **captura de pantalla** del estado exacto en
  `debug/error_{timestamp}.png`.
- En la extracción del CSV, se guarda además el **archivo crudo tal como lo
  entregó el browser** en `debug/descarga_{libro}_{timestamp}.raw` (zip o
  csv) y, si hubo que descomprimir, el CSV ya extraído en
  `debug/csv_{libro}_{timestamp}.csv` — para diagnosticar sin adivinar.

## Lo que este flujo NUNCA hace

- No presenta ni confirma la declaración jurada — solo lee lo que ya está
  en el borrador (o, con `--importar`, dispara que ARCA importe sus propios
  registros al borrador, que tampoco es presentar).
- No modifica ni borra comprobantes en ARCA.
- Nadie salvo el usuario ve o maneja la Clave Fiscal — el login la lee de
  un `.env` que arma y controla el propio usuario.

## Estado por confirmar (no bloqueante)

- Período sin borrador previo (pantalla "Datos Iniciales / CON
  MOVIMIENTOS") — paso 12.
- Cuenta sin "Portal IVA" en "Más utilizados" (haría falta buscarlo) —
  paso 8.
- El click de confirmación real de `--importar` (`#btnImportarAFIPImportar`)
  — se validó el modal pero nunca se confirmó de verdad en una cuenta real
  (se canceló a propósito para no duplicar una importación ya hecha).
