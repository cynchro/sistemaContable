/**
 * Catálogos de referencia de la plantilla legacy "Visual IVA" (hojas Compras/
 * Ventas, columnas ocultas DE-DW en adelante) — extraídos una sola vez de un
 * ejemplo real (Plantilla_Visual_IVA_202605.xls, ver flujo/ — no versionado por
 * contener datos de un cliente) y volcados acá para no depender del xls en el
 * generador. Usados para reconstruir las hojas "humanas" (Compras/Ventas, con
 * el texto "código descripción" de cada desplegable) y las hojas de import
 * (Hoja2/Hoja3, con el código puro).
 */

export interface EntradaCatalogo {
  descripcion: string;
  codigo: string;
}

/** Tipo de comprobante — códigos CITI de 3 dígitos. El código de ARCA (columna
 * "Tipo de Comprobante" del CSV, sin ceros a la izquierda) coincide numéricamente
 * con el código CITI para todos los tipos estándar (validado contra datos reales:
 * 1->001 Factura A, 3->003 NC A, 6->006 Factura B, 11->011 Factura C). */
export const TIPO_COMPROBANTE: EntradaCatalogo[] = [
  { descripcion: "001 FACTURAS A", codigo: "001" },
  { descripcion: "002 NOTAS DE DEBITO A", codigo: "002" },
  { descripcion: "003 NOTAS DE CREDITO A", codigo: "003" },
  { descripcion: "004 RECIBOS A", codigo: "004" },
  { descripcion: "005 NOTAS DE VENTA AL CONTADO A", codigo: "005" },
  { descripcion: "006 FACTURAS B", codigo: "006" },
  { descripcion: "007 NOTAS DE DEBITO B", codigo: "007" },
  { descripcion: "008 NOTAS DE CREDITO B", codigo: "008" },
  { descripcion: "009 RECIBOS B", codigo: "009" },
  { descripcion: "011 FACTURAS C", codigo: "011" },
  { descripcion: "012 NOTAS DE DEBITO C", codigo: "012" },
  { descripcion: "013 NOTAS DE CREDITO C", codigo: "013" },
  { descripcion: "015 RECIBOS C", codigo: "015" },
  { descripcion: "016 NOTAS DE VENTA AL CONTADO C", codigo: "016" },
  { descripcion: "017 LIQUIDACION DE SERVICIOS PUBLICOS CLASE A", codigo: "017" },
  { descripcion: "018 LIQUIDACION DE SERVICIOS PUBLICOS CLASE B", codigo: "018" },
  { descripcion: "019 FACTURAS DE EXPORTACION", codigo: "019" },
  { descripcion: "020 NOTAS DE DEBITO POR OPERACIONES CON EL EXTERIOR", codigo: "020" },
  { descripcion: "021 NOTAS DE CREDITO POR OPERACIONES CON EL EXTERIOR", codigo: "021" },
  { descripcion: "022 FACTURAS - PERMISO EXPORTACION SIMPLIFICADO - DTO. 855/97", codigo: "022" },
  { descripcion: "023 COMPROBANTES “A” DE COMPRA PRIMARIA PARA EL SECTOR PESQUERO MARITIMO", codigo: "023" },
  { descripcion: "024 COMPROBANTES “A” DE CONSIGNACION PRIMARIA PARA EL SECTOR PESQUERO MARITIMO", codigo: "024" },
  { descripcion: "025 COMPROBANTES “B” DE COMPRA PRIMARIA PARA EL SECTOR PESQUERO MARITIMO", codigo: "025" },
  { descripcion: "026 COMPROBANTES “B” DE CONSIGNACION PRIMARIA PARA EL SECTOR PESQUERO MARITIMO", codigo: "026" },
  { descripcion: "027 LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE A", codigo: "027" },
  { descripcion: "028 LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE B", codigo: "028" },
  { descripcion: "029 LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE C", codigo: "029" },
  { descripcion: "030 COMPROBANTES DE COMPRA DE BIENES USADOS", codigo: "030" },
  { descripcion: "031 MANDATO - CONSIGNACION", codigo: "031" },
  { descripcion: "032 COMPROBANTES PARA RECICLAR MATERIALES", codigo: "032" },
  { descripcion: "033 LIQUIDACION PRIMARIA DE GRANOS", codigo: "033" },
  { descripcion: "034 COMPROBANTES A DEL APARTADO A  INCISO F)  R.G. N°  1415", codigo: "034" },
  { descripcion: "035 COMPROBANTES B DEL ANEXO I, APARTADO A, INC. F), R.G. N° 1415", codigo: "035" },
  { descripcion: "036 COMPROBANTES C DEL Anexo I, Apartado A, INC.F), R.G. N° 1415", codigo: "036" },
  { descripcion: "037 NOTAS DE DEBITO O DOCUMENTO EQUIVALENTE QUE CUMPLAN CON LA R.G. N° 1415", codigo: "037" },
  { descripcion: "038 NOTAS DE CREDITO O DOCUMENTO EQUIVALENTE QUE CUMPLAN CON LA R.G. N° 1415", codigo: "038" },
  { descripcion: "039 OTROS COMPROBANTES A QUE CUMPLEN CON LA R G  1415", codigo: "039" },
  { descripcion: "040 OTROS COMPROBANTES B QUE CUMPLAN CON LA R.G. N° 1415", codigo: "040" },
  { descripcion: "041 OTROS COMPROBANTES C QUE CUMPLAN CON LA R.G. N° 1415", codigo: "041" },
  { descripcion: "043 NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE B", codigo: "043" },
  { descripcion: "044 NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE C", codigo: "044" },
  { descripcion: "045 NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE A", codigo: "045" },
  { descripcion: "046 NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE B", codigo: "046" },
  { descripcion: "047 NOTA DE DEBITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE C", codigo: "047" },
  { descripcion: "048 NOTA DE CREDITO LIQUIDACION UNICA COMERCIAL IMPOSITIVA CLASE A", codigo: "048" },
  { descripcion: "049 COMPROBANTES DE COMPRA DE BIENES NO REGISTRABLES A CONSUMIDORES FINALES", codigo: "049" },
  { descripcion: "050 RECIBO FACTURA A  REGIMEN DE FACTURA DE CREDITO ", codigo: "050" },
  { descripcion: "051 FACTURAS M", codigo: "051" },
  { descripcion: "052 NOTAS DE DEBITO M", codigo: "052" },
  { descripcion: "053 NOTAS DE CREDITO M", codigo: "053" },
  { descripcion: "054 RECIBOS M", codigo: "054" },
  { descripcion: "055 NOTAS DE VENTA AL CONTADO M", codigo: "055" },
  { descripcion: "056 COMPROBANTES M DEL ANEXO I  APARTADO A  INC F) R.G. N° 1415", codigo: "056" },
  { descripcion: "057 OTROS COMPROBANTES M QUE CUMPLAN CON LA R.G. N° 1415", codigo: "057" },
  { descripcion: "058 CUENTAS DE VENTA Y LIQUIDO PRODUCTO M", codigo: "058" },
  { descripcion: "059 LIQUIDACIONES M", codigo: "059" },
  { descripcion: "060 CUENTAS DE VENTA Y LIQUIDO PRODUCTO A", codigo: "060" },
  { descripcion: "061 CUENTAS DE VENTA Y LIQUIDO PRODUCTO B", codigo: "061" },
  { descripcion: "063 LIQUIDACIONES A", codigo: "063" },
  { descripcion: "064 LIQUIDACIONES B", codigo: "064" },
  { descripcion: "066 DESPACHO DE IMPORTACION", codigo: "066" },
  { descripcion: "068 LIQUIDACION C", codigo: "068" },
  { descripcion: "070 RECIBOS FACTURA DE CREDITO", codigo: "070" },
  { descripcion: "080 INFORME DIARIO DE CIERRE (ZETA) - CONTROLADORES FISCALES", codigo: "080" },
  { descripcion: "081 TIQUE FACTURA A   ", codigo: "081" },
  { descripcion: "082 TIQUE FACTURA B", codigo: "082" },
  { descripcion: "083 TIQUE", codigo: "083" },
  { descripcion: "088 REMITO ELECTRONICO", codigo: "088" },
  { descripcion: "089 RESUMEN DE DATOS", codigo: "089" },
  { descripcion: "090 OTROS COMPROBANTES - DOCUMENTOS EXCEPTUADOS - NOTAS DE CREDITO", codigo: "090" },
  { descripcion: "091 REMITOS R", codigo: "091" },
  { descripcion: "099 OTROS COMPROBANTES QUE NO CUMPLEN O ESTÁN EXCEPTUADOS DE LA R.G. 1415 Y SUS MODIF ", codigo: "099" },
  { descripcion: "110 TIQUE NOTA DE CREDITO ", codigo: "110" },
  { descripcion: "111 TIQUE FACTURA C", codigo: "111" },
  { descripcion: "112  TIQUE NOTA DE CREDITO A", codigo: "112" },
  { descripcion: "113 TIQUE NOTA DE CREDITO B", codigo: "113" },
  { descripcion: "114 TIQUE NOTA DE CREDITO C", codigo: "114" },
  { descripcion: "115 TIQUE NOTA DE DEBITO A", codigo: "115" },
  { descripcion: "116 TIQUE NOTA DE DEBITO B", codigo: "116" },
  { descripcion: "117 TIQUE NOTA DE DEBITO C", codigo: "117" },
  { descripcion: "118 TIQUE FACTURA M", codigo: "118" },
  { descripcion: "119 TIQUE NOTA DE CREDITO M", codigo: "119" },
  { descripcion: "120 TIQUE NOTA DE DEBITO M", codigo: "120" },
  { descripcion: "331 LIQUIDACION SECUNDARIA DE GRANOS", codigo: "331" },
  { descripcion: "332 CERTIFICACION ELECTRONICA (GRANOS)", codigo: "332" },
];

/** Tipo de documento del comprador/vendedor. El código real (columna "Tipo Doc.
 * Vendedor/Comprador" del CSV) es directamente el prefijo numérico (80=CUIT,
 * 86=CUIL, 96=DNI, etc.) — esta tabla es solo para el texto del desplegable. */
export const TIPO_DOCUMENTO: EntradaCatalogo[] = [
  { descripcion: "80 - CUIT", codigo: "CUIT" },
  { descripcion: "86 - CUIL", codigo: "86" },
  { descripcion: "87 - CDI", codigo: "CDI" },
  { descripcion: "89 - LE", codigo: "LE" },
  { descripcion: "90 - LC", codigo: "LC" },
  { descripcion: "91 - CI extranjera", codigo: "CI extranjera" },
  { descripcion: "94 - Pasaporte", codigo: "Pasaporte" },
  { descripcion: "96 - DNI", codigo: "DNI" },
  { descripcion: "99  - Sin identificar", codigo: "Sin identificar" },
];

/** Código de moneda. "PES" (pesos, el único caso real hasta ahora) mapea
 * directo sin traducción — el resto son códigos AFIP de 3 dígitos. */
export const MONEDA: EntradaCatalogo[] = [
  { descripcion: "000 OTRAS MONEDAS ", codigo: "000" },
  { descripcion: "002 Dólar EEUU LIBRE ", codigo: "002" },
  { descripcion: "003 FRANCOS FRANCESES ", codigo: "003" },
  { descripcion: "004 LIRAS ITALIANAS ", codigo: "004" },
  { descripcion: "005 PESETAS ", codigo: "005" },
  { descripcion: "006 MARCOS ALEMANES ", codigo: "006" },
  { descripcion: "007 FLORINES HOLANDESES ", codigo: "007" },
  { descripcion: "008 FRANCOS BELGAS ", codigo: "008" },
  { descripcion: "009 FRANCOS SUIZOS ", codigo: "009" },
  { descripcion: "010 PESOS MEJICANOS ", codigo: "010" },
  { descripcion: "011 PESOS URUGUAYOS ", codigo: "011" },
  { descripcion: "012 REAL ", codigo: "012" },
  { descripcion: "013 ESCUDOS PORTUGUESES ", codigo: "013" },
  { descripcion: "014 CORONAS DANESAS ", codigo: "014" },
  { descripcion: "015 CORONAS NORUEGAS ", codigo: "015" },
  { descripcion: "016 CORONAS SUECAS ", codigo: "016" },
  { descripcion: "017 CHELINES AUTRIACOS ", codigo: "017" },
  { descripcion: "018 Dólar CANADIENSE ", codigo: "018" },
  { descripcion: "019 YENS ", codigo: "019" },
  { descripcion: "021 LIBRA ESTERLINA ", codigo: "021" },
  { descripcion: "022 MARCOS FINLANDESES ", codigo: "022" },
  { descripcion: "023 BOLIVAR (VENEZOLANO)", codigo: "023" },
  { descripcion: "024 CORONA CHECA ", codigo: "024" },
  { descripcion: "025 DINAR (YUGOSLAVO) ", codigo: "025" },
  { descripcion: "026 Dólar AUSTRALIANO ", codigo: "026" },
  { descripcion: "027 DRACMA (GRIEGO) ", codigo: "027" },
  { descripcion: "028 FLORIN (ANTILLAS HOLA ", codigo: "028" },
  { descripcion: "029 GUARANI ", codigo: "029" },
  { descripcion: "030 SHEKEL (ISRAEL) ", codigo: "030" },
  { descripcion: "031 PESO BOLIVIANO ", codigo: "031" },
  { descripcion: "032 PESO COLOMBIANO ", codigo: "032" },
  { descripcion: "033 PESO CHILENO ", codigo: "033" },
  { descripcion: "034 RAND (SUDAFRICANO)", codigo: "034" },
  { descripcion: "035 NUEVO SOL PERUANO ", codigo: "035" },
  { descripcion: "036 SUCRE (ECUATORIANO) ", codigo: "036" },
  { descripcion: "040 LEI RUMANOS ", codigo: "040" },
  { descripcion: "041 DERECHOS ESPECIALES DE GIRO ", codigo: "041" },
  { descripcion: "042 PESOS DOMINICANOS ", codigo: "042" },
  { descripcion: "043 BALBOAS PANAMEÑAS ", codigo: "043" },
  { descripcion: "044 CORDOBAS NICARAGÛENSES ", codigo: "044" },
  { descripcion: "045 DIRHAM MARROQUÍES ", codigo: "045" },
  { descripcion: "046 LIBRAS EGIPCIAS ", codigo: "046" },
  { descripcion: "047 RIYALS SAUDITAS ", codigo: "047" },
  { descripcion: "048 BRANCOS BELGAS FINANCIERAS", codigo: "048" },
  { descripcion: "049 GRAMOS DE ORO FINO ", codigo: "049" },
  { descripcion: "050 LIBRAS IRLANDESAS ", codigo: "050" },
  { descripcion: "051 Dólar DE HONG KONG ", codigo: "051" },
  { descripcion: "052 Dólar DE SINGAPUR ", codigo: "052" },
  { descripcion: "053 Dólar DE JAMAICA ", codigo: "053" },
  { descripcion: "054 Dólar DE TAIWAN ", codigo: "054" },
  { descripcion: "055 QUETZAL (GUATEMALTECOS) ", codigo: "055" },
  { descripcion: "056 FORINT (HUNGRIA) ", codigo: "056" },
  { descripcion: "057 BAHT (TAILANDIA) ", codigo: "057" },
  { descripcion: "058 ECU", codigo: "058" },
  { descripcion: "059 DINAR KUWAITI", codigo: "059" },
  { descripcion: "060 EURO", codigo: "060" },
  { descripcion: "061 ZLTYS POLACOS", codigo: "061" },
  { descripcion: "062 RUPIAS HINDÚES", codigo: "062" },
  { descripcion: "063 LEMPIRAS HONDUREÑAS", codigo: "063" },
  { descripcion: "064 YUAN (Rep. Pop. China)", codigo: "064" },
  { descripcion: "DOL Dólar ESTADOUNIDENSE", codigo: "DOL " },
  { descripcion: "PES PESOS", codigo: "PES" },
];

/** Tipo de operación de compra — NO viene en el CSV de ARCA, hay que asignar un
 * default (ver output/plantillaVisualIva.ts). */
export const TIPO_OPERACION_COMPRA: EntradaCatalogo[] = [
  { descripcion: "Compras de bienes (excepto bienes de uso)", codigo: "1" },
  { descripcion: "Locaciones", codigo: "2" },
  { descripcion: "Prestaciones de servicio", codigo: "3" },
  { descripcion: "Inversiones en bienes de uso", codigo: "4" },
  { descripcion: "Compras de bienes usados a consumidores finales", codigo: "5" },
  { descripcion: "Tur IVA", codigo: "6" },
  { descripcion: "Contribuciones de la seguridad social", codigo: "7" },
  { descripcion: "Otros conceptos", codigo: "8" },
];

/** Tipo de operación de venta — NO viene en el CSV de ARCA, default. */
export const TIPO_OPERACION_VENTA: EntradaCatalogo[] = [
  { descripcion: "Ventas de cosas muebles, obras, locaciones y/o prestaciones de de servicios", codigo: "1" },
  { descripcion: "Venta de bienes de uso", codigo: "2" },
];

/** Actividad (primaria/secundaria en Visual IVA) — NO viene en el CSV, default. */
export const ACTIVIDAD: EntradaCatalogo[] = [
  { descripcion: "Actividad Primaria en Visual Iva", codigo: "0" },
  { descripcion: "Actividad Secundaria en Visual Iva", codigo: "1" },
];

/** Concepto (AFIP: 1=Productos, 2=Servicios, 3=Productos-Servicios) — NO viene
 * en el CSV, default. */
export const CONCEPTO: EntradaCatalogo[] = [
  { descripcion: "Productos", codigo: "1" },
  { descripcion: "Servicios", codigo: "2" },
  { descripcion: "Productos-Servicios", codigo: "3" },
];

