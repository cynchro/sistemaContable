# Unificación del ecosistema — de 3 sistemas a 1 (sin redundancia)

> Objetivo: un **único sistema homogéneo** que reemplace 3 aplicaciones, **sin
> duplicar datos**. Las entidades que hoy existen repetidas en cada sistema se
> unifican una sola vez (en `Compartido`) y cada módulo las referencia.

## 1. Los tres sistemas

| Sistema | Origen | Naturaleza | Tamaño | Estado |
|---|---|---|---|---|
| **IVA** | Delphi/Firebird (softContable) | Registración: libro IVA, DDJJ | 33 tablas | ✅ Migrado (Compartido + Iva) |
| **Sueldos** | Delphi/Firebird (softContable) | Registración: liquidación de sueldos | 103 tablas | ⏳ Pendiente |
| **sistemaCuarto** | Laravel 12 ("Synergys"/Haddad) | CRM + obligaciones fiscales + tareas | 70 modelos / 76 migraciones | ⏳ Pendiente |

> **Contable NO se migra** (decisión del usuario).
> Schemas extraídos: `softContable/mysql/{iva,sueldos}_*.sql`. El schema real de
> sistemaCuarto solo se obtiene de su DB de producción (migraciones incompletas).

## 2. Mapa de entidades compartidas (clave para NO duplicar)

La regla "sin redundancia" se cumple identificando qué entidad del mundo real
aparece repetida en varios sistemas y dejándola **una sola vez**.

| Entidad real | IVA | Sueldos | sistemaCuarto | Destino unificado |
|---|---|---|---|---|
| **Contribuyente / Empresa** | `empresas` | `EMPRESAS` (razón social, cuit, provincia, ing. brutos, inicio act.) | `Persona` (cuit, tipo_contribuyente, contacto) | **`Compartido`: una sola entidad canónica** (unión de campos). Eje del sistema. |
| **Provincia** | `provincias` | `PROVINCIAS` / `PROVINCIA_CON_LOCALIDAD` | (localidades) | `Compartido.provincias` (+ localidades) |
| **Tipo de documento** | `tipos_documento` | `TIPO_DOCUMENTO` | — | `Compartido.tipos_documento` |
| **Condición IVA** | `condiciones_iva` | — | `tipo_contribuyente` | `Compartido.condiciones_iva` |
| **Cuentas contables** | `cuentas` | `CUENTAS_CONTABLES` | `Cuenta` (financiera) | `Compartido.cuentas` (revisar matices) |
| **Período** | `periodos` (fiscal/IVA) | `PERIODOS` (liquidación) | — | Períodos **tipados por dominio** (no forzar uno solo) |
| **Comprobantes compra/venta** | `ventas`/`compras` (libro completo) | — | `CompraVenta` (registro importado de AFIP) | **IVA es la fuente de verdad**; la importación de sistemaCuarto alimenta IVA |
| **Usuario / Roles** | `USUARIOS`/`PERFILES` | `USUARIOS`/`PERFILES` | `User` + spatie (5 roles) | **Auth + RBAC del framework** (ya nativo) |
| **Empleado** | — | `PERSONAL` (empleados de las empresas cliente) | `Empleado` (personal del estudio) | **Distintos ámbitos** — no unificar a la fuerza (cliente vs estudio) |
| **Estudio contable** | (instalación) | (instalación) | (la app entera = 1 estudio) | **= `tenant`** del framework |
| Infra licencia (`SISTEMA`, `TERMINALES`) | sí | sí | — | **Descartar** (no aplica) |

**Conclusión de redundancia:** el linchpin es el **Contribuyente/Empresa**. Si se
define una sola vez y IVA, Sueldos y sistemaCuarto lo referencian, se elimina la
mayor fuente de duplicación. Los catálogos (provincia, tipo doc, condición IVA) y
los usuarios/roles son las otras unificaciones, ya encaminadas en `Compartido`.

## 3. Qué aporta cada sistema (lo no solapado = funcionalidad nueva)

- **IVA** (hecho): libro IVA ventas/compras, discriminación por alícuota, retenciones,
  DDJJ F2002, subdiario, export CSV.
- **Sueldos**: liquidación de haberes (recibos, conceptos, contribuciones), legajos
  (`PERSONAL`, familiares, asistencia, horarios), obra social/jubilación, ganancias
  4ta categoría, SICOSS/Libro de Sueldos Digital (LSD), convenios (UOCRA, FAECYS).
  Cálculos complejos → fuerte uso del **motor de cálculos**.
- **sistemaCuarto**: CRM de contribuyentes (`Persona`), **calendario de obligaciones**
  (`Tributo`, `Vencimiento`/`VencimientoFiscal`), **workflow de tareas** (`Task*`),
  honorarios, requerimientos, solicitudes de documentación, facilidades ARCA, BCRA,
  chat interno, notificaciones (email/WhatsApp), ingesta por API (`*ImportController`).
  Es la **capa que envuelve al contribuyente** (front-office del estudio).

## 4. Recomendación de orden: **Sueldos primero, sistemaCuarto después**

### Por qué Sueldos antes
1. **Mismo método y homogeneidad**: es un legacy Delphi con schema ya extraído
   (igual que IVA). Reusamos la ingeniería inversa ya probada y el motor de cálculos.
   sistemaCuarto es otro paradigma (Laravel/CRM) y otra metodología.
2. **Refuerza la entidad canónica con bajo riesgo**: Sueldos comparte
   `EMPRESAS`/`PROVINCIAS`/`TIPO_DOCUMENTO` con `Compartido`. Integrarlo obliga a
   convertir `empresas` en el **Contribuyente canónico** sirviendo a un SEGUNDO
   módulo de registración → valida el diseño "sin redundancia" antes de sumar el CRM.
3. **Valor operativo autocontenido**: la liquidación de sueldos es un vertical
   completo y usable, como lo fue IVA.
4. **sistemaCuarto conviene último porque**: (a) su schema real requiere la **DB de
   producción** (migraciones incompletas), (b) es la capa CRM/workflow que se apoya
   sobre el contribuyente canónico — mejor construirla cuando ese núcleo ya esté
   sólido (IVA + Sueldos), para que su `Persona` mapee sin duplicar.

### Prerrequisito al integrar Sueldos
Evolucionar `Compartido.empresas` → **Contribuyente/Empresa canónico** (unión de
campos IVA + Sueldos; dejar lugar para los de `Persona`). Provincias y tipo_documento
ya están compartidos. Definir si los períodos se tipan por dominio.

## 5. Plan de fases (alto nivel)

1. **(Ahora)** Análisis de unificación — este documento.
2. **Compartido v2**: elevar `empresas` a Contribuyente canónico; localidades; (períodos tipados).
3. **Módulo Sueldos**: ingeniería inversa (legajos → conceptos → liquidación → recibos → LSD/SICOSS),
   con calculadoras propias sobre el motor.
4. **Módulo(s) de sistemaCuarto**: Contribuyentes (merge CRM en el canónico), Obligaciones
   (tributos/vencimientos), Tareas/Workflow, Honorarios, Ingesta/Sync. Requiere DB de prod.
5. **RECIÉN DESPUÉS**: migración de datos reales de los 3 (plan en `softContable/migracion/`),
   reconciliando y deduplicando contribuyentes.

## 6. Riesgos / notas
- **sistemaCuarto**: migraciones incompletas vs prod, git parcialmente corrupto, código muerto
  (~40 archivos `*Old`/fechados). Su schema fiel necesita dump de producción.
- **Deduplicación de contribuyentes** en la migración: el mismo CUIT puede existir en IVA,
  Sueldos y sistemaCuarto → matchear por CUIT al unificar.
- **Empleados**: `PERSONAL` (de empresas cliente, núcleo de Sueldos) ≠ `Empleado` (personal del
  estudio en sistemaCuarto). No mezclar.
- **CompraVenta** de sistemaCuarto: evitar duplicar con el libro IVA; definir IVA como único origen.
