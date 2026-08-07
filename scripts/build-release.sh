#!/usr/bin/env bash
# =============================================================================
# build-release.sh — Empaqueta un plugin/theme Convoca para WordPress.org
#
# Uso:
#   bash build-release.sh <dir-plugin> [dir-salida]
#
# Qué hace:
#   1. Copia el árbol a un directorio temporal limpio
#   2. Elimina vendor/ y reinstala con --no-dev --optimize-autoloader
#   3. Excluye tests/, docs/, .github/, configs de QA, caches y residuos
#   4. Genera el ZIP
#   5. VERIFICA que el ZIP es < 10 MB (límite WordPress.org)
#   6. Imprime el tamaño final
#
# Exit code 0 = ZIP válido. Exit code 1 = ZIP supera 10 MB o error.
# =============================================================================
set -euo pipefail

SRC_DIR="${1:-}"
OUT_DIR="${2:-dist}"
LIMIT_MB=10
LIMIT_BYTES=$((LIMIT_MB * 1024 * 1024))

if [[ -z "$SRC_DIR" || ! -d "$SRC_DIR" ]]; then
    echo "ERROR: primer argumento debe ser el directorio del plugin" >&2
    exit 1
fi

NAME="$(basename "$SRC_DIR")"
# Ruta absoluta de salida (se usa tras cd al temporal)
ORIG_CWD="$(pwd)"
mkdir -p "$OUT_DIR"
OUT_ABS="$(cd "$OUT_DIR" && pwd)"

# ── 1. Copia a directorio temporal ─────────────────────────────────────
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
cp -r "$SRC_DIR" "$WORK/$NAME"
cd "$WORK/$NAME"

# ── 2. Vendor de producción (--no-dev) ──────────────────────────────────
rm -rf vendor
if [[ -f composer.json ]]; then
    # --ignore-platform-req=ext-iconv: dompdf requiere iconv en build-time,
    # el servidor de producción lo tiene. No es una dependencia del plugin.
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --ignore-platform-req=ext-iconv \
        --ignore-platform-req=ext-gd \
        >/dev/null 2>&1 \
        || { echo "ERROR: composer install --no-dev falló" >&2; exit 1; }
fi

# ── 3. Excluir desarrollo, QA y residuos ────────────────────────────────
rm -rf \
    tests \
    docs \
    .github \
    .git \
    node_modules \
    dev-tools \
    assets-wporg \
    screenshots

rm -f \
    *.md \
    CHANGELOG* \
    CONTRIBUTING* \
    CODE_OF_CONDUCT* \
    SECURITY* \
    SUPPORT* \
    LICENSE \
    composer.lock \
    phpstan.neon* \
    phpstan-baseline.neon* \
    phpstan-bootstrap.php \
    phpunit.xml* \
    phpunit-unit.xml* \
    .phpcs.xml* \
    .phpunit.result.cache \
    .phpunit.cache \
    package.json \
    package-lock.json \
    playwright.config.* \
    wp-tests-config.php \
    .gitignore \
    .editorconfig

# Caches y temporales residuales
find . -name "*.cache" -type f -delete 2>/dev/null || true
find . -name "*.result.cache" -type f -delete 2>/dev/null || true
find . -name ".DS_Store" -type f -delete 2>/dev/null || true

# ── 4. Generar ZIP ──────────────────────────────────────────────────────
cd "$WORK"
ZIP_PATH="$OUT_ABS/$NAME.zip"
rm -f "$ZIP_PATH"
zip -rq "$ZIP_PATH" "$NAME"

# ── 5. Verificar tamaño < 10 MB ─────────────────────────────────────────
SIZE_BYTES="$(stat -c%s "$ZIP_PATH")"
SIZE_MB="$(awk "BEGIN{printf \"%.2f\", $SIZE_BYTES / 1048576}")"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  📦 $NAME.zip"
echo "  Tamaño: ${SIZE_MB} MB ($SIZE_BYTES bytes)"
echo "  Límite WordPress.org: $LIMIT_MB MB"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if (( SIZE_BYTES > LIMIT_BYTES )); then
    echo "❌ ERROR: $NAME.zip supera el límite de $LIMIT_MB MB" >&2
    exit 1
fi

echo "✅ ZIP válido para WordPress.org"
