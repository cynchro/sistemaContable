#!/usr/bin/env bash
# =====================================================================
# release.sh — un comando para publicar una versión.
#
# Bumpea frontend/package.json, verifica el build, commitea, mergea a main,
# crea el tag vX.Y.Z y pushea todo. El webhook de GitHub auto-deploya el VPS.
#
# Uso:
#   scripts/release.sh [patch|minor|major|X.Y.Z]
#     patch (default)  0.3.0 -> 0.3.1
#     minor            0.3.0 -> 0.4.0
#     major            0.3.0 -> 1.0.0
#     X.Y.Z            versión explícita (ej. 1.2.0)
#
# Requisitos: correr con el working tree LIMPIO (tus cambios ya commiteados).
# =====================================================================
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"
PKG="frontend/package.json"
BUMP="${1:-patch}"

say() { printf '\033[1;36m== %s\033[0m\n' "$*"; }
die() { printf '\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }

# 0) Pre-condiciones ---------------------------------------------------
[ -f "$PKG" ] || die "no encuentro $PKG (¿estás en el repo?)"
[ -z "$(git status --porcelain)" ] || die "hay cambios sin commitear. Commiteá tus cambios antes de publicar."
WORK="$(git branch --show-current)"
[ -n "$WORK" ] || die "estás en un detached HEAD; posicionate en tu rama de trabajo."

# 1) Calcular la nueva versión ----------------------------------------
CUR="$(grep -m1 '"version"' "$PKG" | sed -E 's/.*"version": *"([^"]+)".*/\1/')"
IFS=. read -r MA MI PA <<<"$CUR"
case "$BUMP" in
  patch) NEW="$MA.$MI.$((PA+1))" ;;
  minor) NEW="$MA.$((MI+1)).0" ;;
  major) NEW="$((MA+1)).0.0" ;;
  [0-9]*.[0-9]*.[0-9]*) NEW="$BUMP" ;;
  *) die "argumento inválido '$BUMP' (usá patch|minor|major|X.Y.Z)" ;;
esac
[[ "$NEW" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "versión inválida: $NEW"
git rev-parse -q --verify "refs/tags/v$NEW" >/dev/null && die "el tag v$NEW ya existe"

say "Release: v$CUR -> v$NEW (rama $WORK)"

# 2) Verificar que el frontend compila (no publicar código roto) ------
say "Verificando build del frontend (tsc)…"
( cd frontend && npx tsc -b ) || die "tsc falló — no publico una versión que no compila."

# 3) Bump + commit en la rama de trabajo ------------------------------
sed -i '0,/"version": *"[^"]*"/s//"version": "'"$NEW"'"/' "$PKG"
git add "$PKG"
git commit -q -m "chore(release): v$NEW"

# 4) Merge a main (fast-forward) + tag + push -------------------------
say "Merge a main + tag v$NEW…"
git checkout -q main
git merge --ff-only "$WORK" || die "no se pudo hacer fast-forward a main (main divergió). Resolvé y reintentá."
git tag -a "v$NEW" -m "v$NEW"
git push -q origin main
git push -q origin "v$NEW"
git checkout -q "$WORK"
git push -q origin "$WORK" || true   # mantener la rama de trabajo al día (no crítico)

# 5) Listo -------------------------------------------------------------
say "Publicado v$NEW"
echo "  tag:      https://github.com/cynchro/sistemaContable/releases/tag/v$NEW"
echo "  compare:  https://github.com/cynchro/sistemaContable/compare/v$CUR...v$NEW"
echo "  deploy:   el webhook de GitHub dispara el redeploy en el VPS automáticamente."
echo "  footer:   mostrará v$NEW cuando termine el deploy (~2-3 min)."
