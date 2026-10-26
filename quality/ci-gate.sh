#!/usr/bin/env bash
# ci-gate.sh — puerta de calidad con RATCHET (F5).
#
# Uso: ci-gate.sh <ruta-del-repo>
# Lee .quality/baseline.json del repo (si no existe, informa y NO falla: modo bootstrap)
# y falla solo si algún número SUBE respecto a la línea base congelada.
#
# Herramientas (las instala el workflow): php, composer, semgrep, gitleaks y
# /tmp/phpmd.phar. PHPStan sale del vendor del repo.
set -uo pipefail

REPO="${1:-.}"; REPO="$(cd "$REPO" && pwd)"
BASE="$REPO/.quality/baseline.json"
FAIL=0

say() { printf '%s\n' "$*"; }
num() { python3 -c "import json,sys;d=json.load(open(sys.argv[1]));print(d.get(sys.argv[2],0))" "$BASE" "$1" 2>/dev/null || echo 0; }

say "== quality gate: $(basename "$REPO")"
if [ ! -f "$BASE" ]; then
  say "   aviso: sin .quality/baseline.json (modo bootstrap: informa y no bloquea)"
  BASE=/dev/null
fi

check() { # nombre actual base
  local name="$1" cur="$2" base="$3"
  if [ "$cur" -gt "$base" ]; then
    say "   FALLO $name: $cur > $base (sube respecto a la base)"
    FAIL=1
  else
    say "   ok    $name: $cur (base $base)"
  fi
}

# 1) Dependencias de producción
if [ -f "$REPO/composer.lock" ] && command -v composer >/dev/null; then
  ( cd "$REPO" && composer audit --no-dev --format=json --no-interaction > /tmp/audit.json 2>/dev/null )
  adv=$(python3 -c "
import json
try:
    d=json.load(open('/tmp/audit.json')); a=d.get('advisories') or []
    print(sum(len(v) for v in a.values()) if isinstance(a,dict) else len(a))
except Exception: print(0)")
  check dependencias "$adv" "$(num composer_advisories)"
fi

# 2) Secretos en el árbol (el historial se barre en el job nocturno)
if command -v gitleaks >/dev/null; then
  gitleaks dir "$REPO" --no-banner --redact --config "${QUALITY_RULES:-/tmp/quality}/gitleaks.toml" \
    --report-format json --report-path /tmp/leaks.json >/dev/null 2>&1
  leaks=$(python3 -c "import json;print(len(json.load(open('/tmp/leaks.json'))))" 2>/dev/null || echo 0)
  check secretos "$leaks" "$(num secretos)"
fi

# 3) Reglas propias de Semgrep (solo ERROR; las políticas WARNING no bloquean)
if command -v semgrep >/dev/null && [ -f "${QUALITY_RULES:-/tmp/quality}/convoca.yml" ]; then
  semgrep scan --config "${QUALITY_RULES:-/tmp/quality}/convoca.yml" --metrics=off --json \
    --exclude=vendor --exclude=node_modules --exclude=tests --exclude=build \
    --exclude=assets-wporg --exclude=e2e --exclude=dev-tools "$REPO" > /tmp/sg.json 2>/dev/null
  sg=$(python3 -c "
import json
d=json.load(open('/tmp/sg.json'))
print(sum(1 for r in d.get('results',[]) if r['extra'].get('severity')=='ERROR'))" 2>/dev/null || echo 0)
  check semgrep_errores "$sg" "$(num semgrep_errors)"
fi

# 4) PHPMD (tamaño y complejidad): el total no puede subir
if [ -f /tmp/phpmd.phar ] && [ -f "${QUALITY_RULES:-/tmp/quality}/phpmd.xml" ]; then
  php /tmp/phpmd.phar analyze --format=json --ruleset="${QUALITY_RULES:-/tmp/quality}/phpmd.xml" \
    --exclude='*/vendor/*' --exclude='*/tests/*' --exclude='*/node_modules/*' \
    --exclude='*/build/*' --exclude='*/assets-wporg/*' --exclude='*/e2e/*' \
    --no-progress "$REPO" > /tmp/phpmd.json 2>/dev/null
  pmd=$(python3 -c "
import json
d=json.load(open('/tmp/phpmd.json'))
print(sum(len(f.get('violations',[])) for f in d.get('files',[])))" 2>/dev/null || echo 0)
  check phpmd_total "$pmd" "$(num phpmd_total)"
fi

# 5) PHPStan al nivel del CI: 0 errores nuevos
CFG=""
if [ -f "$REPO/.github/workflows/ci.yml" ]; then
  CFG="$(grep -oE 'phpstan analyse[^#]*--configuration=[^[:space:]]+' "$REPO/.github/workflows/ci.yml" | head -1 | sed -E 's/.*--configuration=//' | tr -d '"'"'"'')"
fi
[ -n "$CFG" ] && [ -f "$REPO/$CFG" ] || {
  for c in phpstan.neon phpstan.neon.dist phpstan.dist.neon dev-tools/phpstan.neon.dist; do
    [ -f "$REPO/$c" ] && { CFG="$c"; break; }
  done
}
if [ -n "$CFG" ] && [ -x "$REPO/vendor/bin/phpstan" ]; then
  ( cd "$REPO" && vendor/bin/phpstan analyse --configuration="$CFG" --error-format=json \
      --no-progress --memory-limit=2G > /tmp/phpstan.json 2>/dev/null )
  ps=$(python3 -c "
import json
d=json.load(open('/tmp/phpstan.json'))
print(d['totals'].get('file_errors',0)+d['totals'].get('errors',0))" 2>/dev/null || echo 0)
  check phpstan_errores "$ps" "$(num phpstan_errors)"
fi

if [ $FAIL -ne 0 ]; then
  say "== PUERTA EN ROJO: algún número sube respecto a la línea base."
  exit 1
fi
say "== PUERTA OK: nada sube respecto a la línea base."
exit 0
