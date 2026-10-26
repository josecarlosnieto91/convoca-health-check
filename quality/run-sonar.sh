#!/usr/bin/env bash
# run-sonar.sh — analiza repos Convoca con el SonarQube local y guarda el parte.
#
# Uso: ./quality/run-sonar.sh convoca-core convoca-members ...
# Requiere: SonarQube arriba en 127.0.0.1:9000 y token en `pass show convoca/sonar-token`.
#
# El proyecto se llama igual que el repo (convoca-core). El scanner no escribe en el repo:
# el directorio de trabajo va a /tmp dentro del contenedor y el repo se monta en solo lectura.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMG="docker.io/sonarsource/sonar-scanner-cli@sha256:47956b669bf486bdad919e1cbd0533223515aa0b6d1d31763e5a3754942df6d7"
HOST="http://127.0.0.1:9000"
OUT="$ROOT/quality/sonar/reports/$(date +%F)"
REPOS=("$@")
[ ${#REPOS[@]} -eq 0 ] && REPOS=(convoca-core convoca-members convoca-enroll convoca-gateway convoca-shifts \
  convoca-publisher convoca-assistant convoca-license-generator convoca-theme convoca-courses convoca-mu-plugins)

TOKEN="$(pass show convoca/sonar-token 2>/dev/null | head -1)"
[ -z "$TOKEN" ] && { echo "FALLO: falta el token en pass (convoca/sonar-token)" >&2; exit 2; }
curl -sf -u "$TOKEN:" "$HOST/api/system/status" >/dev/null || { echo "FALLO: Sonar no responde en $HOST" >&2; exit 2; }
mkdir -p "$OUT"

for name in "${REPOS[@]}"; do
  repo="$HOME/repos/$name"
  [ -d "$repo" ] || { echo "SALTO $name (no existe)"; continue; }
  extra=()
  if [ -f "$repo/build/coverage.xml" ]; then
    extra+=("-Dsonar.php.coverage.reportPaths=build/coverage.xml")
  else
    echo "   aviso: $name sin build/coverage.xml (se analiza sin cobertura)"
  fi
  echo "== $name"
  # El repo se monta en su MISMA ruta absoluta: así las rutas del clover (absolutas) casan
  # con los ficheros que ve Sonar y la cobertura no se queda a medias.
  podman run --rm --network host -v "$repo:$repo:ro" -w "$repo" \
    -e SONAR_HOST_URL="$HOST" -e SONAR_TOKEN="$TOKEN" "$IMG" \
    -Dsonar.projectKey="$name" -Dsonar.projectName="$name" \
    -Dsonar.projectBaseDir="$repo" \
    -Dsonar.working.directory=/tmp/scannerwork \
    -Dsonar.sources=. -Dsonar.sourceEncoding=UTF-8 -Dsonar.scm.disabled=true \
    -Dsonar.exclusions="vendor/**,tests/**,node_modules/**,build/**,assets-wporg/**,e2e/**,dev-tools/**" \
    "${extra[@]}" > "$OUT/$name.scanner.log" 2>&1
  rc=$?
  if [ $rc -ne 0 ]; then echo "   FALLO scanner rc=$rc (ver $OUT/$name.scanner.log)"; continue; fi

  # El análisis se procesa en segundo plano: esperar a que haya medidas antes de leerlas.
  for _ in $(seq 1 30); do
    curl -s -u "$TOKEN:" "$HOST/api/measures/component?component=$name&metricKeys=ncloc" > "$OUT/$name.ncloc.json"
    if python3 -c "import json,sys;d=json.load(open(sys.argv[1]));sys.exit(0 if d.get('component',{}).get('measures') else 1)" "$OUT/$name.ncloc.json" 2>/dev/null; then break; fi
    sleep 2
  done
  curl -s -u "$TOKEN:" "$HOST/api/measures/component?component=$name&metricKeys=bugs,vulnerabilities,code_smells,complexity,cognitive_complexity,duplicated_lines_density,sqale_index,reliability_rating,security_rating,sqale_rating,coverage,ncloc,security_hotspots" \
    > "$OUT/$name.measures.json"
  curl -s -u "$TOKEN:" "$HOST/api/qualitygates/project_status?projectKey=$name" > "$OUT/$name.gate.json"
  python3 - "$OUT" "$name" <<'PY'
import json,sys,os
out,name=sys.argv[1],sys.argv[2]
m=json.load(open(os.path.join(out,name+'.measures.json')))
g=json.load(open(os.path.join(out,name+'.gate.json')))
vals={x['metric']:x.get('value') for x in m.get('component',{}).get('measures',[])}
print('   ', ' '.join(f"{k}={v}" for k,v in vals.items() if k in ('bugs','vulnerabilities','code_smells','complexity','cognitive_complexity','coverage','ncloc')))
print('    gate:', g.get('projectStatus',{}).get('status'))
PY
done
