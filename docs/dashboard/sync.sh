#!/usr/bin/env bash
#
# Sincroniza el avance del Ecosistema Contable con el tablero del cliente
# (clientDashboard) enviando docs/dashboard/progreso.json al endpoint idempotente
# POST /api/import.
#
# Autentica con un TOKEN DE SERVICIO (IMPORT_TOKEN), no con usuario/contraseña: así
# el sync no depende de cuentas sembradas y sobrevive a resets de la base.
#
# Uso:
#   IMPORT_TOKEN=... ./sync.sh                 # token de producción (requerido)
#   BASE_URL=http://localhost ./sync.sh        # sobrescribe el destino (dev local)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SNAPSHOT="${SNAPSHOT:-$SCRIPT_DIR/progreso.json}"

# Si IMPORT_TOKEN no viene por entorno, lo tomamos del .env de la raíz del repo
# (gitignored). Permite correr ./sync.sh sin exportar nada a mano.
if [[ -z "${IMPORT_TOKEN:-}" ]]; then
  ENV_FILE="$SCRIPT_DIR/../../.env"
  if [[ -f "$ENV_FILE" ]]; then
    IMPORT_TOKEN="$(sed -n 's/^[[:space:]]*IMPORT_TOKEN[[:space:]]*=[[:space:]]*//p' "$ENV_FILE" | tail -n1)"
  fi
fi

BASE_URL="${BASE_URL:-https://dashboard.cynchro.cloud}"
# Debe coincidir con IMPORT_TOKEN del backend (docker-compose.yml del clientDashboard).
IMPORT_TOKEN="${IMPORT_TOKEN:-dev-import-token-change-in-prod}"

if [[ ! -f "$SNAPSHOT" ]]; then
  echo "ERROR: no existe el snapshot: $SNAPSHOT" >&2
  exit 1
fi

echo "→ Enviando snapshot ($SNAPSHOT) a $BASE_URL/api/import (auth por token) ..."
HTTP_BODY="$(curl -sS -X POST "$BASE_URL/api/import" \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $IMPORT_TOKEN" \
  --data-binary "@$SNAPSHOT" \
  -w $'\n%{http_code}')"

STATUS="$(printf '%s' "$HTTP_BODY" | tail -n1)"
BODY="$(printf '%s' "$HTTP_BODY" | sed '$d')"

echo "$BODY"

if [[ "$STATUS" != "200" ]]; then
  echo "ERROR: el import falló (HTTP $STATUS)." >&2
  exit 1
fi

echo "✓ Listo."
