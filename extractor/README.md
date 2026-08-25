# extractor — extractor de comprobantes IVA vía Portal IVA (ARCA)

Bot (Node + TypeScript + Playwright, dockerizado) que se levanta con el mismo
`docker-compose.yml` de la raíz de `ecosistema` (servicios `extractor` y
`extractor-worker`, detrás del profile `bot` — ver el README de la raíz).
Resuelve el hueco documentado en `AUTOMATIZACIONES.md` §1: ARCA no expone un
web service público para traer en lote las facturas de **compra** (recibidas
de terceros). Este proyecto lo hace automatizando el **Portal IVA**
(`liva.afip.gob.ar`): abre la DDJJ del período, entra a Libro Ventas/Libro
Compras, y descarga el CSV de la grilla con los comprobantes ya cargados —
ver **`FLUJO.md`** para el paso a paso completo (validado contra una cuenta
real) y `plan-factibilidad-portal-iva.md` para el historial de cómo se llegó
a cada decisión.

**En esta etapa el proyecto hace solo la extracción**: deja los comprobantes
en `salida/` (xlsx reprocesado + csv original de ARCA). Cómo se mandan al
nuevo sistema (ecosistema) es un paso posterior, todavía sin decidir.

## ⚠️ Reglas no negociables (ver plan-factibilidad-portal-iva.md)

1. **Nunca presenta ni confirma una DDJJ.** Solo navega, importa (lee lo que
   ARCA ya tiene) y extrae. Presentar una declaración jurada es un acto
   fiscal real — decisión exclusiva del contador, nunca del código.
2. **Ningún asistente de IA entra ni maneja la Clave Fiscal, nunca.** El
   usuario es quien la pone en su propio `.env` y quien corre el comando —
   el código la usa para llenar el formulario en su nombre, pero quien la
   posee y decide correrlo es siempre una persona, no un asistente.

## Arquitectura

```
extractor/
  ├─ npm run login   (Docker, headless, automatizado con .env) → guarda sesión en .sessions/
  └─ npm run traer   (Docker, headless, reusa la sesión) → guarda xlsx en salida/
```

**Login headless y automatizado por defecto** (a pedido del usuario, ya que
quien lo corre y guarda la clave es siempre él, nunca un asistente): con
`ARCA_CUIT` + `ARCA_CLAVE_FISCAL` en `.env`, `npm run login` completa el
formulario solo, sin pantalla, y anda igual dentro de Docker.

⚠️ **Cuándo hace falta el modo manual (headed, fuera de Docker)**: si ARCA
pide una verificación adicional (SMS, pregunta de seguridad — típico la
primera vez que ve un dispositivo/sesión nueva), el login headless no puede
resolverla — tira `LoginNoConfirmadoError` con instrucciones en vez de
colgarse en silencio. Ahí sí hace falta correr `PLAYWRIGHT_HEADLESS=false
npm run login` una vez, con navegador visible, para resolverlo a mano. Una
vez logueado (headless o headed), la sesión (`storageState` de Playwright)
se persiste en `.sessions/{cuit}.session.json` y `npm run traer` la reusa
sin volver a pedir nada, hasta que expire.

**Nota de seguridad**: guardar la Clave Fiscal en un `.env` en texto plano es
más expuesto que escribirla una vez en un browser sin persistirla en ningún
lado — es la contrapartida de no tener que tocar nada manual. Decisión del
usuario, no del código.

## Estado actual (2026-07-26)

- [x] Sesión persistida (`src/auth/session.ts`).
- [x] **Mecanismo de extracción: descarga del CSV de la grilla** (`src/
      extract/libroCsv.ts` + `src/extract/csv.ts`, parser propio sin
      dependencias). Trae el desglose completo por alícuota + percepciones +
      impuestos — validado contra dos CSV reales de otro cliente/período
      (176 compras + 363 ventas, reconciliación de totales OK salvo 11
      Facturas C con hueco real de ARCA, documentado en el plan). Selector
      del botón confirmado en vivo (primer hijo de `.dt-buttons`, valida el
      texto "CSV" antes de clickear).
- [x] Salida en xlsx local (`src/output/xlsx.ts`, con todo el detalle: por
      alícuota, percepciones, impuestos — no solo un resumen).
- [x] Dockerfile + docker-compose.yml (imagen oficial de Playwright, ya trae
      Chromium + dependencias del sistema).
- [x] **Navegación del wizard** (`src/flows/portalIva.ts`): selectores
      reales confirmados contra el DOM en vivo (`select#periodo`,
      `#btnLibroVentas`/`#btnLibroCompras`, `#btnDropdownImportar`,
      `[aria-label*="iva.btn.home.liva.alt"]` para desambiguar el botón
      "Ingresar" correcto, etc. — detalle completo en el plan). `irAPortalIva`
      devuelve la `Page` de la pestaña nueva que abre ARCA.
- [x] Polling de importación (`src/flows/esperarImportacion.ts`): usa el
      endpoint real `ajax.do?f=listaTareas` (estado `"TE"` = Terminada) en
      vez de clickear "ACTUALIZAR" en loop.
- [ ] Sin confirmar en vivo (documentado, no bloqueante): el camino "período
      sin borrador previo" (pantalla "Datos Iniciales / CON MOVIMIENTOS") y
      el click final de confirmación de `importarDesdeArca` (se abrió el
      modal y se canceló a propósito, para no duplicar una importación ya
      "Procesada"). Probar primero contra un período/cuenta de prueba.
- [ ] Todavía no se corrió el flujo completo con el propio Playwright del
      extractor de punta a punta (`npm run login` + `npm run traer`) — los
      selectores se confirmaron inspeccionando el DOM real, pero falta la
      corrida end-to-end con la sesión propia del proyecto.

## Uso

`extractor-worker` es parte del ecosistema y **arranca solo** con
`docker compose up -d` desde la raíz (`restart: unless-stopped`) — no hace
falta prenderlo a mano. Lo único manual es el login inicial de cada CUIT.

⚠️ **`ECOSISTEMA_BASE_URL`**: corriendo por Docker, el `docker-compose.yml`
de la raíz ya lo fija a `http://modux-backend:80` (el nombre del servicio en
`app-network`) — no hace falta tocarlo en `.env`. Solo importa si corrés
`npm run worker`/`npm run traer` a mano **fuera** de Docker (host): ahí sí
tiene que ser `http://localhost:8077` en tu `.env` local.

### 1. Login (Docker, headless, automatizado)

```bash
cp .env.example .env   # completar ARCA_CUIT, ARCA_CLAVE_FISCAL y ECOSISTEMA_API_KEY

cd ..   # el docker-compose.yml vive en la raíz de ecosistema, no acá
docker compose build extractor
docker compose run --rm extractor npm run login
```

Si tira `LoginNoConfirmadoError` (ARCA pidió verificación adicional), correr
una vez en modo manual con navegador visible (fuera de Docker, porque
`headless: false` necesita una pantalla real):

```bash
npm install
npx playwright install chromium
PLAYWRIGHT_HEADLESS=false npm run login
```

### 2. Extracción (Docker, headless, reusa la sesión)

```bash
docker compose run --rm extractor npm run traer -- --periodo 07/2026
```

Por cada libro quedan dos archivos en `salida/` (montado desde el host, se
ven sin entrar al contenedor): el **xlsx** reprocesado (con el desglose por
alícuota expandido en columnas) y el **csv** original tal como lo entregó
ARCA (ya descomprimido si venía en zip, sin tocar el contenido).

### Sin Docker (equivalente, para debugging)

```bash
npm run login
npm run traer -- --periodo 07/2026
```

### 3. Convertir a plantilla estilo "Visual IVA" (sin volver a tocar ARCA)

Toma los CSV ya guardados en `salida/` (de esta corrida o de una anterior) y
arma un xlsx con la misma forma que usaba el estudio con el sistema legacy
(4 hojas: Compras/Ventas + Hoja2/Hoja3 de importación) — ver
`plan-factibilidad-portal-iva.md` §"Conversión a plantilla Visual IVA" para
el detalle completo (catálogos, defaults, validación):

```bash
npm run convertir -- \
  --compras salida/{cuit}_{periodo}_compras_{ts}.csv \
  --ventas salida/{cuit}_{periodo}_ventas_{ts}.csv \
  --salida salida/plantilla-visual-iva.xlsx
```

No usa Playwright ni credenciales — es puro procesamiento local del CSV.

## Por qué proyecto aparte y no un módulo de `backend/`

- Playwright (Node) es el stack correcto para automatizar un portal con login
  pesado — meterlo en el backend PHP hubiera significado invocar un
  subproceso Node de todos modos.
- Aislar esto en su propio proyecto evita que un cambio de layout de ARCA
  (que va a pasar) obligue a tocar el backend — solo se toca este proyecto.
- Automatizar el propio Portal IVA con la propia Clave Fiscal para traer los
  propios datos es una automatización legítima del contribuyente sobre su
  cuenta — igual que lo haría a mano, sin Excel de por medio.

## Botón "Liquidar IVA" (25/08/2026) — `npm run liquidar` y modo `worker`

Esta sección está bastante más nueva que el resto del documento (arriba
queda como historial de cómo se llegó acá — no desactualizado, solo previo).
El "siguiente paso" que esta sección dejaba abierto ("cómo se sube la
plantilla al nuevo sistema") ya está resuelto: en vez de una plantilla xlsx
intermedia, el proyecto habla directo con la API de `ecosistema`
(`src/ecosistema/client.ts`, autenticado por API key) en las dos direcciones.

- **`npm run liquidar`** (`src/cli/liquidar.ts`): CLI manual para un CUIT fijo
  (`ARCA_CUIT` de `.env`), un período puntual. `--traer` (ARCA → ecosistema) y/o
  `--subir` (ecosistema → ARCA, sube el Libro IVA Digital ya calculado). Ver el
  `--help` implícito del propio script para los flags.
- **`npm run worker`** (`src/cli/worker.ts`, servicio `extractor-worker` del
  `docker-compose.yml` de la raíz — arranca solo con `docker compose up -d`,
  `restart: unless-stopped`): proceso de larga duración que hace polling a
  la cola de `ecosistema`
  (`GET /iva/liquidaciones/pendientes`) — el usuario pide la liquidación desde
  la UI de `ecosistema` (botón "Liquidar IVA"), este proceso la toma y la
  ejecuta sola, sin que nadie toque una terminal. Reusa el mismo flujo que
  `liquidar.ts` (`src/liquidacion/ejecutar.ts`, compartido) — la diferencia es
  que cada pedido de la cola trae SU PROPIO CUIT (el worker sirve a todos los
  clientes del estudio, uno detrás de otro), y la Clave Fiscal nunca vive en
  `.env` acá: se pide a `ecosistema` (`POST /iva/liquidaciones/{id}/credencial`,
  cifrada en reposo con `App\Support\Crypto` del lado del backend) SOLO cuando
  la sesión guardada de ese CUIT puntual expiró.
- **Límite real, no resuelto por diseño**: el worker necesita que el CUIT ya
  tenga una sesión de ARCA bootstrapeada al menos una vez (`npm run login` a
  mano, ver arriba) — el primer login de un CUIT nuevo, o uno al que ARCA le
  pide verificación adicional (SMS), sigue siendo un paso humano. El worker
  detecta el caso (sin sesión guardada para ese CUIT) y reporta el error claro
  a `ecosistema` en vez de colgarse.
- **Bug real corregido (25/08/2026)**: `esperarImportacion.ts` identificaba la
  tarea de importación por orden de `codigo` descendente, asumiendo que era
  cronológico — no lo es (confirmado en vivo, una tarea vieja puede tener un
  `codigo` numéricamente mayor que una nueva). Ahora compara contra una foto
  de `listaTareas` tomada ANTES de clickear "Importar" — sin ese fix, un
  worker desatendido podía reportar falsos positivos con el resultado de una
  corrida anterior.
