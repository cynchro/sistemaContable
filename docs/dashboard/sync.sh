#!/usr/bin/env bash
#
# Sincroniza el avance del Ecosistema Contable con el tablero del cliente
# (clientDashboard). Hace login como admin y envía docs/dashboard/progreso.json
# al endpoint idempotente POST /api/import.
#
# Uso:
#   ./sync.sh                         # usa los valores por defecto de abajo
#   BASE_URL=http://localhost ./sync.sh
#   ADMIN_EMAIL=admin@example.com ADMIN_PASS=password123 ./sync.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SNAPSHOT="${SNAPSHOT:-$SCRIPT_DIR/progreso.json}"

BASE_URL="${BASE_URL:-http://localhost}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASS="${ADMIN_PASS:-password123}"

if [[ ! -f "$SNAPSHOT" ]]; then
  echo "ERROR: no existe el snapshot: $SNAPSHOT" >&2
  exit 1
fi

echo "→ Login como $ADMIN_EMAIL en $BASE_URL ..."
LOGIN_RESPONSE="$(curl -sS -X POST "$BASE_URL/api/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASS\"}")"

# Extrae el token sin depender de jq.
TOKEN="$(printf '%s' "$LOGIN_RESPONSE" | sed -n 's/.*"token"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')"

if [[ -z "$TOKEN" ]]; then
  echo "ERROR: login fallido. Respuesta: $LOGIN_RESPONSE" >&2
  exit 1
fi

echo "→ Enviando snapshot ($SNAPSHOT) a $BASE_URL/api/import ..."
curl -sS -X POST "$BASE_URL/api/import" \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $TOKEN" \
  --data-binary "@$SNAPSHOT"
echo
echo "✓ Listo."
