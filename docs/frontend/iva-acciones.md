# Módulo IVA — paridad funcional con Visual IVA 6.1 (spec del frontend)

> Objetivo: construir la UI del módulo IVA cubriendo **todas las acciones** del Visual IVA
> (manual en `softContable/manuales/viva61`), con UX mejorada pero **sin perder funcionalidad**.
> Fuente de verdad de *qué hace cada cosa*: el manual. Este doc mapea cada acción del legacy →
> endpoint del backend (✓ existe / ✗ falta) → pantalla/componente del frontend.
>
> Leyenda backend: ✅ ya existe · 🟡 parcial / se arma en el front · ❌ falta endpoint.

---

## 1. Ventas (`/iva/ventas`, bajo empresa+período)

Pantalla central. Listado de comprobantes del período activo.

| Acción Visual IVA | Backend | Frontend |
|---|---|---|
| Listar (fecha, cliente, nº comprobante, total) | ✅ `GET …/ventas` (paginado) | Tabla (`CSmartTable`) con columnas del legacy |
| Filtrar por rango de fechas | ✅ `fecha_desde`/`fecha_hasta` | Inputs de fecha sobre la tabla |
| Buscar por cliente | ✅ `cliente_id` (+ se puede agregar `texto`) | Buscador |
| Buscar por nº/letra de comprobante | ✅ `letra` (nº: 🟡 agregar filtro) | Buscador |
| Ordenar por fecha / cliente | 🟡 (hoy ordena por fecha; agregar `orden`) | Selector de orden |
| **Agregar** (ficha de carga) | ✅ `POST …/ventas` | Pantalla/modal de ficha (ver §1.1) |
| **Modificar** | ✅ `PUT …/ventas/{id}` | Misma ficha precargada |
| **Eliminar → Borrar actual** | ✅ `DELETE …/ventas/{id}` | Botón en fila + confirmación |
| **Eliminar → Borrar seleccionados** | 🟡 N× `DELETE` (o endpoint batch ❌) | Checkbox multi-select + acción masiva |
| **Eliminar → Borrar todos** | 🟡 N× `DELETE` (o batch ❌) | Acción "borrar todos del período" |
| **Ver todos** (quitar filtros) | ✅ (sin params) | Botón limpiar filtros |
| **Mover** a otro período | ✅ `POST …/ventas/{id}/mover` | Selección + modal "período destino" |
| **Imprimir** (→ Reportes) | ✅ datos en `…/reportes/ventas` | Abre pantalla de Reportes |
| **Utilidades → Exportar período** | ✅ `…/exportar/ventas` (CSV/TXT) + `…/libro-iva-digital/*` + export configurable | Menú "Exportar" con formatos |
| **Utilidades → Forzar recálculo** | N/A (totales derivados on-the-fly) | No aplica (se documenta el porqué) |
| **Utilidades → Importar comprobantes** (Excel/Portal IVA) | ❌ **falta endpoint de importación** | Pendiente backend + UI de carga |
| Solicitar CAE (factura electrónica) | ✅ `POST …/ventas/{id}/cae` | Acción en fila / ficha |

### 1.1 Ficha de carga de venta
| Campo/acción | Backend | Frontend |
|---|---|---|
| Fecha de emisión | ✅ | Date (default: última usada) |
| Cliente con **búsqueda inteligente** | ✅ (lista de clientes) | Autocomplete por nombre/razón social |
| **Nuevo Cliente** (alta inline) | ✅ `POST …/clientes` | Modal rápido desde la ficha |
| Autocompletar por CUIT (padrón ARCA) | ✅ `GET /padron/{cuit}/sugerencia` | Botón "traer de ARCA" |
| Tipo doc, CUIT, ingresos brutos | ✅ | Inputs |
| Tipo de comprobante + letra | ✅ (catálogo) | Selects |
| **Discriminación**: agregar/eliminar neto gravado por alícuota | ✅ (líneas del agregado) | Sub-tabla editable (neto, alícuota, IVA) |
| Percepciones (IVA/IIBB/municipal) | ✅ (`venta_percepciones`) | Sub-tabla; total integra al comprobante |
| Comprobantes asociados (NC/ND) | ✅ (`comprobantes_asociados[]`) | Sub-sección |
| Totales (neto, IVA, percepciones, total) | ✅ (motor) | Calculados en vivo (mostrar) |

## 2. Compras (`/iva/compras`)
Simétrico a Ventas, lado proveedor. Todas las acciones de §1 con: **Buscar por proveedor**, filtro por **CUIT**, y en la ficha el **crédito fiscal computable** por línea. Backend ✅ (mismos endpoints `…/compras`).

## 3. Clientes (`/iva/clientes`, bajo empresa)
| Acción | Backend | Frontend |
|---|---|---|
| Listar / filtrar | ✅ `GET …/clientes` | Tabla + buscador |
| Buscar por CUIT / por Nombre | ✅ | Buscador |
| Agregar / Modificar / Eliminar | ✅ CRUD | Modal de ficha |
| Eliminar **global** (con aviso) / compartir entre empresas (`esglobal`) | 🟡 (hoy filtra por empresa) | Aviso; compartir = pendiente de diseño |
| **Nuevo Rubro** inline | ✅ `POST /rubros` | Modal rápido desde la ficha |
| Autocompletar por padrón (CUIT) | ✅ `GET /padron/{cuit}/sugerencia` | Botón "traer de ARCA" |
| Imprimir | ✅ (datos) | → Reportes |

## 4. Proveedores (`/iva/proveedores`)
Igual que Clientes (§3), lado proveedor. Backend ✅. Incluye **múltiples CAI** (legacy `cai2..5`) → ❌ hoy solo el principal.

## 5. Cuentas — plan de cuentas (`/iva/cuentas` o en Compartido)
| Acción | Backend | Frontend |
|---|---|---|
| Agregar / Modificar / Eliminar / Ver todos | ✅ CRUD `…/cuentas` | Tabla + modal |
| Exportar cuentas (formatos) | ❌ falta | Pendiente backend |
| Importar cuentas de otra empresa | ❌ falta | Pendiente backend |
| Importar desde Visual Conta | ❌ falta (depende módulo Contable) | Pendiente |
| Imprimir | ✅ (datos) | → Reportes |

## 6. Reportes (`/iva/reportes`)
El Visual IVA agrupa: **Informes de Compras, de Ventas, de Totales, Informe SIAP, Otros Listados, Crédito/Débito Fiscal F2002**.
| Reporte | Backend | Frontend |
|---|---|---|
| Subdiario Ventas / Compras | ✅ `…/reportes/{ventas\|compras}` | Vista + descarga CSV/TXT |
| Libro IVA detallado (por alícuota/condición) | ✅ `…/libro-iva` | Vista |
| DDJJ F2002 (débito/crédito/saldo) | ✅ `…/ddjj` | Vista |
| DDJJ IVA Simple (F.2051) | ✅ `…/iva-simple` (+ persistencia) | Vista + presentar |
| Libro IVA Digital (4 TXT ARCA) | ✅ `…/libro-iva-digital/*` | Descargas |
| **Render PDF** de los reportes | ❌ (es presentación) | Generar PDF en el front |
| **Informe SIAP** | ❌ falta | Pendiente |
| Régimen de información RG3685 (legacy) | ⚠️ reemplazado por Libro IVA Digital | — |

## 7. Catálogos / Archivos
Provincias, Rubros, Tipos de comprobante, Tipos de retención, Tipos de condición de IVA.
| Acción | Backend | Frontend |
|---|---|---|
| Ver catálogos (read-only AFIP) | ✅ `GET /catalogos/{slug}` | Selects / pantallas de consulta |
| ABM de tipos de retención propios del estudio | ✅ `/tipos-retencion` (CRUD) | ABM |
| ABM de rubros | ✅ `/rubros` | ABM |

---

## Gaps de backend que el Visual IVA tiene y nosotros no (a resolver)
1. ❌ **Importación de comprobantes** (ventas/compras) desde Excel / Portal IVA (Mis Comprobantes). El Visual IVA importa; hoy solo exportamos. **Es la pieza más relevante a sumar.**
2. ❌ **Borrado masivo** (seleccionados / todos del período) — endpoint batch (o emular con N× DELETE desde el front).
3. ❌ **Exportar / importar plan de cuentas** e **importar desde Visual Conta** (depende del módulo Contable).
4. ❌ **Informe SIAP** y **render PDF** de reportes.
5. 🟡 Filtros faltantes en el listado: por **número** de comprobante y **orden** configurable (hoy hay fecha/cliente/letra + orden por fecha).
6. 🟡 **Compartir clientes/proveedores entre empresas** (`esglobal`) y **múltiples CAI** en proveedores.

## Plan de construcción del frontend (orden sugerido)
1. **Clientes y Proveedores** (ABM) — base para cargar comprobantes (reusa el patrón de Empresas).
2. **Ventas** — listado con filtros/orden + ficha de carga completa (discriminación, percepciones, asociados, padrón, nuevo cliente inline). El núcleo.
3. **Compras** — espeja Ventas (lado proveedor + cf computable).
4. **Reportes** — subdiario, libro IVA, DDJJ F2002/IVA Simple, descargas (Libro IVA Digital, CSV/TXT).
5. **Cuentas y Catálogos** (ABM rubros / tipos de retención; consulta de catálogos).
6. **Acciones masivas** (borrar seleccionados/todos, mover) y **Exportar/Importar** (según gaps backend).

> Los gaps de backend (importación de comprobantes, borrado batch, SIAP, PDF) se abordan como
> tareas propias cuando lleguemos a la pantalla que los necesita; se registran en
> `app/Modules/Iva/pendientes.md`.
