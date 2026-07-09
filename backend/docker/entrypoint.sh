#!/bin/sh
# Render .env from the container environment for immutable/platform deploys where
# the source .env is not bind-mounted. No-op if one already exists.
sh /var/www/html/docker/gen-env.sh

# Fix storage permissions at container start (volume mount overrides Dockerfile chown)
mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage
chmod -R ug+rwX /var/www/html/storage

# Correr migraciones pendientes en cada arranque (idempotente: modux saltea las ya aplicadas).
# Necesario en deploys de plataforma: el código sube pero el esquema no se migra solo. Se espera
# a que la DB esté lista (reintentos) para no fallar si el contenedor arranca antes que MySQL.
i=0
until php /var/www/html/modux migrate; do
  i=$((i + 1))
  if [ "$i" -ge 30 ]; then
    echo "entrypoint: migrate no pudo completar tras 30 intentos; sigo levantando el servidor." >&2
    break
  fi
  echo "entrypoint: migrate falló (¿DB no lista?), reintento $i/30…" >&2
  sleep 2
done

exec apache2-foreground
