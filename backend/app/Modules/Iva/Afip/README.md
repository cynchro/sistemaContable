# Facturación Electrónica AFIP/ARCA — Guía del módulo

Guía práctica del subsistema `App\Modules\Iva\Afip\*` (WSAA + WSFEv1 + Padrón). Pensada para
**retomar código heredado**: explica cómo está armado, **cómo emitir una factura paso a paso** y
cómo extenderlo. Validado en vivo contra homologación (CUIT 23321452639).

---

## 0. TL;DR — emitir una factura en 5 pasos

1. **Certificado** (una sola vez): `php modux afip:cert-key` → `php modux afip:cert-csr "Mi Empresa"`.
2. **Portal WSASS** (una sola vez, manual): subí `request.csr`, descargá el certificado y
   **autorizá el servicio `wsfe`** al certificado.
3. **`.env`**: `AFIP_ENV`, `AFIP_CUIT`, `AFIP_CERT_PATH`, `AFIP_KEY_PATH`.
4. **Probá la conexión**: `php modux afip:wsfe-dummy` (debe dar OK) y `php modux afip:wsaa wsfe` (TA).
5. **Emití**: creás una **venta** con punto de venta + letra + discriminación de IVA, y llamás
   `POST /empresas/{e}/periodos/{p}/ventas/{v}/cae`. Devuelve el **CAE**.

---

## 1. Cómo está armado (mapa mental)

```
  Venta (DB)
     │  FacturaElectronicaService::autorizar(empresaId, periodoId, ventaId, tenantId)
     ▼
  ┌──────────────────────────────────────────────────────────────────┐
  │ 1. Lee la venta (cabecera + discriminaciones + percepciones)      │
  │ 2. CbteTipoResolver(tipo+letra) → CbteTipo de AFIP                 │
  │ 3. WSFEv1.ultimoAutorizado(ptoVta, cbteTipo) + 1 = número         │
  │ 4. WsfeComprobanteMapper.build(venta, ctx) → FeCAEReq             │
  │ 5. WSFEv1.solicitarCae(FeCAEReq) ──────────► ARCA (FECAESolicitar)│
  │ 6. Guarda CAE/vto/resultado en la venta                          │
  └───────────────────┬──────────────────────────────────────────────┘
                       │ cada llamada necesita Auth{Token,Sign,Cuit}
                       ▼
  WsaaClient.authorize('wsfe')  ──► TA (token+sign, ~12 h)
     │ reusa el TA cacheado en `afip_tickets` (DbTicketStore); si venció:
     │   TRA (LoginTicketRequest) → firma CMS (FileCmsSigner/OpenSslCmsSigner)
     │   → loginCms (WSAA) → AccessTicket
     ▼
  Certificado X.509 + clave privada (archivos PEM, rutas en .env)
```

**Carpetas:**

| Carpeta | Qué hay |
|---|---|
| `Afip/Wsaa/` | Autenticación: `WsaaClient`, `LoginTicketRequest` (TRA), `AccessTicket` (TA), `*CmsSigner` (firma), `TicketStore`/`DbTicketStore` (cache del TA en `afip_tickets`) |
| `Afip/Wsfe/` | Factura: `AfipWsfeClient` (SOAP), `WsfeComprobanteMapper` (venta → FeCAEReq), `*Resolver` (códigos), `ComprobanteCae` (respuesta), `FacturaContexto`, `WsfeCatalogoRepository` |
| `Afip/Padron/` | Consulta de CUIT (Padrón A5): `AfipPadronClient`, `PersonaPadron` |
| `Afip/Soap/` | Transporte SOAP genérico (`SoapTransport`, `ExtSoapTransport`) |
| `Services/FacturaElectronicaService.php` | **Orquestador**: el punto de entrada de la emisión |
| `config/afip.php` (raíz backend) | URLs por ambiente + lectura de las env `AFIP_*` |

---

## 2. Setup inicial (una sola vez)

### 2.1 Generar la clave y el CSR

```bash
php modux afip:cert-key                      # → AFIP_KEY_PATH (o storage/afip/private.key)
php modux afip:cert-csr "Razón Social SA"    # → request.csr (junto a la clave)
#   opcional: --org="Razón Social SA" --country=AR
```

El CUIT lo toma de `AFIP_CUIT` (.env) y lo pone en el `serialNumber` del CSR (formato `CUIT xxxxxxxxxxx`),
como exige ARCA. La clave queda con permisos `600` y **no se versiona** (`storage/afip/` está en
`.gitignore`).

### 2.2 Portal WSASS (manual, en el sitio de ARCA)

1. Entrá al **WSASS** (homologación o producción según corresponda) con Clave Fiscal.
2. Subí/pegá `request.csr` y **descargá el certificado** emitido → guardalo en `AFIP_CERT_PATH`.
3. **Autorizá el/los servicios** al certificado: `wsfe` (facturación) y, si vas a usar el padrón,
   `ws_sr_padron_a5`. **Sin este paso, el login falla con `Computador no autorizado a acceder al
   servicio`.**

### 2.3 Configurar `.env`

```dotenv
AFIP_ENV=homologacion                 # homologacion | produccion
AFIP_CUIT=23321452639                 # CUIT emisor (sin guiones)
AFIP_CERT_PATH=/var/www/html/storage/afip/certificate.crt
AFIP_KEY_PATH=/var/www/html/storage/afip/private.key
AFIP_KEY_PASSPHRASE=                  # vacío si la clave no tiene passphrase
```

(Las rutas son **dentro del contenedor**; con el bind-mount, `storage/afip/` del repo = esa ruta.)

### 2.4 Verificar la conexión

```bash
php modux afip:wsfe-dummy     # appserver/dbserver/authserver: OK  → SOAP llega a ARCA
php modux afip:wsaa wsfe      # "TA obtenido" → cert + autorización OK (lo cachea en afip_tickets)
php modux afip:padron 30XXXXXXXXX   # solo si autorizaste ws_sr_padron_a5
```

---

## 3. Modelo de datos

```
empresa ──< periodo ──< venta ──< venta_discriminaciones   (una por alícuota de IVA)
                          │     ──< venta_percepciones       (percepciones; integran el total)
                          │     ──< venta_comprobantes_asociados  (para NC/ND)
                          └─ campos fiscales: letra, punto_venta, tipo_comprobante_id,
                             tipo_documento_id, condicion_iva_id, tipo_moneda_id, cuit, fecha, total
```

- La **venta** es el comprobante. Lleva **su propio** `punto_venta` y `letra` (no hay tabla aparte
  obligatoria para emitir; `puntos_venta` es un registro auxiliar).
- El **total y el IVA** los calcula `IvaComprobanteCalculator` a partir de las **discriminaciones**
  (no se mandan a mano): cada discriminación tiene `neto_gravado` + `iva_alicuota` (ej. `21`).
- Los IDs `tipo_comprobante_id`, `tipo_documento_id`, etc. apuntan a **catálogos** (ver §5). Los
  resolvers traducen el **código** del catálogo al **código numérico de AFIP**.

---

## 4. Cómo generar una factura (paso a paso)

### 4.1 Qué necesita la venta para poder emitir

| Campo | Ejemplo (Factura B a Consumidor Final) | Por qué |
|---|---|---|
| `letra` | `B` | Junto al tipo define el CbteTipo de AFIP |
| `punto_venta` | `1` | Debe estar **habilitado en ARCA** para tu CUIT |
| `tipo_comprobante_id` | id cuyo `codigo='FA'` | `FA`+`B` → CbteTipo 6 (Factura B) |
| `tipo_documento_id` | id cuyo `cod_afip='99'` | 99 = Consumidor Final |
| `condicion_iva_id` | id cuyo `codigo='CF'` | RG 5616: condición del receptor (CF=5) |
| `tipo_moneda_id` | id cuyo `codigo_afip='PES'` | Moneda |
| `fecha` | hoy | Para Concepto 1 (productos) ARCA acepta ±~5 días |
| `discriminaciones` | `[{neto_gravado:100, iva_alicuota:21}]` | Genera el IVA y el total |
| `cuit` | (vacío si Consumidor Final) | Para Factura A/B con receptor identificado |

### 4.2 Crear la venta (API)

`POST /empresas/{empresaId}/periodos/{periodoId}/ventas` (permiso `iva.ventas`, header
`Authorization: Bearer <jwt>`):

```json
{
  "fecha": "2026-06-30",
  "letra": "B",
  "punto_venta": "1",
  "tipo_comprobante_id": 9,
  "tipo_documento_id": 12,
  "condicion_iva_id": 5,
  "tipo_moneda_id": 2,
  "cliente_nombre": "Consumidor Final",
  "concepto": 1,
  "discriminaciones": [
    { "neto_gravado": 100.00, "iva_alicuota": 21 }
  ]
}
```

> Los IDs de catálogo (9, 12, 5, 2) son **de esta base**; buscá los tuyos con las queries de §5.
> El sistema calcula `iva_importe` (21.00) y `total` (121.00) solo.

### 4.3 Emitir el CAE (API)

`POST /empresas/{empresaId}/periodos/{periodoId}/ventas/{ventaId}/cae` (permiso `iva.facturacion`).
No lleva body. Internamente corre `FacturaElectronicaService::autorizar()`:

- numera (último autorizado en ARCA + 1),
- arma el `FeCAEReq` y pide el CAE,
- **persiste** `cae`, `cae_vto`, `numero`, `afip_resultado` en la venta.

Respuesta OK:

```json
{ "numero": 2, "cae": "86260517370191", "cae_vto": "2026-07-10", "venta": { ... } }
```

Si ARCA **rechaza**, devuelve `409 Conflict` con el detalle de las observaciones/errores (y guarda
`afip_resultado='R'` + `afip_obs`).

### 4.4 Atajo para probar por consola (sin HTTP)

Útil para debug. Corré dentro del contenedor un script que invoca el servicio:

```php
<?php
define('BASE_PATH', __DIR__);
$app = require __DIR__ . '/bootstrap/app.php';
$svc = $app->get(\App\Modules\Iva\Services\FacturaElectronicaService::class);
$r = $svc->autorizar(empresaId: 8, periodoId: 5, ventaId: 7, tenantId: '<uuid-del-tenant>');
echo "CAE {$r['cae']}  nro {$r['numero']}  vto {$r['cae_vto']}\n";
```

```bash
docker compose exec modux-backend php ese_script.php
```

(El `tenantId` es el `empresas.tenant_id` de la empresa.)

---

## 5. Catálogos y códigos

Los resolvers traducen el **código del catálogo** (no el id local) al **código de AFIP**:

### Tipo de comprobante — `CbteTipoResolver::resolve(tipo_codigo, letra)`
`tipos_comprobante.codigo` + `ventas.letra`:

| codigo | A | B | C | E | M |
|---|---|---|---|---|---|
| `FA` (Factura) | 1 | 6 | 11 | 19 | 51 |
| `ND` (Nota Débito) | 2 | 7 | 12 | 20 | 52 |
| `NC` (Nota Crédito) | 3 | 8 | 13 | 21 | 53 |
| `RF`/`RE` (Recibo) | 4 | 9 | 15 | – | 54 |

### Alícuota de IVA — `AlicuotaIvaResolver::id()`
`venta_discriminaciones.iva_alicuota`: `0`→3, `10.5`→4, **`21`→5**, `27`→6, `5`→8, `2.5`→9.

### Condición IVA del receptor (RG 5616) — `CondicionReceptorResolver::id()`
`condiciones_iva.codigo`: `RI`→1, `EX`→4, **`CF`→5**, `MO`→6, `CE`→9.

### Tipo de documento — `tipos_documento.cod_afip`
`80`=CUIT, `86`=CUIL, `96`=DNI, **`99`=Consumidor Final**.

### Encontrar los IDs locales en tu base

```sql
SELECT id, codigo FROM tipos_comprobante WHERE codigo='FA';
SELECT id, cod_afip FROM tipos_documento WHERE cod_afip='99';
SELECT id, codigo FROM condiciones_iva WHERE codigo='CF';
SELECT id, codigo_afip FROM tipos_moneda WHERE codigo_afip='PES';
```

---

## 6. Las clases clave (qué tocar)

| Clase | Responsabilidad |
|---|---|
| `Services/FacturaElectronicaService` | **Orquesta** la emisión. Empezá a leer acá. |
| `Wsfe/WsfeComprobanteMapper` | Convierte la venta en el `FeCAEReq` (importes, `Iva→AlicIva`, `Tributos`, `CbtesAsoc`, `CondicionIVAReceptorId`). Acá se agregan/ajustan campos del request. |
| `Wsfe/CbteTipoResolver` · `AlicuotaIvaResolver` · `CondicionReceptorResolver` · `TributoResolver` | Tablas código→AFIP. Agregá entradas acá para soportar nuevos casos. |
| `Wsfe/AfipWsfeClient` | Llamadas SOAP a WSFEv1 (`dummy`, `ultimoAutorizado`, `solicitarCae`). Inyecta `Auth{Token,Sign,Cuit}`. |
| `Wsfe/ComprobanteCae` | Parsea la respuesta de AFIP (`aprobado()`, `cae`, `caeVto`, `observaciones`, `errores`). |
| `Wsfe/WsfeCatalogoRepository` | `codigosDeVenta()`: trae los códigos de catálogo de una venta. |
| `Wsaa/WsaaClient` | Obtiene/cachea el TA. No suele tocarse. |
| `Wsaa/FileCmsSigner` → `OpenSslCmsSigner` | Firma el TRA (CMS/PKCS#7) con el cert. |
| `Wsaa/DbTicketStore` | Cache del TA en `afip_tickets` (1 por servicio/CUIT). |
| `config/afip.php` | URLs por ambiente y env vars. |

---

## 7. Cómo extender (recetas)

- **Nuevo tipo de comprobante**: agregá la fila al catálogo `tipos_comprobante` con el `codigo` que
  ya entiende `CbteTipoResolver` (ej. `TF`, `FE`), o sumá la entrada al resolver si es un código nuevo.
- **Nota de Crédito / Débito**: la venta debe tener `letra` (A/B/C) con `tipo_comprobante_id` de
  código `NC`/`ND`, **y** `comprobantes_asociados[]` (tipo+letra+punto_venta+numero [+cuit/fecha]).
  El mapper los emite como `CbtesAsoc → CbteAsoc[]`.
- **Varias alícuotas**: agregá varias `discriminaciones` (una por alícuota). El mapper arma el array
  `Iva→AlicIva` y suma `ImpNeto`/`ImpIVA`.
- **Percepciones / impuestos internos**: `venta_percepciones[]` (+ `imp_interno`). Integran el total
  y se emiten como `Tributos→Tributo[]` (Id por `TributoResolver`).
- **Servicios** (Concepto 2 o 3): seteá `fch_serv_desde/hasta` y `fch_vto_pago` en la venta; el
  mapper agrega esos campos.
- **Pasar a producción**: repetí §2.1–2.3 en el **WSASS de producción**, poné `AFIP_ENV=produccion`
  y **siempre** informá `condicionIvaReceptor` (RG 5616, obligatorio).

---

## 8. Troubleshooting (errores reales)

| Mensaje de ARCA / síntoma | Causa y solución |
|---|---|
| `Computador no autorizado a acceder al servicio` | Falta **autorizar el servicio** (wsfe / padron) al certificado en WSASS (§2.2). No es bug. |
| `El CEE ya posee un TA válido...` | ARCA da **1 TA por servicio cada ~12 h**. Es normal: reusá el TA cacheado (el SDK ya lo hace) o esperá a que venza. |
| `Fecha del comprobante ... fuera de rango` | `ventas.fecha` muy lejos de hoy. Para Concepto 1 usá fecha de hoy (±~5 días). |
| `El campo ImpTotal no coincide...` | El `total` no cierra con neto+IVA+tributos. Revisá las `discriminaciones`/percepciones (el calculador las usa). |
| `No se pudo leer el certificado o la clave de AFIP` | Rutas `AFIP_CERT_PATH`/`AFIP_KEY_PATH` mal o sin permisos de lectura para el proceso. |
| `AFIP_CUIT no configurado` | Falta `AFIP_CUIT` en `.env`. |
| Rechazo con Observaciones (afip_resultado='R') | Datos del comprobante inválidos (CUIT receptor, condición, importes). El mensaje trae `[código] detalle`. |

---

## 9. Comandos CLI de referencia

```bash
php modux afip:cert-key [--force]                          # genera la clave RSA 2048
php modux afip:cert-csr "<CN>" [--org=..] [--country=AR]   # genera el CSR para WSASS
php modux afip:wsaa [service]                              # autentica y cachea el TA (default wsfe)
php modux afip:wsfe-dummy                                  # health check WSFEv1 (sin auth)
php modux afip:padron <cuit>                               # consulta Padrón A5 por CUIT
```

> Validado en vivo (homologación): FEDummy OK, WSAA OK, y CAE real emitido por
> `FacturaElectronicaService` (Factura B, CAE 86260517370191).
