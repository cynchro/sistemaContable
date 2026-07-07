#!/bin/sh
# Materialize /var/www/html/.env from the container environment. Needed for
# immutable/platform deploys where the source .env is NOT bind-mounted (the app's
# bootstrap requires the file to exist on disk). No-op if a .env already exists.
ENV_FILE=/var/www/html/.env
[ -f "$ENV_FILE" ] && exit 0
printenv | grep -E '^(APP_|DB_|JWT_|AFIP_|CORS_|MAIL_|LOG_|DISPLAY_ERRORS)=' > "$ENV_FILE" || true
