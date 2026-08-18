# Ingeniería inversa — Sistema IVA → módulos Modux

> Fuente legacy: **Visual IVA 6.10** (Logosoft), Delphi/VCL + Firebird 1.5 (ODS 10.1).
> Artefactos analizados (en `/data/proyectos/cynchro/sistemaContable/softContable`):
> `mysql/iva_mysql.sql` (schema, 33 tablas), `mysql/iva_referencia_psql.sql`
> (75 vistas/procedures/triggers, lógica de negocio), `analisis/reportes_iva.{md,json}`
> (64 reportes FastReport), `manuales/viva61/` (manual de usuario).
> Este documento es **análisis y diseño**, no implementación.

---

## 1. Decisiones de arquitectura (confirmadas)

| # | Decisión | Implicancia |
|---|---|---|
| 1 | **Empresa = entidad de dominio; Tenant = estudio contable** | El `tenant_id` del JWT identifica al estudio/contador. Un tenant administra N empresas. `empresas` lleva `tenant_id` y es la raíz de aislamiento (`TenantMiddleware`). Compartible por futuros módulos Contable y Sueldos. |
| 2 | **Infraestructura legacy → mecanismos nativos del framework** | `USUARIOS`/`PERFILES` → Auth+JWT y RBAC (`roles_permisos`); `LOG` → Logger PSR-3; se **descartan** `SISTEMA`, `TERMINALES`, `LOG_ACTIVACIONES_EMP_PER` (licenciamiento Delphi, ya no aplica). `CONFIG` se absorbe como config de módulo/empresa. |
| 3 | **Dos módulos: `Compartido` + `Iva`** | `Compartido` = catálogos y estructura reutilizable (empresas, períodos, cuentas, rubros, provincias, condición IVA, tipos). `Iva` = dominio específico (sujetos, comprobantes, retenciones, reportes). |
| 4 | **Totales de período derivados on-the-fly** | Sin columnas `PERIODO_TOTAL_*` persistidas ni triggers. Se calculan con queries agregadas / vistas SQL al momento de consultarlos. Imposible que queden desincronizados. |

---

## 2. Mapa de tablas legacy → diseño nuevo

Convención de nombres del framework: tablas **snake_case** en minúscula. Las columnas se
renombran sin el prefijo redundante de tabla (`CLI_NOMBRE` → `nombre`).

### Módulo `Compartido`
| Legacy | Tabla nueva | Notas |
|---|---|---|
| `EMPRESA` | `empresas` | + `tenant_id`. Se podan campos de licencia y de integración Conta legacy; los de facturación electrónica (certificado, clave privada, tokens) se difieren a la fase AFIP. |
| `PERIODOS` | `periodos` | FK `empresa_id`. Totales `*_TOTAL_*` **no se portan** (derivados). Se conserva `cerrado`. |
| `CUENTAS` | `cuentas` | FK `empresa_id`. Plan de cuentas para imputación. |
| `RUBROS` | `rubros` | Clasificación de comprobantes/sujetos. |
| `PROVINCIAS` | `provincias` | Catálogo (incluye jurisdicción IIBB). |
| `CONDICION_IVA` | `condiciones_iva` | Catálogo AFIP (RI, Monotributo, etc.). |
| `TIPO_COMPROBANTE` | `tipos_comprobante` | **Incluye `signo`** (`TC_SIGNO`): clave para totales (NC = −1). |
| `TIPO_DOCUMENTO` | `tipos_documento` | DNI/CUIT/etc. con código AFIP. |
| `TIPO_MONEDA` | `tipos_moneda` | Código AFIP de moneda. |
| `TIPO_RETENCIONES` | `tipos_retencion` | Alícuota, código AFIP, RG3685. |
| `TIPO_OP_COMPRA` | `tipos_operacion_compra` | Clasificación de operación. |
| `TIPO_OP_VTA` | `tipos_operacion_venta` | Clasificación de operación. |

### Módulo `Iva`
| Legacy | Tabla nueva | Notas |
|---|---|---|
| `CLIENTES` | `iva_clientes` | **Prefijo `iva_` para evitar colisión** con el `clientes` demo del framework (ver §9). FK `empresa_id`. |
| `PROVEEDORES` | `iva_proveedores` | FK `empresa_id`. Soporta múltiples CAI. |
| `VENTAS` | `ventas` | Cabecera de comprobante de venta. FK `periodo_id`, `tipo_comprobante_id`. |
| `VENTA_DISCRIMINACION` | `venta_discriminaciones` | Líneas por alícuota (neto gravado, IVA, IVA inc.). FK `venta_id`. |
| `RETENCIONES` | `venta_retenciones` | Retenciones sobre la discriminación de venta. FK `venta_discriminacion_id`. |
| `COMPRAS` | `compras` | Cabecera de comprobante de compra. |
| `COMPRAS_DISCRIMINACION` | `compra_discriminaciones` | Líneas por alícuota + crédito fiscal computable. |
| `RETENC_COMPRAS` | `compra_retenciones` | Retenciones sobre la discriminación de compra. |

### Diferido / fuera del primer alcance
| Legacy | Destino | Por qué se difiere |
|---|---|---|
| `VENTAS_CACHE_WS`, certificados/tokens en `EMPRESA` | Fase **AFIP / Facturación electrónica** | Integración WSFE/WSAA; gran superficie externa. |
| `EXPOTXT`, `EXPOTXT_ARCHIVOS`, `EXPOTXT_CAMPOS`, `EXPORECE`, `EXPOVCONTA` | Fase **Exportaciones** | Generadores de TXT/CITI/Conta; dependen del núcleo ya cargado. |
| `CONFIG` | Config de módulo/empresa | Pocos flags (factura T, tope venta CF). |

### Descartadas (cubiertas por el framework)
`USUARIOS`, `PERFILES`, `LOG`, `SISTEMA`, `TERMINALES`, `LOG_ACTIVACIONES_EMP_PER`.

---

## 3. Modelo de dominio (agregados)

```
Estudio (tenant)
 └── Empresa (empresas)                      raíz de aislamiento
      ├── Período (periodos)                  cerrado/abierto; rango de fechas
      │    ├── Venta (ventas) ───────────────┐
      │    │    └── VentaDiscriminacion       │ por alícuota
      │    │         └── VentaRetencion       │
      │    └── Compra (compras) ──────────────┘
      │         └── CompraDiscriminacion
      │              └── CompraRetencion
      ├── Cuenta (cuentas)                     plan de cuentas
      ├── IvaCliente / IvaProveedor            sujetos
      └── (catálogos globales: provincias, condiciones_iva, tipos_*)
```

**Dos agregados transaccionales**: `Venta` (cabecera + discriminaciones + retenciones) y
`Compra` (idem). La discriminación y las retenciones **no tienen vida propia**: se crean/
modifican/borran junto con su comprobante (cascade dentro de una transacción del Service).

---

## 4. Reglas de negocio a preservar

Origen: `iva_referencia_psql.sql` (vistas/procedures/triggers) y manual.

1. **Signo por tipo de comprobante** — todo total se pondera por `tipos_comprobante.signo`
   (`TC_SIGNO`); las notas de crédito restan. (Procedure `ACTUALIZA_TOTALES_PERIODO`,
   vistas `VIEW_*_OPERACION`.)
2. **Totales de período (derivados)**:
   - Total compras = `Σ(compras.total · signo)`
   - IVA compras   = `Σ(compra_discriminaciones.iva_importe · signo)`
   - Total ventas  = `Σ(ventas.total · signo)`
   - IVA ventas    = `Σ(venta_discriminaciones.iva_importe · signo)`
3. **IVA neto de venta** = `Σ(iva_importe) − Σ(reintegro_t)`. (Vista `VIVENTAS`.)
4. **Cascade de borrado** — borrar comprobante elimina sus discriminaciones y retenciones;
   borrar período exige que no tenga comprobantes y que no sea el activo. (Procedure
   `BORRAR_COMPROBANTES_DE_PERIODO`, manual de Períodos.)
5. **Validación de fechas** — fecha de comprobante dentro (ventas) o ≤ fin (compras) del
   período. (Manual de Períodos.)
6. **Mover comprobantes** entre períodos (reasignar `periodo_id`). (Manual de Ventas/Compras.)
7. **Número de comprobante compuesto** = `letra + punto_venta + número` (campo computado
   `VTA_COMPROBANTE`/`CMP_COMPROBANTE`). En la app: columna generada o accesor del modelo.
8. **Agregados para DDJJ/reportes** — agrupar por período + condición IVA + alícuota
   (+ actividad / concepto producto-servicio). (Vistas `VIEW_VENTAS_ACTIV_OPER`,
   `VIEW_VENTAS_PRODUCTO_SERVICIO`, `VIEW_COMPRAS_OPERACION`.)

Estas reglas viven en la **capa Service** (no en triggers), dentro de transacciones
(`DB::withTransaction`), respetando la filosofía "sin magia" del framework.

---

## 5. Estructura de módulos propuesta (convención Modux)

```
app/Modules/
├── Compartido/
│   ├── Controllers/   EmpresaController, PeriodoController, CuentaController,
│   │                  RubroController, CatalogoController (provincias, condiciones, tipos…)
│   ├── Repositories/   uno por entidad
│   ├── Services/       EmpresaService, PeriodoService (incl. cerrar/abrir, totales), …
│   ├── Requests/        FormRequest por operación de escritura
│   ├── ServiceProvider.php
│   └── routes.php
└── Iva/
    ├── Controllers/   IvaClienteController, IvaProveedorController,
    │                  VentaController, CompraController, ReporteIvaController
    ├── Repositories/   IvaClienteRepository, VentaRepository (con discriminación+retenciones), …
    ├── Services/       VentaService, CompraService (transaccionales),
    │                  LibroIvaService (agregados/DDJJ)
    ├── Requests/
    ├── ServiceProvider.php
    └── routes.php
```

Cada capa respeta SRP: **Repository** = solo SQL/persistencia; **Service** = reglas de
negocio + transacciones; **Controller** = HTTP in/out; **FormRequest** = validación.

---

## 6. Endpoints propuestos (borrador)

Todos bajo `AuthMiddleware` + `TenantMiddleware`; escrituras con `PermissionMiddleware`.

```
# Compartido
GET/POST/PUT/DELETE  /empresas[/{id}]
GET/POST/PUT/DELETE  /empresas/{id}/periodos[/{pid}]
POST                 /periodos/{id}/cerrar     /periodos/{id}/abrir
GET                  /periodos/{id}/totales            # derivados
GET/POST/PUT/DELETE  /empresas/{id}/cuentas[/{cid}]
GET                  /catalogos/{provincias|condiciones-iva|tipos-comprobante|...}
CRUD                 /rubros, /tipos-retencion, ...

# Iva
GET/POST/PUT/DELETE  /empresas/{id}/clientes[/{cid}]
GET/POST/PUT/DELETE  /empresas/{id}/proveedores[/{pid}]
GET/POST/PUT/DELETE  /periodos/{id}/ventas[/{vid}]     # crea cabecera+discriminación+retenciones
GET/POST/PUT/DELETE  /periodos/{id}/compras[/{cid}]
POST                 /ventas/{id}/mover  body:{periodo_id}
GET                  /periodos/{id}/libro-iva/{ventas|compras}
GET                  /periodos/{id}/ddjj/...           # agregados
```

---

## 7. RBAC (mapeo de `PERFILES` legacy → permisos)

Los flags `PERFIL_ABM_*` del legacy se mapean a permisos del framework (`roles_permisos`):

```
empresas.{ver,crear,editar,eliminar}      clientes.{...}     proveedores.{...}
periodos.{...}  periodos.cerrar           ventas.{...}       compras.{...}
cuentas.{...}   rubros.{...}              reportes.ver       reportes.editar
config.editar
```
Más permisos generales `agregar/modificar/eliminar` del legacy → se expresan por-recurso.

---

## 8. Alcance por fases

- **Fase 1 — Núcleo (este hito):** módulo `Compartido` (empresas, períodos, catálogos,
  cuentas, rubros) + módulo `Iva` (clientes, proveedores, ventas, compras, retenciones)
  con totales derivados. Migraciones + Repos/Services/Controllers/Requests + tests.
- **Fase 2 — Reportes / Libro IVA / DDJJ:** agregados de `LibroIvaService` (basados en las
  vistas legacy) y endpoints de reportes (los 64 `.fr3` ya mapeados en `analisis/`).
- **Fase 3 — Exportaciones:** TXT configurables, CITI/RG3685, export a Contable.
- **Fase 4 — AFIP / Facturación electrónica:** WSAA/WSFE, `ventas_cache_ws`, certificados.

---

## 9. Riesgos y notas

- **Colisión `clientes`**: el framework trae un módulo demo `Cliente` + tabla `clientes` de
  ejemplo. Se quitará el demo y el IVA usará `iva_clientes` (y `iva_proveedores`) para no
  pisar nada. Confirmar si se elimina también el resto de demos (`Usuario` demo, etc.).
- **Charset**: la base IVA legacy declara WIN1251 por error; los datos reales son cp1252.
  Ya contemplado en la migración de datos (CLAUDE.md de softContable). Destino: utf8mb4.
- **FKs/tipos**: el conversor marcó columnas computadas con `[REVISAR]`; las columnas
  `*_CTA_*` y de cuenta son enteros sueltos sin FK estricta en el legacy — evaluar FK a
  `cuentas` o dejar nullable sin FK como el original.
- **Decimales**: importes `DECIMAL(18,2)`, alícuotas `DECIMAL(7,3)`, tipo de cambio
  `DECIMAL(18,4)` — preservar precisión exacta (no float) para reconciliar con el legacy.
- **Datos demo**: hay `iva_data.sql` (1140 filas) para sembrar y validar contra el sistema viejo.

---

## 10. Próximos pasos

1. Confirmar nombres de tablas/columnas finales y el podado de campos de `empresas`.
2. Generar el módulo `Compartido` (`php modux make:module Compartido`) y sus migraciones.
3. Generar el módulo `Iva` y sus migraciones.
4. Implementar Repos → Services (con reglas §4) → Controllers → Requests.
5. Tests unitarios (Services con repos mockeados) y de feature (HTTP + DB).
6. Sembrar catálogos y datos demo; validar totales contra reportes del legacy.
