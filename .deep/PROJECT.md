# ecosistema

Sistema contable de estudio (IVA, Sueldos, gestión, AFIP) con extracción automática de comprobantes desde ARCA.  
**Organización:** monorepo con backend PHP 8.2+ (framework propio Modux, monolito modular, API JSON), frontend React SPA (Vite, TypeScript, CoreUI) y extractor Node+Playwright standalone (scraping del Portal IVA, aún no integrado).  
**Entorno:** Dockerizado. `docker compose up -d` levanta frontend (dev server HMR en :5173), backend (:8080) y MySQL (:3308). Tras clonar, ejecutar `docker compose exec modux-back

## Notas del proyecto
<!-- Editá esta sección: el CLI la respeta en build y update.
Ejemplos: "La API key va en .env", "No modificar legacy/",
"Usar pytest para los tests", "Toda respuesta incluye campo timestamp". -->
