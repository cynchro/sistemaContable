# Modelo de negocio — Ecosistema Contable

> Análisis del modelo de negocio del sistema (a 2026-07-08). El producto es un **ecosistema
> de software para estudios contables** construido sobre el framework propio **Modux** y
> desplegado en la plataforma propia (platform-v5). Nace del trabajo con el **Estudio Haddad**
> (ingeniería inversa del "Visual IVA" y de los sistemas de Sueldos y CRM/fiscal), con la meta
> de unificar los tres sistemas legacy del estudio en uno solo, homogéneo y sin redundancia.

---

## 1. En una frase

**Un sistema integral que reemplaza los múltiples programas sueltos que hoy usa un estudio
contable (IVA, sueldos, gestión/CRM fiscal) por una sola plataforma web, multi-empresa y
conectada con ARCA.** Se cobra como servicio (suscripción) al estudio, que lo usa para operar
la contabilidad e impuestos de todos sus clientes contribuyentes.

---

## 2. Propuesta de valor

El dolor de un estudio contable chico/mediano hoy es la **fragmentación**: un programa de
escritorio para IVA (el "Visual IVA"), otro para sueldos, planillas de Excel para gestión de
vencimientos y honorarios, y carga manual repetida en cada uno. Los datos del mismo
contribuyente (CUIT, domicilio, condición fiscal) se reescriben una y otra vez.

Qué resuelve el ecosistema:

- **Unificación sin redundancia.** El contribuyente es una única entidad (`empresas`) que
  atraviesa todos los módulos (IVA, Sueldos, Fiscal, Tareas, Honorarios). Se carga una vez.
- **Fidelidad con lo que ya saben usar.** El frontend replica el flujo del Visual IVA que el
  estudio ya domina ("está idéntico al Visual IVA", devolución del contador), bajando a casi
  cero la curva de aprendizaje.
- **Cumplimiento con ARCA de punta a punta.** Libro IVA Digital (Portal IVA), DJ IVA Simple
  (F2051), factura electrónica (WSFEv1 + CAE), consulta de padrón, y exportaciones de rentas
  (SIFERE Convenio Multilateral) — validadas byte a byte contra los archivos reales del estudio.
- **Web y multi-dispositivo.** Deja atrás el software de escritorio atado a una PC/licencia;
  se accede desde el navegador, con los datos centralizados y respaldados.
- **Automatización del trabajo repetitivo.** Importación de "Mis Comprobantes", presets de
  carga para comprobantes manuales (resúmenes bancarios, tickets, préstamos, tarjetas), y a
  futuro conciliaciones e ingesta automática.

**Diferencial central:** no es un ERP genérico adaptado a la fuerza, sino un producto
**modelado sobre la operación real de un estudio argentino**, con las reglas fiscales de ARCA
y de convenio multilateral incorporadas y verificadas contra casos reales.

---

## 3. Segmento de clientes

- **Cliente primario (quien paga y usa): el estudio contable.** Es el *tenant* del sistema.
  Perfil: estudios chicos y medianos que hoy llevan la contabilidad de decenas o cientos de
  contribuyentes con software de escritorio + planillas.
- **Usuario final dentro del estudio:** contadores y administrativos (con roles/permisos
  diferenciados: quién carga, quién liquida, quién administra).
- **Beneficiario indirecto: el contribuyente** (la empresa/persona cliente del estudio), que
  recibe mejores tiempos, reportes y trazabilidad, aunque no opera el sistema directamente.

El diseño **multi-tenant** (cada estudio ve solo sus datos) es lo que habilita venderlo a más
de un estudio con la misma plataforma.

---

## 4. El producto (módulos)

| Área | Qué cubre | Estado |
|---|---|---|
| **Compartido** | Contribuyentes, períodos, plan de cuentas, rubros, catálogos AFIP | Núcleo |
| **IVA** | Ventas/compras, Libro IVA, DDJJ (F2002/F2051), subdiario, Libro IVA Digital, SIFERE, mayorización y reportes de Mayor | Completo |
| **AFIP** | WSAA, padrón, factura electrónica (CAE), puntos de venta | Completo (falta cert. de producción) |
| **Sueldos** | Legajos, conceptos con fórmula, liquidación, recibos, SAC, vacaciones, contribuciones | Operable |
| **Fiscal / Tareas / Honorarios** | Vencimientos, workflow del estudio, honorarios por servicios/complejidad | Operable |
| **Automatización** | Ingesta y conciliaciones automáticas | En diseño |

La estrategia de construcción fue **ingeniería inversa de los tres sistemas legacy** del
estudio y su unificación sobre una entidad canónica de contribuyente, para no recrear la
fragmentación que se busca eliminar.

---

## 5. Fuentes de ingreso (posibles)

- **Suscripción por estudio (SaaS).** Cuota mensual/anual, típicamente escalonada por tamaño
  (cantidad de contribuyentes activos, cantidad de usuarios, o módulos contratados).
- **Por módulo.** IVA de base; Sueldos, Automatización y add-ons como upsell.
- **Servicios de implementación / migración.** Alta del estudio y **migración de datos reales**
  desde el sistema legacy (dedup de contribuyentes por CUIT) como servicio inicial.
- **Uso intensivo / consumo.** Eventualmente, cargos por volumen de factura electrónica u
  otras integraciones que tengan costo variable.

El modelo natural es **suscripción recurrente + una implementación inicial**, que da ingresos
predecibles y alto costo de cambio una vez migrados los datos.

---

## 6. Estructura de costos

- **Desarrollo y mantenimiento** del producto (el costo dominante en esta etapa).
- **Infraestructura**: la plataforma propia (platform-v5) sobre un VPS, con contenedores por
  app, base de datos y registry — costo relativamente bajo y compartido entre tenants.
- **Soporte y onboarding** de cada estudio nuevo (migración de datos, capacitación — mitigada
  por la fidelidad con el Visual IVA y por estos manuales de uso).
- **Cumplimiento**: seguir los cambios normativos de ARCA y de rentas provinciales (los
  formatos de exportación cambian y hay que mantenerlos al día).

---

## 7. Ventajas competitivas (foso)

1. **Conocimiento del dominio validado.** Las reglas fiscales están reconstruidas y
   confirmadas con un contador y contra archivos reales (Libro IVA Digital, F2051, SIFERE
   byte a byte). Replicar eso desde cero es caro y lento.
2. **Unificación real de los 3 sistemas** sin redundancia — algo que las alternativas
   (programas sueltos) no ofrecen.
3. **Costo de cambio alto**: una vez migrados los datos y aprendido el flujo (idéntico al que
   ya usaban), cambiar de sistema es costoso para el estudio.
4. **Plataforma y framework propios** (Modux + platform-v5): despliegue y multi-tenancy sin
   depender de terceros, con margen para escalar a más estudios.

---

## 8. Riesgos y dependencias

- **Dependencia normativa de ARCA/rentas:** cambios de formato obligan a mantenimiento continuo.
- **Certificados y habilitaciones:** la factura electrónica en producción requiere el
  certificado de ARCA del contribuyente/estudio.
- **Migración de datos reales:** es el paso crítico para adoptar el sistema en un estudio con
  historia; su calidad define la experiencia inicial.
- **Concentración de cliente:** el producto nace con un estudio ancla (Haddad); productizarlo
  para vender a otros estudios es el salto de "software a medida" a "producto".

---

## 9. Camino a producto (de un estudio a muchos)

El sistema ya está construido de forma **multi-tenant y modular**, así que el salto de "sistema
del Estudio Haddad" a "producto para estudios contables" es principalmente comercial y de
empaquetado, no de arquitectura: onboarding autoservicio, planes por módulo/tamaño, y un
catálogo de **manuales de uso** (esta misma sección del sistema) que reduzca el costo de
soporte. La automatización (ingesta y conciliaciones) es la próxima palanca de valor para
diferenciarse y justificar la suscripción.
