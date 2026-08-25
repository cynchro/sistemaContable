import { config, requireArcaCuit } from "../config.js";
import { openSession, persistSession } from "../auth/session.js";
import { login } from "../auth/login.js";

async function main() {
  const cuit = requireArcaCuit();
  if (!config.arcaClaveFiscal) {
    throw new Error("Falta ARCA_CLAVE_FISCAL en .env para hacer login");
  }

  const { browser, context } = await openSession(cuit);
  const page = await context.newPage();

  console.log(`Iniciando login en ARCA para CUIT ${cuit}...`);
  await login(page, cuit, config.arcaClaveFiscal);

  await persistSession(cuit, context);
  console.log(`Sesión guardada en ${config.storageDir}. Ya se puede correr "npm run traer".`);

  await browser.close();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
