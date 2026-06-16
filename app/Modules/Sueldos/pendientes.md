# Módulo Sueldos — Pendientes

> Estado: **núcleo operativo COMPLETO y utilizable vía API**. Ciclo: legajo
> (empleados/familiares) → conceptos (con fórmula) → liquidación (novedades →
> recibos) → contribuciones patronales. Multi-tenant, transaccional, con motor de
> cálculos (`FormulaEvaluator`, `AntiguedadCalculator`, `LiquidacionCalculator`,
> `ContribucionCalculator`).
>
> Análisis y decisiones: `docs/ingenieria-inversa/sueldos.md`. Este archivo lista lo
> diferido. Nada de esto bloquea la operación básica.

## A) Supuestos a confirmar contra una liquidación real del legacy
La lógica de liquidación vivía en el cliente Delphi (no en la DB), así que se
reconstruyó desde el modelo + la semántica de fórmulas. Confirmar:
- [ ] **`ANTIG` = años cumplidos** (fórmulas usan `ANTIG/100`, 1%/año). ¿Hay convenios
      con otra escala de antigüedad?
- [ ] **`NOREM`** = acumulador de conceptos no remunerativos ya procesados.
- [ ] **`tipo`**: 1 remunerativo, 2 no remunerativo, 3 descuento (mapeo haber/descuento).
- [ ] **Liquidación dirigida por novedades** (sólo se liquidan conceptos con novedad).
      ¿Hay conceptos "automáticos" sin novedad (p. ej. básico)?
- [ ] **BASICO** = legajo, o `sueldos_categorias.valor` si el legajo está en 0.
- [ ] Variables adicionales que puedan usar las fórmulas además de
      BASICO/CAN/IMP/ANTIG/NOREM (revisar `VARIABLES_CONCEPTOS` y fórmulas reales).

## B) Contribuciones — nuances diferidas
- [ ] **Detracción** (DEC 14/2020 y sucesores) y **topes** (mínimos/máximos de base
      imponible) de `CONTRIBUCIONES_PATRONALES` (el schema extraído está incompleto en
      esos campos). Hoy: `importe = base·% + fijo`, base = remunerativo (+no rem opcional).
- [ ] Conceptos incluidos/excluidos por contribución (`CONCEPTOS_EXCLUIDOS`).
- [ ] Aportes del empleado vs contribuciones patronales (hoy modeladas las patronales).

## C) Ganancias 4ta categoría (Fase pendiente) — REQUIERE SPEC
- [ ] `TABLA_GANANCIAS`, `DEDUCCIONES_GANANCIAS`, `TIPO_DEDUCCIONES`,
      `RETENCIONES_GANANCIAS`, `RETENCIONES_ANT_MANUALES`, `TOPES_HISTORICO`. Lógica
      fiscal compleja y **anual** (escalas, deducciones, topes). No implementar de
      memoria: usar las tablas oficiales del período. Las deducciones por familiar
      ya tienen base en `familiares` (deduce_ganancias, porc_deduccion).

## D) LSD / SICOSS / exportaciones (Fase pendiente) — REQUIERE SPEC AFIP
- [ ] **Libro de Sueldos Digital (LSD)**: `LSD_CABECERA`, `LSD_CONCEPTOS`,
      `LSD_DETALLE`, `LSD_EVENTUALES` + flags por concepto (`CREDITO_DEBITO_LSD`,
      `UNIDAD_LSD`, `EXPORTA_CAN_A_LSD`).
- [ ] **SICOSS** (F931): generación del archivo. Flags `SEGURO_SICOSS`, bases
      diferenciales por concepto (`BASE_DIF_*`).
- [ ] **Export a Contable** (`EXPO_CTA_*` de la empresa) → asientos hacia el módulo
      contable/cuentas.
- [ ] Exportador CSV/TXT del libro de sueldos (reusar `App\Support\Csv\CsvWriter`).

## E) Reportes / presentación
- [ ] **Recibo de sueldo en PDF** (haberes/descuentos/neto + datos empleado/empresa).
- [ ] **Libro de Sueldos** (Ley 20.744), listados, netos por período, orden de pago bancaria.

## F) Funcionalidad de legajo/empresa diferida
- [ ] **Snapshot** del legajo y conceptos al liquidar (`PERSONAL_LIQUIDACIONES` /
      `CONCEPTOS_LIQUIDACION` del legacy) — hoy el recibo referencia empleado/concepto
      vigentes. Necesario para que una liquidación vieja no cambie al editar el legajo.
- [ ] `sueldos_empresa_config`: hoy sólo existe la tabla; falta su ABM/uso (jornada,
      tipo de recibo, etc.).
- [ ] Catálogos sin ABM/seed (estados civiles, nacionalidades, obras sociales, etc.):
      sembrar valores AFIP estándar.
- [ ] **Familiares**: CRUD (la tabla existe; falta el vertical).
- [ ] **Convenios específicos** del legajo: UOCRA, FAECYS, COMERCIO, UOM, SPEP/SGO,
      seguros — muchos campos de `PERSONAL` diferidos.
- [ ] Asistencia/horarios/fichadas, vacaciones, embargos, incapacidades.

## G) RBAC / permisos
- [ ] Aplicar `PermissionMiddleware` por recurso (hoy Auth + Tenant): `empleados.*`,
      `conceptos.*`, `liquidaciones.*`, `liquidaciones.liquidar`, etc.
