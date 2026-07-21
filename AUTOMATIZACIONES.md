# Automatizaciones del sistema

> Este documento junta, en un solo lugar, qué se automatizó (y por qué), qué se evaluó y se
> descartó, y cómo extender el enfoque a futuras automatizaciones. Nace de la pregunta del
> usuario "¿qué se podría automatizar de este proyecto?" — el pedido original era traer
> comprobantes de ARCA a la base y auditar contra lo cargado a mano.

## Resumen

| # | Automatización | Estado | Alcance |
|---|-----------------|--------|---------|
| 1 | Auditoría de ventas vs. ARCA (WSFEv1) | ✅ Implementado | Ventas propias emitidas |
| 2 | Verificar puntual: venta cargada vs. ARCA (spot-check) | ✅ Implementado | Ver §6 |
| — | Traer compras recibidas de ARCA en lote | ❌ Descartado (sin insumo) | Ver §2 |
| — | V3: verificación masiva/automática (no on-demand) | Diferido | Ver §5 |

---

## 1. Por qué "compras desde ARCA" no es automatizable hoy

La idea original era un botón que trajera los comprobantes de ARCA (ventas **y** compras) y
los comparara contra lo cargado a mano. Investigando los web services de AFIP/ARCA:

- **Ventas propias emitidas**: si el estudio emite con nuestro propio flujo de CAE (WSFEv1),
  ARCA reconoce esos comprobantes y **se pueden consultar** vía SOAP (`FECompConsultar`,
  `FECompUltimoAutorizado`). Esto habilita una auditoría real — ver §3.
- **Compras recibidas de terceros**: ARCA **no expone un webservice público** para traer en
  lote las facturas que un contribuyente recibió de sus proveedores. Ese dato solo está
  disponible en el portal web **"Mis Comprobantes"**, que requiere login con Clave Fiscal y no
  tiene API — es exactamente por eso que hoy ese lado se resuelve con el **importador CSV**
  (`docs/frontend/flujos-de-uso.md` §1: el usuario exporta el CSV desde el portal y lo sube).

Automatizar el lado de compras de punta a punta implicaría scrapear el portal web (frágil,
contra los términos de uso de ARCA) o pagar un proveedor externo que ya lo hace. **Se
descartó** por no haber un camino técnico limpio con la infraestructura actual.

## 2. Decisión de alcance (V1)

Se automatizó solo el lado que sí tiene un camino oficial: la conciliación de **ventas
propias** contra lo que ARCA reconoce, reusando el WSFEv1 que ya estaba integrado para emitir
CAE. Reglas de diseño acordadas:

- **Sin tabla de auditoría persistida.** El proyecto ya tiene como convención "totales
  derivados on-the-fly, sin columnas persistidas" (ver `CLAUDE.md`); la comparación se calcula
  en vivo contra ARCA cada vez que el usuario pide "Consultar ARCA" / "Actualizar", igual que ya
  hace la emisión de CAE (`FECompUltimoAutorizado` en cada emisión).
- **Solo se comparan combinaciones ya usadas localmente** (punto de venta + tipo + letra). No
  se puede adivinar qué tipos de comprobante emitió un punto de venta si acá nunca se cargó ni
  una vez — limitación conocida, aceptable para v1 (ver §5).
- **Ninguna escritura automática.** El sistema nunca inserta una venta por su cuenta: el
  usuario ve el hueco, consulta el detalle y decide si lo carga (revisado y completado a mano).

## 3. Qué se implementó: Auditoría de ventas vs. ARCA

### Flujo (desde la UI)

**Ruta:** menú IVA → **Auditoría ARCA** (`/empresas/:empresaId/auditoria-afip`, a nivel
empresa, no depende de período).

1. Botón **"Consultar ARCA"**: por cada combinación punto de venta + tipo + letra que la
   empresa ya usó alguna vez, se le pregunta a ARCA (`FECompUltimoAutorizado`) el último número
   que tiene autorizado y se compara contra el máximo cargado localmente. Tabla con columnas
   Punto de venta / Tipo / Letra / Último en ARCA / Último local / Estado (`Al día` en verde o
   `N faltante(s)` en amarillo).
2. Para una fila con faltantes: se elige un número del rango faltante y **"Consultar"** trae el
   detalle real de ARCA (`FECompConsultar`) — fecha, total, neto, discriminación por alícuota,
   CAE y vencimiento — y si ese número ya está cargado localmente (`ya_cargado`), para no
   duplicar.
3. Si no está cargado: botón **"Cargar como venta"** — resuelve a qué período pertenece la
   fecha del comprobante y abre el alta de venta **precargada** con los datos de ARCA (mismo
   mecanismo que los presets de "Comprobante manual" de Compras) para que el usuario revise
   cliente/rubro/cuenta y guarde.

### Backend

| Archivo | Qué hace |
|---|---|
| `app/Modules/Iva/Afip/Wsfe/WsfeClient.php` (+ `AfipWsfeClient.php`) | Método nuevo `consultarComprobante()` → `FECompConsultar` (mismo patrón que `ultimoAutorizado()`/`solicitarCae()`: mismo WSDL, misma auth WSAA). |
| `app/Modules/Iva/Afip/Wsfe/ComprobanteConsultado.php` (nuevo) | DTO que normaliza la respuesta SOAP (mismo estilo que `ComprobanteCae.php`). Si ARCA no tiene el comprobante, devuelve `Errors` código 602 en vez de tirar excepción — acá se traduce a `encontrado: false`. |
| `app/Modules/Iva/Afip/Wsfe/AlicuotaIvaResolver.php` | Método nuevo `porcentaje(int $id): ?float` — inverso de `id()`: el campo `Id` que devuelve ARCA en `Iva.AlicIva[]` es el **Id de alícuota WSFEv1**, no el porcentaje; hay que resolverlo contra la misma tabla que usa la emisión de CAE. |
| `app/Modules/Iva/Repositories/VentaRepository.php` | 3 métodos de solo lectura: `tiposUsados()` (combos punto de venta+tipo+letra por empresa), `maxNumero()` (máximo local de una combinación) y `findByComprobante()` (si un número puntual ya está cargado). |
| `app/Modules/Iva/Services/AuditoriaAfipService.php` (nuevo) | `resumen()` y `detalleComprobante()`. Sin estado propio: reusa `VentaRepository`/`EmpresaRepository`/`WsfeClient` ya registrados. |
| `app/Modules/Iva/Controllers/AuditoriaAfipController.php` (nuevo) | `GET /empresas/{id}/auditoria-afip` y `GET /empresas/{id}/auditoria-afip/comprobante`. |
| `seeders/PermisosIvaSeeder.php` | Key nueva `iva.auditoria-afip` (solo lectura). No se reusó `iva.auditoria` (log de auditoría de escrituras, otra cosa) ni `iva.facturacion` (específico de emisión de CAE). |
| `tests/Feature/AuditoriaAfipTest.php` (nuevo) | Combo al día, combo con huecos, comprobante no encontrado en ARCA (602), comprobante ya cargado localmente, RBAC 403 sin el permiso. Usa un doble de `WsfeClient` (sin red ni certificado), mismo patrón que `FacturaElectronicaTest.php`. |

### Frontend

| Archivo | Qué hace |
|---|---|
| `src/api/auditoriaAfip.ts` (nuevo) | Tipos `ResumenAuditoriaItem` / `ComprobanteAfipDetalle` + `getResumenAuditoria()` / `getComprobanteAfip()`. |
| `src/modules/iva/auditoria/AuditoriaAfipPage.tsx` (nuevo) | La pantalla (tabla + modal de detalle). No dispara la consulta a ARCA automáticamente al entrar — requiere el botón, igual que "Emitir CAE". |
| `src/modules/iva/auditoria/afipPreset.ts` (nuevo) | `VentaPreset` + `buildVentaPreset()`: mapea el detalle de ARCA a los campos del alta de venta. |
| `src/modules/iva/ventas/VentaFormModal.tsx` | Prop nueva `preset?: VentaPreset` (solo aplica en alta), mismo mecanismo que ya tenía `CompraFormModal` con sus presets de "comprobante manual". |
| `src/modules/iva/ventas/VentasList.tsx` | Lee `location.state.preset` al montar (llega por `navigate(..., {state})` desde la Auditoría), abre el modal precargado y limpia el `state` para que un refresh no lo reabra. |
| `src/layout/nav.ts` / `src/App.tsx` | Ítem "Auditoría ARCA" en el menú IVA (habilitado con empresa activa, sin depender de período) + ruta. |

### Verificación

- **Backend**: 570 tests verdes (5 nuevos), PHPStan nivel 6 y PHPCS sin errores.
- **Frontend**: `tsc -b`, `oxlint` y `vite build` sin errores/warnings propios.
- **En vivo, contra ARCA homologación** (empresa real "GRUPO MAZZUCO SA", CUIT del estudio
  23321452639): el botón "Consultar ARCA" trajo los últimos números autorizados reales por
  punto de venta, y la consulta de detalle de un comprobante puntual devolvió los datos reales
  de ARCA (fecha, neto $1000, alícuota 21% con importe $210, CAE, vencimiento) correctamente
  cruzados con la venta local existente (`ya_cargado: true`, `venta_id_local` correcto). Sin
  errores de consola.
- El camino de "hueco real" (`faltantes > 0` → seleccionar número → "Cargar como venta" →
  período resuelto por fecha → modal precargado) no tuvo un caso real en los datos de la demo
  (ARCA homologación no tenía comprobantes por delante de los cargados localmente) — se validó
  por tests automatizados (mocks) + code review, siguiendo exactamente el patrón ya probado en
  producción de los presets de `CompraFormModal`.

## 4. Decisiones de diseño relevantes

- **Por qué no una tabla de auditoría con historial**: se evaluó y se descartó a propósito
  (ver §2) para no romper la convención del proyecto de derivar todo on-the-fly. Si en el
  futuro se necesita un historial (por ejemplo, para no re-consultar ARCA en cada carga de
  pantalla, o para trazar "cuándo se detectó" un hueco), es la primera pieza a agregar — una
  tabla simple `venta_auditoria_afip` con snapshot de la comparación.
- **Por qué la comparación es por rango de número autorizado y no comprobante por comprobante**:
  `FECompUltimoAutorizado` es una sola llamada SOAP por combinación (barato); pedir el detalle
  de cada número (`FECompConsultar`) es una llamada por comprobante, así que solo se hace **on
  demand** cuando el usuario elige investigar un hueco puntual — evita hamerear a ARCA sin que
  el usuario lo pida.
- **Por qué no se verifica automáticamente (en lote) cada venta ya cargada**: sería 1 llamada
  SOAP por fila y, si el CAE se emitió por nuestro propio flujo, no aporta demasiado la mayoría
  de las veces (ya sabemos que coincide). Se implementó como botón **"Verificar" on-demand por
  fila** (ver §6), no automático — el usuario lo dispara cuando sospecha de un comprobante
  puntual (por ejemplo uno importado por CSV).

## 5. Fuera de alcance / diferido

- **Detectar tipos de comprobante nunca cargados localmente**: si un punto de venta emitió en
  ARCA un tipo que acá nunca se usó ni una vez, la auditoría actual no lo puede comparar (no
  hay con qué combo cruzarlo). Requeriría otra fuente (p. ej. `FEParamGetTiposCbte` combinado
  con alguna heurística) o simplemente que el usuario cargue el tipo una vez.
- **Automatización del lado de compras**: sigue bloqueada por falta de un webservice oficial de
  ARCA (ver §1). Si en el futuro el estudio contrata un proveedor de datos que sí exponga una
  API sobre "Mis Comprobantes", se podría enchufar ahí con un service nuevo detrás de la misma
  interfaz de importación que ya existe (CSV), sin tocar el resto del sistema.
- **Historial persistido de auditorías** (ver §4).
- **Verificación en lote/automática** de todas las ventas de un período contra ARCA (correr el
  "Verificar" de §6 para cada fila sin que el usuario lo pida una por una) — descartado a
  propósito por ahora para no hamerear a ARCA sin pedido explícito; si el volumen de comprobantes
  lo justifica, es la extensión natural del botón por fila.

## 6. Verificar puntual: venta cargada vs. ARCA (spot-check por fila)

Complementa la Auditoría ARCA (§3): esa pantalla solo detecta **huecos de numeración**
(comprobantes que ARCA tiene y acá no). Este botón cubre el caso inverso — una venta que **sí**
está cargada localmente pero cuyo total o CAE puede no coincidir con lo que ARCA tiene
realmente registrado (típicamente un CAE mal tipeado al importar un CSV de "Mis Comprobantes",
o un total editado a mano después de haber emitido el CAE).

**No hizo falta tocar el backend**: `AuditoriaAfipService::detalleComprobante()` /
`GET /empresas/{id}/auditoria-afip/comprobante` (ya construidos para §3) hacen exactamente lo
necesario. Lo único que faltaba era declarar en el frontend un campo que el backend **ya**
devolvía (`VentaRepository::findPaginado()` hace `SELECT v.*`, así que `tipo_comprobante_id`
ya viajaba en el JSON del listado de ventas, solo no estaba tipado en el frontend).

| Archivo | Qué cambió |
|---|---|
| `frontend/src/api/ventas.ts` | Agregado `tipo_comprobante_id: number \| null` a la interfaz `Venta` (dato que el backend ya enviaba). |
| `frontend/src/modules/iva/ventas/VentasList.tsx` | Botón **"Verificar"** por fila (junto a Editar/Mover/Eliminar), visible solo si la fila tiene punto de venta + número + letra + tipo (lo mínimo para resolver el `CbteTipo` de AFIP). Llama a `getComprobanteAfip()` (mismo cliente que ya existía) y muestra el resultado en un `CAlert` (mismo patrón que el mensaje de "Emitir CAE"): compara total (tolerancia $0,01) y CAE contra lo que devuelve ARCA. |

**Verificado en vivo** contra ARCA homologación (empresa "GRUPO MAZZUCO SA", id 34, período
2026-05 Mayo): se probaron los tres estados posibles —
- *Coincide*: comprobante con total y CAE iguales a ARCA (validado en la sesión de §3).
- *Difiere*: `A 1-1: difiere de ARCA — total local $ 123.500,00 vs. ARCA $ 1.210,00.` (dato de
  demo con total distinto al que ARCA tiene realmente registrado para ese punto de
  venta+tipo+letra+número).
- *No encontrado*: `A 3-777: ARCA no tiene este comprobante registrado.`

Sin errores de consola en ninguno de los tres casos. `tsc -b`, `oxlint` y `vite build` limpios
(no ameritó tests de backend nuevos: el endpoint reusado ya está cubierto por
`AuditoriaAfipTest.php`).

## 7. Otras ideas de automatización evaluadas (no implementadas)

Discutidas con el usuario al arrancar este frente, quedan pendientes de priorizar:

- **Vencimientos fiscales**: recordatorios automáticos (cron) cuando se acerca la fecha de una
  obligación sin presentar (módulo Fiscal ya tiene el modelo de `vencimientos` con workflow de
  estado — falta el disparador temporal).
- **Honorarios recurrentes**: generación mensual automática en vez de alta manual repetida.
- **Sueldos**: recordatorio/generación de la liquidación del mes al abrir el período.
