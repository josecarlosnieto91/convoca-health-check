#!/usr/bin/env bash
# =============================================================================
# svn-deploy.sh — Despliega un plugin Convoca a WordPress.org (SVN)
#
# Requisitos previos:
#   1. El plugin DEBE tener su repo SVN en wp.org (registrado por el admin).
#   2. Credenciales SVN disponibles (SVN_USER / SVN_PASS o ~/.svn-auth).
#   3. El ZIP release debe existir (generado con build-release.sh).
#
# Uso:
#   SVN_USER=xxx SVN_PASS=yyy bash svn-deploy.sh <slug> <zip> [version]
#
# Pasos:
#   1. Checkout del repo SVN (trunk + assets + tag/<version>)
#   2. Extrae el ZIP en trunk/ (limpia antes)
#   3. Copia assets-wporg/ → assets/ del SVN
#   4. Copia trunk → tag/<version>
#   5. Commit (trunk + assets + tag)
# =============================================================================
set -euo pipefail

SLUG="${1:-}"
ZIP="${2:-}"
VERSION="${3:-}"

if [[ -z "$SLUG" || -z "$ZIP" || ! -f "$ZIP" ]]; then
    echo "Uso: bash svn-deploy.sh <slug> <zip> [version]" >&2
    exit 1
fi

if [[ -z "$VERSION" ]]; then
    VERSION=$(unzip -p "$ZIP" "*/readme.txt" 2>/dev/null | grep -m1 "^Stable tag:" | awk '{print $3}')
fi
if [[ -z "$VERSION" ]]; then
    echo "ERROR: no se pudo determinar la versión (usa el 3er argumento)" >&2
    exit 1
fi

SVN_USER="${SVN_USER:?SVN_USER no definido}"
SVN_PASS="${SVN_PASS:?SVN_PASS no definido}"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

echo "=== SVN DEPLOY: ${SLUG} v${VERSION} ==="

# 1. Checkout (trunk + assets)
svn co --username "$SVN_USER" --password "$SVN_PASS" --non-interactive \
    "$SVN_URL/trunk" "$WORK/trunk" >/dev/null 2>&1
svn co --username "$SVN_USER" --password "$SVN_PASS" --non-interactive \
    "$SVN_URL/assets" "$WORK/assets" >/dev/null 2>&1

# 2. Extraer ZIP en trunk (limpiar antes)
echo "-- trunk --"
rm -rf "$WORK/trunk"/*
unzip -q "$ZIP" -d "$WORK/trunk-extract"
# El ZIP contiene una carpeta raíz <slug>/ → mover contenido a trunk/
if [[ -d "$WORK/trunk-extract/$SLUG" ]]; then
    mv "$WORK/trunk-extract/$SLUG"/* "$WORK/trunk/"
    mv "$WORK/trunk-extract/$SLUG"/.[!.]* "$WORK/trunk/" 2>/dev/null || true
else
    mv "$WORK/trunk-extract"/* "$WORK/trunk/"
fi
rm -rf "$WORK/trunk-extract"

# 3. Assets (banner/icon) — desde el repo local si existe
echo "-- assets --"
if [[ -d "assets-wporg" ]]; then
    rm -rf "$WORK/assets"/*
    cp assets-wporg/* "$WORK/assets/"
elif [[ -d "${ZIP%/*}/assets-wporg" ]]; then
    rm -rf "$WORK/assets"/*
    cp "${ZIP%/*}/assets-wporg"/* "$WORK/assets/"
fi

# 4. Tag
echo "-- tag ${VERSION} --"
svn cp --username "$SVN_USER" --password "$SVN_PASS" --non-interactive \
    "$SVN_URL/trunk" "$SVN_URL/tags/${VERSION}" \
    -m "Release ${VERSION}" >/dev/null 2>&1 || \
    echo "  (el tag ${VERSION} ya existe o no se pudo crear)"

# 5. Commit
echo "-- commit --"
cd "$WORK"
svn add --force trunk assets >/dev/null 2>&1 || true
svn delete --force --keep-local trunk/* >/dev/null 2>&1 || true
svn commit trunk assets -m "Release ${VERSION}" \
    --username "$SVN_USER" --password "$SVN_PASS" --non-interactive

echo ""
echo "✅ Deploy completado: https://wordpress.org/plugins/${SLUG}/"
echo "   (la aprobación del plugin en wp.org puede tardar horas)"
