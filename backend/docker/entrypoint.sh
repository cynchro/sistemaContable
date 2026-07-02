#!/bin/sh
# Render .env from the container environment for immutable/platform deploys where
# the source .env is not bind-mounted. No-op if one already exists.
sh /var/www/html/docker/gen-env.sh

# Fix storage permissions at container start (volume mount overrides Dockerfile chown)
mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage
chmod -R ug+rwX /var/www/html/storage

exec apache2-foreground
