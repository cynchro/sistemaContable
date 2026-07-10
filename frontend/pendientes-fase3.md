# Pendientes — Frontend guiado por Visual IVA (rama `frontend-visual-iva`)

Documento de pendientes al cierre de la Fase 3 + add-on de importación. Enfoque **híbrido**
(React + CoreUI con el flujo del Visual IVA 6.10) sobre el backend real. `main` intacto,
rama **sin commitear**.

## Estado (hecho)
- **Fase 0**: contexto "empresa + período activo" (header + persistencia) + sidebar reagrupado
  IVA / Estudio / Administración (ítems dependientes se deshabilitan sin contexto).
- **Fase 1**: Cuentas (ABM) · multi-select borrar · mover comprobante · campos legacy en los
  modales (tipo documento, moneda, cotización, campo auxiliar — migración 0039).
- **Fase 2**: Reportes (ya cubiertos por LibroIvaPage) + **Utilidades** (catálogos + auditoría).
- **E2E + fixes**: sidebar (`position="fixed"`), contraste dark-mode, doble-resaltado nav, Inicio
  sobre contexto activo.
- **Fase 3**: dashboard con KPIs del Visual IVA + densidad "planilla contable" (`.ledger`).
- **Add-on**: importación de comprobantes por CSV con mapeo de columnas (ventas y compras).

Detalle en `../CLAUDE.md` (sección "Frontend guiado por Visual IVA").

---

## Pendientes

### A. Importación de comprobantes (mejoras sobre la v1)
- [ ] **Resolver campos por catálogo**: hoy `tipo_comprobante`, `condición IVA`, `provincia`,
  `tipo_operación`, `tipo_documento`, `moneda` quedan en `null` (se completan editando). Agregar un
  resolver texto/código → id (p. ej. "Factura A" → tipo_comprobante_id) para que el import los complete.
  ⚠️ **Bloqueado**: la "Mis Comprobantes" trae el código numérico de comprobante de AFIP (1/6/11…),
  que codifica tipo+letra; nuestro catálogo guarda `codigo`(FA/NC) + `cod_citi`, no ese código AFIP.
  Resolverlo bien requiere la tabla AFIP-code→(tipo,letra) + un CSV real de ARCA para validar byte a
  byte (mismo insumo que E). No hornear un mapeo parcial sin ese CSV.
- [x] **Alícuota derivada + multi-alícuota** (HECHO): si no se mapea la alícuota pero sí el importe de
  IVA, se deduce de `IVA/neto` y se encaja a la alícuota vigente más cercana (`derivarAlicuota`,
  tolerancia 1,5 pts). **Multi-alícuota por fila** (HECHO): sección "Alícuotas adicionales" — se agregan
  líneas {columna del neto, alícuota fija, columna del IVA opcional} y cada una con neto ≠ 0 suma otra
  discriminación (caso resumen bancario 21% + 10,5% con columnas separadas). El preview suma el neto y
  lista las alícuotas ("21 / 10.5").
- [x] **Guardar el mapeo por perfil** (HECHO): perfiles de mapeo reutilizables guardados en el
  navegador (`localStorage`, key `iva_import_perfiles`), indexados por NOMBRE de encabezado para servir
  a archivos del mismo origen aunque cambie el orden de columnas. Barra "Perfil de mapeo": seleccionar y
  aplicar, "Guardar mapeo actual…" (nombre por prompt), "Borrar perfil". Guarda mapping + percepciones +
  alícuotas adicionales. (v1 per-browser; una versión compartida por tenant estilo `iva_export_formatos`
  quedaría como mejora futura si se pide.)
- [x] **Validación previa en el preview** (HECHO): `validarComprobante` marca por fila (badge + color)
  falta de fecha, sin importes (neto/no gravado/exento), fecha fuera del período y CUIT ≠ 11 díg. Resumen
  válidas/aviso/error + checkbox "omitir filas con error". Errores bloquean esa fila; los avisos importan.
- [x] **Aviso de período cerrado** (HECHO): si el período activo está cerrado, un alert lo avisa y
  deshabilita el botón de importar (antes cada fila fallaba con el conflicto = N errores iguales).
- [ ] **Detección de duplicados**: el backend ya valida no-duplicado por fila; falta mostrarlo de forma
  agrupada / permitir "omitir duplicados".
- [ ] **Archivos grandes**: hoy se manda todo en un request. Para miles de filas, chunking + barra de
  progreso.
- [ ] **Importar Excel (.xlsx)**: hoy solo CSV. Requiere parseo de xlsx (dependencia o convertir a CSV).
- [ ] **Errores amigables**: el backend devuelve el error crudo (p. ej. `SQLSTATE[...]`); mapearlos a
  mensajes claros ("El número supera 8 caracteres", etc.).
- [ ] **Tests**: agregar PHPUnit para `…/ventas/import` y `…/compras/import` (éxito parcial, filtro de
  nulls, reporte por índice).

### B. Add-ons ARCA del Visual IVA aún no construidos
- [ ] **Constatar en línea**: validar un comprobante contra el WS de ARCA. Requiere el web service +
  certificado (misma dependencia que el resto de AFIP en vivo).
- [ ] **Exportar a Contable**: depende del **módulo Contable**, que todavía no existe en el ecosistema.
- [ ] **RG3685 / ATP**: RG3685 quedó reemplazado por el **Libro IVA Digital** (ya implementado); ATP
  no tiene spec. Confirmar con el contador si siguen siendo necesarios.

### C. Coherencia híbrida / UX
- [ ] **Contexto activo en Sueldos / Gestión / AFIP**: adoptaron parcialmente el header; algunos siguen
  con su propio selector de empresa. Unificar para que todo lea el contexto activo.
- [ ] **Selector de contexto en mobile**: el `ActiveSelector` del header está oculto en `< md`
  (`d-none d-md-flex`); en mobile no hay forma de elegir empresa/período. Definir alternativa (drawer,
  o moverlo al sidebar).
- [ ] **Deep-link con período distinto al activo**: si la URL apunta a un período ≠ al activo, el
  sidebar no resalta el ítem (los links se arman con el período activo). Menor.
- [ ] **Revisión visual pendiente**: Actividades, Sueldos, Gestión y Admin no se revisaron con capturas
  en esta pasada (Fase 3 se enfocó en el núcleo IVA + Inicio).

### D. Calidad / infra
- [ ] **Commitear la rama** `frontend-visual-iva` (checkpoint en git) cuando se decida.
- [ ] **Optimización del import**: `service.create` re-valida "período editable" por fila (N queries);
  pre-chequear una vez si el volumen crece.
- [ ] **Densidad `.ledger`**: aplicada a ventas/compras/libro IVA/import; falta (opcional) extenderla a
  otras tablas (reportes del subdiario, catálogos) si se quiere consistencia total.

### E. Bloqueado por insumo externo / decisión de dominio
- [ ] **Parser fijo de "Mis Comprobantes"** (sin mapeo, 0 clicks): requiere un **CSV de ejemplo real** de
  ARCA para validar el layout byte a byte (como se hizo con el Libro IVA Digital). Hoy se cubre con el
  import genérico + mapeo.
- [ ] **AFIP en vivo** (constatación, factura electrónica real): requiere **certificado de homologación**
  de ARCA (pendiente transversal del proyecto).
