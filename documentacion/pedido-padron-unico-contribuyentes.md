# Pedido del contador — Padrón Único de Contribuyentes

**Fecha:** 22/07/2026 · **Canal:** WhatsApp · **De:** Juan Haddad (contador)

> **✅ Implementado (23/07/2026)**: ver sección 7 al final. Modelo elegido: "Padrón +
> activación liviana" (`iva_sujetos` + `iva_sujeto_empresas`). 584 tests verdes,
> PHPStan/PHPCS/tsc/oxlint limpios.

## 1. Pedido textual

> [10:17] pensa en el padron unico de contribuyentes quiero que el sistema de iva nuevo se maneje asi
> [10:20] queiro que todo sea una sola cosa
> [10:21] no quiero 300.000 lineas de proveedores como tenemos ahora y algunos tienen doble imputacion de cuenta

## 2. Contexto: ya existe un análisis previo

En esta misma carpeta hay un análisis ya hecho sobre este mismo problema, a partir de la base real
de proveedores de Visual IVA:

- **`Informe_Definitivo_Padron_Proveedores.pdf`** (21/07/2026): depuró 385.317 filas → eliminó 8.496
  duplicados exactos → definió el modelo **Padrón Único de Proveedores** (6.481 filas, 1 por CUIT) +
  **Configuración por Contribuyente** (376.819 filas, EMPRESA_ID + CUIT). Fija 6 reglas duras
  antiduplicación y diseña un KPI de verificación contra el padrón APOC (facturas apócrifas) de AFIP.
- **`Padron_Unico_Proveedores.xlsx`**: el maestro depurado (1 fila por CUIT).
- **`Relacion_Contribuyente_Proveedor.xlsx`**: la tabla de relación (qué contribuyente usa qué
  proveedor, con qué rubro/cuenta).

El mensaje de Juan del 22/07 **confirma y generaliza** ese modelo: no lo limita a proveedores, habla
de "padrón único de **contribuyentes**" y de que "todo sea una sola cosa" — es decir, la misma idea
aplicada también a clientes, no solo a proveedores.

## 3. Por qué esto también nos toca a nosotros, no solo al legacy

El informe describe el problema de Visual IVA (cada contribuyente crea su propia copia completa del
proveedor → duplicación masiva). **Nuestro sistema nuevo hoy tiene el mismo diseño**, no es solo un
problema de los datos históricos a migrar:

`backend/migrations/0013_create_iva_sujetos_tables.php` define `iva_clientes` e `iva_proveedores`
con `empresa_id` + todos los datos del sujeto (`nombre`, `cuit`, `domicilio`, `localidad`, `cuenta_id`,
`rubro_id`, `condicion_iva_id`...) en la misma fila. Si el mismo CUIT le compra a los 351
contribuyentes de un estudio, hoy se crean 351 filas independientes — exactamente el patrón que generó
las 385.317 filas del legacy. La mitigación que ya existe (`esglobal`, ver `CLAUDE.md`) es un booleano
que comparte **una sola fila completa** entre empresas del tenant, pero:

- No separa identidad (nombre/domicilio/CUIT) de configuración (cuenta/rubro por contribuyente) — si
  dos empresas necesitan distinta cuenta contable para el mismo proveedor global, no se puede: es la
  misma fila para todas.
- Es opt-in por sujeto (quien carga decide si es global), no una regla dura de "CUIT único en el
  sistema" — por eso pueden seguir naciendo copias divergentes del mismo CUIT con distinta cuenta
  (la "doble imputación de cuenta" que menciona Juan).

## 4. Modelo objetivo (extiende el informe a "contribuyentes")

- **Padrón Único de Contribuyentes** (maestro, por tenant/estudio): una fila por CUIT — nombre,
  domicilio, localidad, condición IVA. Sirve tanto para clientes como para proveedores (un mismo CUIT
  puede ser cliente de una empresa y proveedor de otra, o ambas cosas).
- **Configuración por Contribuyente** (relación): `empresa_id` + `contribuyente_id` (o CUIT como FK
  directa, como propone el informe) + `cuenta_id` + `rubro_id` + `rol` (cliente/proveedor) + activo.
  Acá y solo acá vive lo que cambia según quién opera con ese contribuyente.
- **Reglas duras** (sección 2 del informe, aplicables tal cual a nuestro sistema): CUIT como índice
  único NOT NULL; validación de dígito verificador antes de guardar; alta obligatoria por CUIT (buscar
  primero contra el padrón, autocompletar desde AFIP si no existe — el endpoint `GET
  /padron/{cuit}/sugerencia` ya existe y ya lo usa el alta de cliente/proveedor); importaciones
  (CSV, "Mis Comprobantes") deben hacer upsert por CUIT, no insert ciego; auditoría periódica de
  duplicados (ya existe `iva_audit_log`, se podría sumar un chequeo de CUIT repetido).

## 5. Decisiones que faltan definir antes de tocar código

1. **Alcance clientes+proveedores**: ¿un solo padrón de contribuyentes con `rol` (cliente/proveedor/
   ambos), o dos padrones separados que comparten la misma tabla maestra? (Recomendado: una tabla
   maestra + `rol` en la relación, para no duplicar la identidad de un CUIT que es cliente y proveedor
   a la vez.)
2. **Scope del padrón**: por tenant (estudio), no global entre estudios — coherente con el resto del
   sistema multi-tenant y con el propio informe (351 EMPRESA_ID de un solo estudio).
3. **Migración de `iva_clientes`/`iva_proveedores` actuales**: pasan de "sujeto completo" a "fila de
   configuración"; requiere migración de esquema + de datos existentes (deduplicar por CUIT dentro de
   cada tenant).
4. **Migración de datos históricos reales**: usar `Padron_Unico_Proveedores.xlsx` +
   `Relacion_Contribuyente_Proveedor.xlsx` ya depurados como fuente (ver plan general en
   `softContable/migracion/`).
5. **KPI de verificación APOC** (WSAPOC): queda fuera del alcance inmediato — depende de que se defina
   quién gestiona el certificado/clave fiscal para ese webservice (mismo bloqueo que ya tenemos
   documentado para AFIP producción).

## 6. Próximos pasos sugeridos

1. Confirmar con Juan el punto 5.1 (¿un padrón para clientes y proveedores juntos, o el `rol` alcanza?).
2. Diseñar la migración de esquema (tabla maestra `contribuyentes` + relación por empresa) sin romper
   los endpoints/frontend actuales de IVA clientes/proveedores.
3. Implementar las reglas duras de la sección 4 (CUIT único, dígito verificador, alta por CUIT,
   upsert en importador).
4. Recién después, migrar el volumen histórico con los Excel ya depurados.

## 7. Lo implementado (23/07/2026)

Un solo padrón para clientes y proveedores (decisión: "Padrón + activación liviana", opción
recomendada del punto 5.1 — no dos padrones separados). Reemplaza `iva_clientes`/`iva_proveedores`
por completo:

- **`iva_sujetos`** (migración `0048`): padrón único por tenant, CUIT `UNIQUE(tenant_id, cuit)`,
  NOT NULL — cumple la regla dura del informe (§2.1) sin depender de una revisión manual.
- **`iva_sujeto_empresas`**: activación por empresa (`empresa_id + sujeto_id + rol` cliente/
  proveedor). Reemplaza lo que hacía `esglobal` (que ya se quitó del todo, back y front): ahora
  **todo** sujeto del padrón es compartido por diseño, no opt-in.
- **`App\Support\Cuit`**: normaliza y valida el dígito verificador AFIP (regla §2.2 del informe).
  Rechaza el alta con un CUIT inválido (422), y rechaza que dos sujetos del mismo tenant compartan
  CUIT (protegido además por el `UNIQUE` de la tabla).
- **Alta por CUIT = upsert** (regla §2.3): dar de alta un CUIT que ya existe en el padrón del
  tenant reutiliza esa fila (actualiza los datos de identidad) y solo activa cliente/proveedor
  para la empresa que lo está cargando — no crea una copia. Facturar con un sujeto ya activa el
  vínculo automáticamente (sin alta manual previa).
- **Hallazgo que simplificó el modelo**: `cuenta_id`/`rubro_id` de las tablas viejas ya estaban
  muertos (nada los leía — el rediseño "R1 — Mayorización por línea", migración `0043`, movió esa
  imputación a nivel de línea de comprobante). Se eliminaron del todo en vez de migrarse: menos
  campos, cero ambigüedad de "qué cuenta es la real" — la causa concreta de la "doble imputación
  de cuenta" que señalaba Juan.
- Las rutas/permisos (`/empresas/{id}/clientes`, `/empresas/{id}/proveedores`, RBAC `iva.clientes`/
  `iva.proveedores`) no cambiaron — mínimo impacto en frontend y en roles ya asignados.
- Test `PadronUnicoSujetosTest`: mismo CUIT en dos empresas del mismo tenant reutiliza el sujeto;
  un proveedor cargado en una empresa se usa directo en una compra de otra empresa del mismo
  tenant (antes daba 422, ahora es el comportamiento esperado); un sujeto de otro tenant sigue
  rechazado.
- Frontend: se quitó el checkbox/badge "Global" (ya no aplica) y el CUIT pasó a ser obligatorio
  en el alta.
- **Fuera de esta pasada** (documentado, no bloqueante): migración del volumen histórico real
  (`Padron_Unico_Proveedores.xlsx`/`Relacion_Contribuyente_Proveedor.xlsx`), upsert automático por
  CUIT en el importador CSV, KPI de verificación APOC (sigue atado al certificado/clave fiscal).

No se tocó código todavía — este documento deja registrado el pedido y el plan para retomarlo.
