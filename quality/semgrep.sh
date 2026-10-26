#!/usr/bin/env bash
# semgrep.sh — reglas propias de Convoca sobre los repos (F2).
#
# Uso: ./quality/semgrep.sh convoca-core convoca-members ...
# Salida: quality/semgrep/reports/<fecha>/<repo>.json (Semgrep JSON) + resumen por consola.
#
# Solo corre las reglas de convoca.yml: son las que entienden el dominio (prepare, esc_*,
# nonce, roles, SSRF sobre wp_remote_*). Las reglas de registro (p/php) se usan aparte como
# inventario, porque su volumen y sus falsos positivos en WordPress no sirven como puerta.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMG="docker.io/semgrep/semgrep@sha256:3db5c7af55eb1f512eac0abf0ab69a97b52c74b5e9a27314c221aee1a78d7ab2"
OUT="$ROOT/quality/semgrep/reports/$(date +%F)"
REPOS=("$@")
[ ${#REPOS[@]} -eq 0 ] && REPOS=(convoca-core convoca-members convoca-enroll convoca-gateway convoca-shifts \
  convoca-publisher convoca-assistant convoca-license-generator convoca-theme convoca-courses convoca-mu-plugins)
mkdir -p "$OUT"

for name in "${REPOS[@]}"; do
  repo="$HOME/repos/$name"
  [ -d "$repo" ] || { echo "SALTO $name"; continue; }
  echo "== $name"
  podman run --rm -v "$repo:/src:ro" -v "$ROOT/quality/semgrep:/rules:ro" -v "$OUT:/out" -w /src "$IMG" \
    semgrep scan --config=/rules/convoca.yml --metrics=off --json --output="/out/$name.json" \
    --exclude=vendor --exclude=node_modules --exclude=tests --exclude=build \
    --exclude=assets-wporg --exclude=e2e --exclude=dev-tools > "$OUT/$name.log" 2>&1
  rc=$?
  if [ $rc -gt 1 ]; then echo "   FALLO semgrep rc=$rc (ver $OUT/$name.log)"; continue; fi
  python3 - "$OUT/$name.json" "$name" <<'PY'
import json,sys,collections
d=json.load(open(sys.argv[1])); name=sys.argv[2]
c=collections.Counter(r['check_id'].split('.')[-1] for r in d.get('results',[]))
sev=collections.Counter(r['extra'].get('severity','') for r in d.get('results',[]))
print('   ', len(d.get('results',[])), 'hallazgos', dict(sev), dict(c))
PY
done
