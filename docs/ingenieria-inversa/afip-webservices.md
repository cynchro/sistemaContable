# AFIP/ARCA Web Services — análisis y comparación con lo que tenemos

> Fuente: https://www.afip.gob.ar/ws/ (documentación SOAP de ARCA, ex AFIP).
> Objetivo: medir la brecha entre nuestro módulo `Iva` actual y lo que exigen los
> web services para **emitir factura electrónica** (CAE) y **consultar padrón**.
> Estado: análisis. NADA de esto está implementado todavía; es el plan para el hito
> "AFIP / factura electrónica" diferido en `app/Modules/Iva/pendientes.md`.

## 1. Catálogo de web services relevantes

| WS | Sigla | Para qué sirve | Comprobantes | Reg. |
|----|-------|----------------|--------------|------|
| Autenticación | **WSAA** / WSASS | Obtiene el Ticket de Acceso (TA) firmando con certificado X.509. **Obligatorio antes de cualquier WS de negocio.** | — | — |
| Factura Electrónica V1 | **WSFEv1** | FE **sin** detalle de ítem, CAE y CAEA | A, B, C, M | RG 4291 (manual v4.3) |
| FE con ítems | **WSMTXCA** | FE **con** detalle de ítem | A, B | RG 2904 |
| FE Exportación V1 | **WSFEXv1** | Comprobantes de exportación | E | RG 2758 |
| Comprobantes T | **WSCT** | Turismo (alojamiento a extranjeros) | T | — |
| Constancia de inscripción | **ws_sr_constancia_inscripcion** | Trae datos fiscales por CUIT (razón social, impuestos, domicilio) | — | — |
| Padrón alcance 4/5/10/13 | **ws_sr_padron_a{4,5,10,13}** | Consulta CUIT / condición frente a IVA / domicilio / actividades | — | — |

**Para nuestro caso (RI con ventas/compras): el camino es WSAA → WSFEv1.** WSMTXCA solo
si se necesita detalle de ítems en la propia factura (hoy no lo modelamos así).

## 2. Estructura de `FECAESolicitar` (WSFEv1) vs nuestra tabla `ventas`

`FECAESolicitar` es el método que pide la autorización (CAE). Campos del request y
su correspondencia con lo que ya tenemos:

| Campo AFIP | ¿Lo tenemos? | Nota |
|------------|--------------|------|
| `CbteTipo` | ⚠️ parcial | Tenemos `tipo_comprobante_id`, pero `tipos_comprobante.cod_citi` **NO es** el código `CbteTipo` de WSFEv1 (CITI ≠ FE). Falta una columna `cod_afip_cbte` (1=Fac A, 2=ND A, 3=NC A, 6=Fac B, 7=ND B, 8=NC B, 11=Fac C, 12=ND C, 13=NC C, 51=Fac M…). |
| `PtoVta` | ❌ | No hay entidad "punto de venta" autorizado. Hoy `punto_venta` es texto libre. |
| `Concepto` (1/2/3) | ✅ | `ventas.concepto` smallint. |
| `DocTipo` | ✅ | `tipo_documento_id` → `tipos_documento.cod_afip`. |
| `DocNro` | ✅ | `cuit` (o doc). |
| `CbteDesde`/`CbteHasta` | ⚠️ | `numero`/`numero_fin` existen, pero el número lo asigna AFIP (ver §3). |
| `CbteFch` | ✅ | `fecha`. |
| `ImpTotal` `ImpNeto` `ImpIVA` `ImpTributos` `ImpOpEx` `ImpTotConc` | ✅/⚠️ | Tenemos `total`, neto/IVA por discriminación, `exento`, `neto_no_grav`. `ImpTributos` (percepciones) está flojo: modelamos `retenciones`, no el array `Tributos` de AFIP. |
| `MonId`/`MonCotiz` | ✅ | `tipo_moneda_id` → `tipos_moneda.codigo_afip` + `tipo_cambio`. |
| `Iva[]` (`Id`,`BaseImp`,`Importe`) | ⚠️ | Tenemos `venta_discriminaciones` (neto+alícuota+importe). Falta el **`Id` de alícuota AFIP** (3=0%, 4=10,5%, 5=21%, 6=27%, 8=5%, 9=2,5%); hoy guardamos `iva_alicuota` como decimal, no el Id. |
| `CondicionIVAReceptorId` | ❌ | **Obligatorio (RG 5616, vigente).** Es la condición frente al IVA del receptor con la codificación nueva. Mapea a `condiciones_iva` pero con otro código → falta columna. |
| `FchServDesde/Hasta`, `FchVtoPago` | ❌ | Requeridos cuando `Concepto` ∈ {2,3} (servicios). No los tenemos. |
| `CbtesAsoc[]` | ❌ | Comprobantes asociados (obligatorio en NC/ND). No modelado. |

### Respuesta (lo que hay que persistir y hoy NO guardamos)
- `CAE` y `CAEFchVto` (vencimiento del CAE) — hoy solo existe el `cai`/`fecha_cai` **legacy** (otro régimen).
- `Resultado` (**A**probado / **R**echazado / **O**bservado).
- `Observaciones[]` y `Errors[]` (códigos/mensajes de AFIP).

## 3. Numeración y puntos de venta
WSFEv1 **no acepta** numeración libre: se consulta `FECompUltimoAutorizado(PtoVta, CbteTipo)`
y se emite con `último + 1`, correlativo por punto de venta y tipo. Implica:
- Tabla `puntos_venta` por empresa (número, tipo de emisión, habilitado).
- Control de correlatividad/concurrencia al solicitar CAE.
- `FEParamGetPtosVenta` para validar contra los puntos de venta habilitados en AFIP.

## 4. Lo que falta construir (infra, no existe hoy)
1. **WSAA**: certificado X.509 por CUIT (.crt + .key), armado del TRA, **firma CMS**
   (openssl/pkcs7), pedido del TA y **cache del TA** (válido ~12 h). El secreto de la
   clave privada del certificado encaja con `App\Support\Crypto` (cifrado en reposo).
2. **Cliente SOAP** (PHP `SoapClient` o equivalente) con endpoints de **homologación**
   y **producción** configurables por entorno.
3. **Servicio `FacturaElectronicaService`** (módulo Iva): orquesta TA → numeración →
   FECAESolicitar → persistir CAE/estado → manejar Observaciones/Errores. Patrón del
   proyecto: Repository=SQL, Service=orquesta, **Calculator=matemática pura** (el cálculo
   de importes ya lo hace `IvaComprobanteCalculator`, se reutiliza).
4. **Sincronización de catálogos AFIP** vía `FEParamGet*` (TiposCbte, TiposIva, TiposDoc,
   TiposConcepto, TiposMonedas, Cotizacion) para validar/mapear contra nuestros catálogos.

## 5. Cambios de modelo (migración futura, p. ej. 0028)
- `tipos_comprobante`: agregar `cod_afip_cbte` (CbteTipo de WSFEv1; distinto de `cod_citi`).
- Catálogo `tipos_iva_afip` (o columna `cod_afip_alic` en la discriminación): Id de alícuota AFIP.
- `condiciones_iva`: agregar `cod_condicion_receptor` (CondicionIVAReceptorId, RG 5616).
- `ventas`: `cae`, `cae_vto`, `estado_afip` (A/R/O), `obs_afip` (JSON), `pto_venta_id`,
  y para servicios `fch_serv_desde/hasta`, `fch_vto_pago`.
- Nuevas tablas: `puntos_venta`, `comprobantes_asociados`, `afip_credenciales`
  (certificado/clave cifrada + alias + entorno homologación/producción).

## 6. Padrón — mejora de alto valor (no bloqueante)
`ws_sr_constancia_inscripcion` / `ws_sr_padron_a5`/`a13` permiten, al cargar un
`iva_cliente`/`iva_proveedor` (o una `empresa`), **autocompletar desde el CUIT**: razón
social, condición frente al IVA, domicilio y actividades. Hoy esos datos se cargan a mano.
Reutiliza la misma autenticación WSAA. Buen primer paso "barato" para validar la
integración SOAP antes de meterse con la emisión de CAE.

## 7. Resumen de la brecha
- **Lo que ya está bien encaminado:** la base imponible, alícuotas, neto/IVA/total,
  moneda, tipo de documento, concepto y los **códigos AFIP preservados** en los catálogos.
  El motor de cálculo (`IvaComprobanteCalculator`) produce los importes que pide AFIP.
- **Lo que falta y es estructural:** WSAA (auth con certificado), cliente SOAP, numeración
  correlativa por punto de venta, `CondicionIVAReceptorId`, el `CbteTipo`/`Id` de alícuota
  con la codificación **de factura electrónica** (no la de CITI), y persistir CAE/estado.
- **Orden sugerido:** (1) WSAA + un `FEParamGet*` de prueba en homologación → (2) consulta
  de padrón (autocompletar CUIT) → (3) numeración + FECAESolicitar de comprobantes simples
  (Factura C/B sin asociados) → (4) NC/ND con `CbtesAsoc` y servicios → (5) producción.

## Fuentes
- Portal WS: https://www.afip.gob.ar/ws/
- FE (lista de WS): https://www.afip.gob.ar/ws/documentacion/ws-factura-electronica.asp
- Manual WSFEv1 (desarrollador): https://www.arca.gob.ar/ws/documentacion/manuales/manual-desarrollador-ARCA-COMPG.pdf
- WSAA (manual): https://www.afip.gob.ar/ws/WSAA/WSAAmanualDev.pdf
- Constancia de inscripción: https://www.arca.gob.ar/ws/WSCI/manual_ws_sr_ws_constancia_inscripcion_v3.7.pdf
- Padrón A13: https://www.arca.gob.ar/ws/ws-padron-a13/manual-ws-sr-padron-a13-v1.3.pdf
- Catálogo de WS de negocio: https://www.afip.gob.ar/ws/documentacion/catalogo.asp
