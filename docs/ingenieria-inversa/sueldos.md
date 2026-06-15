# Ingeniería inversa — Sistema Sueldos → módulo Modux

> Fuente: legacy **Vsueldos** (Logosoft), Delphi/VCL + Firebird. Schema extraído en
> `softContable/mysql/sueldos_{mysql,data,referencia_psql}.sql` (103 tablas).
> Mismo método que IVA (ver `iva.md`) y respetando la unificación sin redundancia
> (ver `ecosistema-unificacion.md`).

## 1. Dominio
Liquidación de sueldos (Argentina): legajos de empleados, conceptos con fórmulas,
liquidaciones por período, recibos, contribuciones patronales, ganancias 4ta
categoría, Libro de Sueldos Digital (LSD) y SICOSS, convenios (UOCRA, FAECYS, etc.).

## 2. Flujo central (de las tablas núcleo)
```
Empresa (empleador, CANÓNICA en Compartido)
 └── PERSONAL (legajo del empleado)            FK id_empresa
      ├── FAMILIARES (grupo familiar)
      ├── CCOSTOS_EMPLEADOS, HORARIOS_EMPLEADO, CONTRIBUCIONES_EMPLEADOS
      └── ...
 └── CONCEPTOS (haberes/descuentos, con FORMULA, TIPO)   FK id_empresa
 └── LIQUIDACIONES (corrida: periodo_liquidado, tipo, fechas)  FK id_empresa
      ├── PERSONAL_LIQUIDACIONES (snapshot del legajo en la corrida)
      ├── CONCEPTOS_LIQUIDACION (conceptos aplicados)
      └── RECIBOS (resultado: por empleado+concepto: cantidad, importe, tipo)
```

## 3. Mapa de tablas → grupos (103)
- **Compartido (ya existe / unificar)**: `EMPRESAS`→`empresas` (canónica), `PROVINCIAS`,
  `PROVINCIA_CON_LOCALIDAD`→localidades, `TIPO_DOCUMENTO`→`tipos_documento`.
- **Config por empresa (Sueldos)**: los ~75 campos payroll de `EMPRESAS`
  (jornada, SICOSS, recibo custom, export conta, banco, etc.) → tabla
  `sueldos_empresa_config` (1:1 con empresa). **No** bloatear la empresa canónica.
- **Legajo**: `PERSONAL`, `FAMILIARES`, `CCOSTOS`/`CCOSTOS_EMPLEADOS`,
  `HORARIOS*`/`DIAS_HORARIOS`/`DETALLE_HORARIOS`, `VACACIONES`, `INCAPACIDADES`,
  `EMBARGOS_EMPLEADOS`, `AUSENCIAS`, `ASISTENCIA`, `FICHADAS*`.
- **Conceptos y fórmulas**: `CONCEPTOS`, `CONCEPTOS_AFIP`, `VARIABLES_CONCEPTOS`,
  `CONCEPTOS_LIQUIDACION`, `CONCEPTOS_UPDATES`.
- **Liquidación**: `LIQUIDACIONES`, `PERSONAL_LIQUIDACIONES`, `RECIBOS`,
  `RECIBOS_UPDATES`, `COMENTARIOS_LIQUIDACIONES`, `NOVEDADES`.
- **Contribuciones / SS**: `CONTRIBUCIONES_PATRONALES`, `CONTRIBUCIONES_EMPLEADOS`,
  `CONTRIBUCIONES_LIQUIDACIONES`, `CONTRIBUCIONES_RECIBO(_DETALLE)`, `CONTRIBUCIONES_EXCLUIDAS`.
- **Ganancias 4ta**: `TABLA_GANANCIAS`, `DEDUCCIONES_GANANCIAS`, `TIPO_DEDUCCIONES`,
  `RETENCIONES_GANANCIAS`, `RETENCIONES_ANT_MANUALES`, `TOPES_HISTORICO`.
- **LSD / SICOSS / export**: `LSD_CABECERA`, `LSD_CONCEPTOS`, `LSD_DETALLE`,
  `LSD_EVENTUALES`, `CONF_CSV`, `CODIGOS_SPEP_SGO`, `CARGOS_SPEP_SGO`.
- **Catálogos**: `CATEGORIAS(_DOS/_TRES)`, `OBRA_SOCIAL`, `REGIMENES_JUBILATORIOS`,
  `MODALIDAD_CONTRATACION`, `SITUACION_REVISTA`, `CONDICION_LABORAL`, `ESTADO_CIVIL`,
  `NACIONALIDADES`, `PARENTESCOS`, `ACTIVIDADES(_LABORALES)`, `DEPARTAMENTOS`,
  `LUGAR_DE_PAGO`, `MODALIDAD…`, convenios `UOCRA(_CONVENIOS)`, `FAECYS_CATEGORIAS`,
  `COMERCIO_*`, `NUMEROS_PATRONALES`, `DIAS_FESTIVOS`, `REGIMENES…`.
- **Infra (descartar / nativo del framework)**: `USUARIOS`, `PERFILES`, `SISTEMA`,
  `TERMINALES`, `BITACORA_*`, `CHAT_LOG`, `EVENTOS`, `EMPRESA_USUARIOS`.

## 4. Decisiones de arquitectura
1. **Empresa canónica liviana + `sueldos_empresa_config`** (1:1). La identidad
   (razón social, CUIT, domicilio, provincia, condición, ing. brutos, actividad,
   inicio act.) vive en `Compartido.empresas`; lo payroll-específico, en el módulo.
   Mismo patrón aplicable a IVA si luego necesita config propia.
2. **El período de liquidación NO es el `periodos` de IVA**: vive en
   `LIQUIDACIONES.periodo_liquidado` (+ fechas desde/hasta). No se comparte.
3. **Motor de fórmulas (lo más complejo)**: `CONCEPTOS.FORMULA` son expresiones que
   se evalúan por empleado en cada liquidación, sobre variables (básico, antigüedad,
   días, categorías, acumuladores). Se implementará como un **evaluador de fórmulas
   puro** (sobre el motor `Decimal`), con un contexto de variables y funciones — una
   calculadora del módulo Sueldos. Es el núcleo del riesgo; se diseña en detalle al
   llegar a la liquidación (Fase 3), reconciliando contra `iva`/`referencia_psql`.
4. **Snapshot en liquidación**: `PERSONAL_LIQUIDACIONES` y `CONCEPTOS_LIQUIDACION`
   son *copias congeladas* del legajo/conceptos al liquidar (el legacy las
   materializa). Se preserva ese diseño (una liquidación no cambia si luego se edita
   el legajo). `RECIBOS` guarda el resultado calculado por línea.
5. **SOLID/clean igual que IVA**: Repository=SQL, Calculator=cálculo puro,
   Service=orquesta+transacción+reglas, Controller=HTTP, FormRequest=validación.
6. **Multi-tenant**: todo cuelga de `empresa` (→ tenant=estudio), como IVA.

## 5. Estructura de módulo propuesta
```
app/Modules/Sueldos/
├── Calc/        FormulaEvaluator, LiquidacionCalculator, AntiguedadCalculator, ...
├── Controllers/ EmpleadoController, ConceptoController, LiquidacionController,
│                ReciboController, CatalogoSueldosController, ...
├── Repositories/ EmpleadoRepository, ConceptoRepository, LiquidacionRepository, ...
├── Services/    EmpleadoService, ConceptoService, LiquidacionService (transaccional), ...
├── Requests/
├── ServiceProvider.php
└── routes.php
```
`Compartido` gana: `sueldos`-agnósticos (localidades) y, si hace falta, campos de
identidad en `empresas`.

## 6. Fases de implementación
1. **Compartido v2 + catálogos Sueldos**: campos de identidad faltantes en `empresas`
   (p. ej. nro_anses/actividad si aplica) + `sueldos_empresa_config`; catálogos
   (categorías, obra social, régimen jubilatorio, modalidad, situación revista,
   condición laboral, estado civil, nacionalidades, parentescos, departamentos…).
2. **Legajo**: `empleados` (PERSONAL, subset núcleo) + `familiares`; CRUD.
3. **Conceptos**: `conceptos` (+ fórmula) + variables; CRUD. Diseño del
   **FormulaEvaluator**.
4. **Liquidación**: `liquidaciones` + snapshot + `recibos`; `LiquidacionService`
   que evalúa fórmulas por empleado y persiste recibos (transaccional). Recálculo.
5. **Contribuciones / Ganancias 4ta**.
6. **LSD / SICOSS / exportaciones** (formato AFIP — requiere spec, como CITI en IVA).
7. **Reportes**: recibos (PDF), libro de sueldos, netos por período.

## 7. Riesgos
- **FormulaEvaluator**: reproducir el lenguaje de fórmulas del legacy (operadores,
  funciones, referencias a variables/acumuladores) con exactitud. Validar contra
  datos demo (`sueldos_data.sql`, 21.667 filas) y `sueldos_referencia_psql.sql`.
- **Volumen**: 103 tablas; muchas son catálogos/convenios opcionales → priorizar el
  flujo núcleo y diferir convenios específicos (UOCRA/FAECYS/COMERCIO/SPEP) y
  seguros, documentándolos.
- **Ganancias 4ta y SICOSS/LSD**: lógica fiscal compleja y cambiante; encarar tras
  el núcleo de liquidación.
