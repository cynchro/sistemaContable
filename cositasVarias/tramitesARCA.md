# Trámites ARCA (AFIP) — estado y pendientes

Documento de referencia de las gestiones con ARCA (ex AFIP) que el sistema necesita y que
dependen de trámites externos (no de código). Actualizar cuando cambie el estado.

---

## 1. Padrón — autocompletar el alta de empresa/cliente/proveedor

### Estado actual: se usa el **padrón A13** (constancia de inscripción)

- Config: `AFIP_PADRON_ALCANCE=a13` (por defecto), servicio `ws_sr_padron_a13`
  (`config/afip.php` → `padron.a13`).
- Certificado de **homologación** asociado, CUIT `23321452639`.
- **Qué trae A13** (verificado en vivo contra ARCA): CUIT, tipo de persona, estado, denominación,
  domicilio fiscal (dirección / localidad / **provincia** / CP), **actividad principal**
  (código + descripción + período → **inicio de actividad**).
- **Qué NO trae A13**: la lista de impuestos. Por eso **no se puede autocompletar**:
  - **Condición frente al IVA** (se deriva de los impuestos 30 = RI, 32 = Exento, 20/21 = Monotributo).
  - **Actividad secundaria**.

> Comprobado: A13 devuelve `impuestos: []` para todas las CUIT, incluso Responsables Inscriptos
> seguros (p. ej. Banco Nación). Es una limitación del servicio, no del sistema.

### Decisión vigente: **condición frente al IVA = carga manual**

En el modal de empresa (y de cliente/proveedor), al "Obtener datos de AFIP" se autocompletan
nombre, dirección, localidad, **provincia**, **actividad principal** e **inicio de actividad**.
La **condición frente al IVA** y la **actividad secundaria** se eligen a mano (la condición es un
`<select>` del catálogo; ya queda seleccionable). No se infiere ningún dato fiscal que ARCA no informe.

### Cómo se resolvería el autocompletar de la condición (padrón A4/A5)

El padrón **A4** (o A5) es el padrón *completo*: devuelve `datosRegimenGeneral` con **impuestos +
actividades** (y `datosMonotributo`), de donde salen la condición IVA y la actividad secundaria.

**El sistema ya está listo para A4/A5** — no hay nada que programar:
- Endpoints A4/A5/A10 cargados en `config/afip.php` → `padron`.
- El `ServiceProvider` arma WSAA + WSDL + nombre de servicio según el alcance.
- El parser (`app/Modules/Iva/Afip/Padron/PersonaPadron.php`) ya interpreta la estructura A4/A5
  y `condicionIva()` deriva la condición (impuesto 30 → Responsable Inscripto, 32 → IVA Exento,
  20/21 o nodo `datosMonotributo` → Monotributo). El front la matchea contra el catálogo y la preselecciona.

Para activarlo, una vez habilitado en ARCA (ver abajo): **`AFIP_PADRON_ALCANCE=a4`** en el `.env`
del entorno + redeploy. Nada más.

### ⛔ Barrera: A4/A5/A10 son servicios RESTRINGIDOS de ARCA

Intentar operar A4 dio: **"La persona no se encuentra habilitada para operar el servicio"**
(ticket ARCA **46568742**). Hay **dos gates**, y no alcanza con agregar la relación:

1. **Certificado (WSAA)**: la relación del certificado con `ws_sr_padron_a4` debe estar activa
   (si no: `Computador no autorizado a acceder al servicio`).
2. **Persona (servicio)**: ARCA debe **habilitar expresamente el CUIT** para operar el padrón A4
   (de ahí el error "La persona no se encuentra habilitada…"). Es un trámite manual que ARCA
   evalúa caso por caso — los padrones A4/A5/A10 están pensados para bancos / grandes agentes, y a
   un estudio contable no siempre se lo otorgan.

**Servicios de padrón que ARCA ofrece al certificado**: Consulta Padrón **A13** (activo, liviano),
**A4** (completo, restringido), **A10** (impuestos por período, restringido). **A5 no aparece** en
la lista de este certificado.

### Si en algún momento se quiere habilitar A4

1. Responder el ticket **46568742** a **mayuda@afip.gov.ar** pidiendo habilitar el CUIT para operar
   `ws_sr_padron_a4`, justificando el uso (estudio contable que consulta datos fiscales de clientes).
2. Agregar la relación del certificado a "Servicio Consulta Padron A4" en el **Administrador de
   Relaciones** del ambiente correspondiente (homologación / producción).
3. Avisar al equipo de desarrollo → se prueba en vivo, se pone `AFIP_PADRON_ALCANCE=a4` y listo.

**Mientras tanto**: operar con la condición IVA manual (funciona hoy, sin depender del trámite).

---

## 2. Certificado de PRODUCCIÓN (factura electrónica / padrón en vivo)

- Estado: **pendiente el trámite del certificado de producción**. Hoy corre todo con el
  certificado de **homologación** (WSFEv1 validado: CAE real emitido en homologación).
- Al tener el certificado de producción: apuntar `AFIP_CERT_PATH` / `AFIP_KEY_PATH` (o los `*_PEM`)
  y `AFIP_ENV=produccion`. El circuito de emisión de CAE ya está validado punta a punta.

---

## Variables de entorno relevantes (`.env`)

| Variable | Valor actual | Nota |
|---|---|---|
| `AFIP_ENV` | `homologacion` | `produccion` al tener el certificado de producción. |
| `AFIP_PADRON_ALCANCE` | `a13` | `a4` cuando ARCA habilite el padrón completo (condición IVA + actividad secundaria). |
| `AFIP_CUIT` | 23321452639 (homolog.) | CUIT del certificado. |
| `AFIP_CERT_PATH` / `AFIP_KEY_PATH` | homologación | Rutas al certificado + clave (o `*_PEM`). |
