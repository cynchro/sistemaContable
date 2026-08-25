import path from "node:path";
import { leerComprobantesDesdeArchivo } from "../extract/libroCsv.js";
import { generarPlantillaVisualIva } from "../output/plantillaVisualIva.js";

function parseArgs(): { compras: string; ventas: string; salida: string } {
  const args = process.argv.slice(2);
  const get = (flag: string) => {
    const idx = args.indexOf(flag);
    return idx >= 0 ? args[idx + 1] : undefined;
  };
  const compras = get("--compras");
  const ventas = get("--ventas");
  const salida = get("--salida") ?? "salida/plantilla-visual-iva.xlsx";

  if (!compras || !ventas) {
    throw new Error(
      "Uso: npm run convertir -- --compras <csv> --ventas <csv> [--salida <xlsx>]",
    );
  }
  return { compras, ventas, salida };
}

async function main() {
  const { compras, ventas, salida } = parseArgs();

  console.log(`Leyendo compras de ${compras}...`);
  const comprobantesCompras = await leerComprobantesDesdeArchivo(compras);
  console.log(`  ${comprobantesCompras.length} comprobantes.`);

  console.log(`Leyendo ventas de ${ventas}...`);
  const comprobantesVentas = await leerComprobantesDesdeArchivo(ventas);
  console.log(`  ${comprobantesVentas.length} comprobantes.`);

  const wb = await generarPlantillaVisualIva({
    compras: comprobantesCompras,
    ventas: comprobantesVentas,
  });

  const rutaSalida = path.resolve(salida);
  await wb.xlsx.writeFile(rutaSalida);
  console.log(`Guardado en ${rutaSalida}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
