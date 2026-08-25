# Imagen oficial de Playwright: ya trae Chromium + todas las libs del sistema
# necesarias para correrlo (evita el infierno de dependencias de un browser
# headless en una imagen Node pelada). Fijar el tag a la misma versión de
# "playwright" en package.json — si se bumpea una, bumpear la otra.
FROM mcr.microsoft.com/playwright:v1.48.0-jammy

WORKDIR /app

# Los browsers ya están instalados en la imagen base, en la versión exacta
# que necesita este playwright — no hace falta que npm los vuelva a bajar.
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1

COPY package.json package-lock.json* ./
RUN npm ci

COPY tsconfig.json ./
COPY src ./src

# Sin ENTRYPOINT fijo: el comando real (login vs. traer, período, flags) lo
# define docker-compose.yml / `docker compose run`, porque no tiene sentido
# correr siempre lo mismo (ver README: "login" corre fuera de Docker).
CMD ["npm", "run", "traer"]
