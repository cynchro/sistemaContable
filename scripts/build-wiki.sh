#!/usr/bin/env bash
#
# build-wiki.sh — regenera y publica la wiki de GitHub a partir de la doc del repo.
#
# La wiki es un ESPEJO de README.md + docs/*.md (única fuente de verdad): este
# script la reconstruye entera y la pushea, así nunca se desincroniza por olvido.
#
# Uso:
#   scripts/build-wiki.sh             # genera y publica la wiki
#   scripts/build-wiki.sh --no-push   # solo genera en un dir temporal (revisar)
#
# Requisito (una sola vez): la wiki debe existir del lado del servidor. GitHub no
# permite crear una wiki vacía por git; creá la primera página desde la pestaña
# "Wiki" del repo (Settings → Features → Wikis activado), y después este script
# la administra por completo.
#
# Nota: la wiki queda 100% gestionada por este script — las páginas que se editen
# a mano en la web se sobrescriben en la próxima corrida.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

NO_PUSH=0
[ "${1:-}" = "--no-push" ] && NO_PUSH=1

# Mapeo  docs/<archivo>  →  Página-Wiki (el nombre con guiones es el título con
# espacios en GitHub). Mantené esta lista alineada con docs/ y con el índice del
# README.
# El wiki es inglés (igual que el README); el sitio bilingüe vive en GitHub Pages.
# Por eso se leen las variantes *.en.md (el español es el *.md por defecto del sitio).
PAGES=(
  "cli.en.md:CLI"
  "modules.en.md:Modules-and-Routing"
  "http.en.md:HTTP"
  "auth-and-tenancy.en.md:Auth-and-Multi-tenancy"
  "infrastructure.en.md:Infrastructure"
  "platform.en.md:Platform"
  "optional-modules.en.md:Optional-Modules"
)

BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

# 1) Páginas temáticas: contenido desde el primer '## ' (descarta el H1/título y
#    la línea de back-link del manual, que no aplican en la wiki).
for entry in "${PAGES[@]}"; do
  src="${entry%%:*}"
  page="${entry##*:}"
  sed -n '/^## /,$p' "docs/$src" > "$BUILD/$page.md"
done

# 2) Home: el README, con los links a docs/*.md reescritos a páginas de la wiki.
cp README.md "$BUILD/Home.md"
for entry in "${PAGES[@]}"; do
  src="${entry%%:*}"
  page="${entry##*:}"
  sed -i "s#](docs/$src)#]($page)#g" "$BUILD/Home.md"
done
sed -i 's#\[`docs/`\](docs/)#las páginas de esta wiki#g' "$BUILD/Home.md"

# 3) Navegación (sidebar + footer).
cat > "$BUILD/_Sidebar.md" <<'EOF'
### Modux

- [[Home]]
- [[CLI]]
- [[Modules and Routing|Modules-and-Routing]]
- [[HTTP]]
- [[Auth &amp; Multi-tenancy|Auth-and-Multi-tenancy]]
- [[Infrastructure]]
- [[Platform]]
- [[Optional Modules|Optional-Modules]]
EOF

cat > "$BUILD/_Footer.md" <<'EOF'
[Modux](https://github.com/cynchro/modux) · framework PHP modular-monolith, DI-first · MIT
EOF

echo "✓ Generadas $(find "$BUILD" -name '*.md' | wc -l | tr -d ' ') páginas."

if [ "$NO_PUSH" = "1" ]; then
  trap - EXIT   # conservar el dir para revisión
  echo "→ --no-push: revisá el contenido en: $BUILD"
  exit 0
fi

# 4) Clonar la wiki, sincronizar y pushear. La URL se deriva del remoto origin,
#    salvo que se fije MODUX_WIKI_REMOTE (lo usa el CI para inyectar el token).
WIKI_URL="${MODUX_WIKI_REMOTE:-$(git config --get remote.origin.url | sed 's/\.git$//').wiki.git}"
WIKI="$(mktemp -d)"
trap 'rm -rf "$BUILD" "$WIKI"' EXIT

if ! git clone -q "$WIKI_URL" "$WIKI"; then
  echo "✗ No se pudo clonar la wiki: $WIKI_URL" >&2
  echo "  ¿Existe ya? Creá la primera página desde la pestaña Wiki del repo y reintentá." >&2
  exit 1
fi

rm -f "$WIKI"/*.md
cp "$BUILD"/*.md "$WIKI"/

cd "$WIKI"

# Identidad de commit: respeta la global si existe; si no (p. ej. en CI), usa una
# por defecto para no fallar.
if ! git config user.email >/dev/null 2>&1; then
  git config user.name "github-actions[bot]"
  git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
fi

git add -A
if git diff --cached --quiet; then
  echo "→ La wiki ya está al día (sin cambios)."
  exit 0
fi

git commit -q -m "Sync wiki desde docs/ ($(date +%Y-%m-%d))"
git push -q origin HEAD
echo "✓ Wiki publicada: $(echo "$WIKI_URL" | sed 's#git@github.com:#https://github.com/#; s#\.wiki\.git#/wiki#')"
