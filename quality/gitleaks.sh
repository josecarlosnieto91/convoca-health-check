#!/usr/bin/env bash
# gitleaks.sh — secretos en árbol e HISTORIAL de cada repo, con la allowlist justificada.
# Uso: ./quality/gitleaks.sh [repo...]
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BIN="$HOME/.local/bin/gitleaks"
OUT="$ROOT/quality/gitleaks/reports/$(date +%F)"
REPOS=("$@")
[ ${#REPOS[@]} -eq 0 ] && REPOS=(convoca-core convoca-members convoca-enroll convoca-gateway convoca-shifts \
  convoca-publisher convoca-assistant convoca-license-generator convoca-theme convoca-courses convoca-mu-plugins)
[ -x "$BIN" ] || { echo "FALLO: falta gitleaks (quality/install-scanners.sh)"; exit 2; }
mkdir -p "$OUT"
total=0
for name in "${REPOS[@]}"; do
  repo="$HOME/repos/$name"; [ -d "$repo" ] || continue
  "$BIN" git "$repo" --no-banner --redact --config "$ROOT/quality/gitleaks.toml" \
    --report-format json --report-path "$OUT/$name.json" --log-opts="--all" > "$OUT/$name.log" 2>&1
  rc=$?
  n=$(python3 -c "import json;print(len(json.load(open('$OUT/$name.json'))))" 2>/dev/null || echo '?')
  echo "$name leaks=$n rc=$rc"
  total=$((total + ${n:-0}))
done
echo "TOTAL=$total"
