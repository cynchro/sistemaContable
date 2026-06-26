# Build de la SPA y servido como estáticos por nginx (PRODUCCIÓN).
# Para desarrollo NO se usa este Dockerfile: el docker-compose.yml corre Vite (HMR).
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
# La URL de la API se hornea en el bundle (la SPA corre en el navegador). En prod
# pasala como build-arg: --build-arg VITE_API_URL=https://api.tu-dominio.
ARG VITE_API_URL=http://localhost:8080
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build

FROM nginx:alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
