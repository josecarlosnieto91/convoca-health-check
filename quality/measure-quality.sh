#!/usr/bin/env bash
# measure-quality.sh — línea base de calidad de un repo Convoca (F0).
#
# Uso:
#   ./quality/measure-quality.sh --out quality/baseline/2026-10-26 convoca-core convoca-members
#   ./quality/measure-quality.sh --out /tmp/base            # por defecto: los 11 con tooling
#
# Mide, por repo:
#   · LOC de PHP y nº de ficheros (sin vendor/tests/node_modules/build)
#   · complejidad ciclomática >10 / >15 y anidamiento >4 / >6 (PHPCS, variable propia)
#   · PHPStan al nivel del CI y al nivel 8 (misma config que el CI)
#   · suelo de cobertura (.coverage-floor) y nº de ficheros de test
#   · composer audit de dependencias de producción
#   · versión de cabecera, commit y ficheros sin confirmar
#
# No modifica ningún repo: solo escribe JSON en --out y usa el cache temporal de PHPStan.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/quality/baseline/$(date +%F)"
RULESET="$ROOT/quality/phpcs-metrics.xml"
REPOS=()

while [ $# -gt 0 ]; do
  case "$1" in
    --out) OUT="$2"; shift 2 ;;
    --ruleset) RULESET="$2"; shift 2 ;;
    -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
    *) REPOS+=("$1"); shift ;;
  esac
done

if [ ${#REPOS[@]} -eq 0 ]; then
  REPOS=(convoca-core convoca-enroll convoca-members convoca-gateway convoca-shifts \
         convoca-publisher convoca-assistant convoca-license-generator convoca-theme \
         convoca-courses convoca-mu-plugins)
fi

case "$OUT" in /*) ;; *) OUT="$(pwd)/$OUT" ;; esac
mkdir -p "$OUT"
command -v python3 >/dev/null || { echo "FALLO: falta python3" >&2; exit 1; }
[ -f "$RULESET" ] || { echo "FALLO: falta el ruleset $RULESET" >&2; exit 1; }

# Config de PHPStan: la del CI si se puede leer del workflow; si no, la primera que exista.
resolve_phpstan_config() {
  local repo="$1" cfg=""
  if [ -f "$repo/.github/workflows/ci.yml" ]; then
    cfg="$(grep -oE 'phpstan analyse[^#]*--configuration=[^[:space:]]+' "$repo/.github/workflows/ci.yml" \
      | head -1 | sed -E 's/.*--configuration=//' | tr -d '"'"'"'')"
  fi
  if [ -n "$cfg" ] && [ -f "$repo/$cfg" ]; then printf '%s\n' "$cfg"; return 0; fi
  local c
  for c in phpstan.neon phpstan.neon.dist phpstan.dist.neon dev-tools/phpstan.neon.dist dev-tools/phpstan.neon; do
    [ -f "$repo/$c" ] && { printf '%s\n' "$c"; return 0; }
  done
  return 1
}

header_version() {
  local repo="$1" name; name="$(basename "$repo")"
  if [ -f "$repo/style.css" ]; then
    grep -m1 -i '^Version:' "$repo/style.css" 2>/dev/null | sed 's/.*:[[:space:]]*//'
    return
  fi
  local main="$repo/$name.php"
  [ -f "$main" ] || main="$repo/${name#convoca-}.php"
  [ -f "$main" ] || main="$(find "$repo" -maxdepth 1 -name '*.php' ! -name 'uninstall.php' | head -1)"
  grep -m1 -iE '^[[:space:]]*\*[[:space:]]*Version:' "$main" 2>/dev/null | sed 's/.*Version:[[:space:]]*//'
  if [ -z "$(grep -m1 -iE '^[[:space:]]*\*[[:space:]]*Version:' "$main" 2>/dev/null)" ]; then
    grep -rh -m1 -iE '^[[:space:]]*\*[[:space:]]*Version:' "$repo"/*.php 2>/dev/null | head -1 | sed 's/.*Version:[[:space:]]*//'
  fi
}

for name in "${REPOS[@]}"; do
  repo="$HOME/repos/$name"
  [ -d "$repo" ] || { echo "SALTO  $name (no existe)"; continue; }
  echo "== $name"
  out="$OUT/$name.json"
  cfg="$(resolve_phpstan_config "$repo" || true)"
  level=""
  [ -n "$cfg" ] && level="$(grep -m1 -E '^[[:space:]]*level:' "$repo/$cfg" | sed -E 's/.*level:[[:space:]]*//' | tr -d '[:space:]')"
  commit="$(git -C "$repo" log -1 --format=%h 2>/dev/null || true)"
  dirty="$(git -C "$repo" status --porcelain 2>/dev/null | wc -l | tr -d ' ')"
  version="$(header_version "$repo")"
  floor="$(cat "$repo/.coverage-floor" 2>/dev/null | tr -d '[:space:]')"

  # LOC y ficheros
  read -r loc files < <(python3 - "$repo" <<'PY'
import os,sys
root=sys.argv[1]; skip={'vendor','node_modules','tests','build','assets-wporg','e2e'}
n=0; loc=0
for dp,dns,fns in os.walk(root):
    dns[:]=[d for d in dns if d not in skip and not d.startswith('.git')]
    for f in fns:
        if f.endswith('.php'):
            n+=1
            try:
                with open(os.path.join(dp,f),errors='ignore') as fh: loc+=sum(1 for _ in fh)
            except OSError: pass
print(loc,n)
PY
)
  tests="$(find "$repo/tests" -name '*Test.php' 2>/dev/null | wc -l | tr -d ' ')"

  # Complejidad (PHPCS). rc=1 significa "hay hallazgos": es normal.
  cc_json="$OUT/.cc-$name.json"
  cc_status="no_medido"
  if [ -x "$repo/vendor/bin/phpcs" ]; then
    ( cd "$repo" && vendor/bin/phpcs --standard="$RULESET" --report=json \
        --extensions=php --ignore=vendor,tests,build,node_modules,assets-wporg,dev-tools . \
        > "$cc_json" 2>"$OUT/.cc-$name.err" )
    rc=$?
    [ $rc -le 1 ] && [ -s "$cc_json" ] && cc_status="ok"
  fi

  # PHPStan: nivel actual y nivel 8, con la config del CI.
  ps_cur="no_medido"; ps_cur_err=""; ps8="no_medido"; ps8_top=""
  if [ -n "$cfg" ] && [ -x "$repo/vendor/bin/phpstan" ]; then
    cur_json="$OUT/.ps-$name.json"
    ( cd "$repo" && timeout 600 vendor/bin/phpstan analyse --configuration="$cfg" \
        --error-format=json --no-progress --memory-limit=4G > "$cur_json" 2>"$OUT/.ps-$name.err" )
    rc=$?
    if [ $rc -le 1 ] && [ -s "$cur_json" ]; then
      ps_cur_err="$(python3 -c "import json,sys;d=json.load(open(sys.argv[1]));print(d['totals'].get('file_errors',0)+d['totals'].get('errors',0))" "$cur_json" 2>/dev/null || echo '?')"
      ps_cur="ok"
    fi
    ps8_json="$OUT/.ps8-$name.json"
    ( cd "$repo" && timeout 900 vendor/bin/phpstan analyse --configuration="$cfg" --level=8 \
        --error-format=json --no-progress --memory-limit=4G > "$ps8_json" 2>"$OUT/.ps8-$name.err" )
    rc=$?
    if [ $rc -le 1 ] && [ -s "$ps8_json" ]; then
      mapfile -t ps8_out < <(python3 - "$ps8_json" <<'PY'
import json,sys,collections
d=json.load(open(sys.argv[1]))
tot=d['totals'].get('file_errors',0)+d['totals'].get('errors',0)
c=collections.Counter(m['message'].split('\n')[0][:160] for f in d.get('files',{}).values() for m in f.get('messages',[]))
print(tot)
print(' | '.join(f'{n}x {m}' for m,n in c.most_common(3)))
PY
)
      ps8="${ps8_out[0]:-?}"; ps8_top="${ps8_out[1]:-}"
    fi
  fi

  # composer audit (solo dependencias de producción)
  audit="no_medido"; audit_n=0
  if [ -f "$repo/composer.lock" ]; then
    audit_json="$OUT/.audit-$name.json"
    ( cd "$repo" && timeout 90 composer audit --no-dev --format=json --no-interaction > "$audit_json" 2>/dev/null )
    if [ -s "$audit_json" ]; then
      audit_n="$(python3 -c "
import json,sys
try:
    d=json.load(open(sys.argv[1]))
    adv=d.get('advisories') or []
    if isinstance(adv,dict): n=sum(len(v) for v in adv.values())
    else: n=len(adv)
    print(n)
except Exception: print('?')" "$audit_json" 2>/dev/null || echo '?')"
      audit="ok"
    fi
  fi

  python3 - "$out" "$name" "$version" "$commit" "$dirty" "$loc" "$files" "$tests" "$floor" \
    "$cfg" "$level" "$ps_cur" "$ps_cur_err" "$ps8" "$ps8_top" "$audit" "$audit_n" "$cc_status" "$cc_json" <<'PY'
import json,sys,collections,datetime
(o,name,version,commit,dirty,loc,files,tests,floor,cfg,level,ps_cur,ps_cur_err,ps8,ps8_top,audit,audit_n,cc_status,cc_json)=sys.argv[1:]
cc={'cc_over_10':None,'cc_over_15':None,'nesting_over_4':None,'nesting_over_6':None,'cc_files':None}
if cc_status=='ok':
    try:
        d=json.load(open(cc_json)); msgs=[m for f in d.get('files',{}).values() for m in f.get('messages',[])]
        cc={'cc_over_10':sum(1 for m in msgs if 'CyclomaticComplexity' in m.get('source','') and m.get('type')=='WARNING'),
            'cc_over_15':sum(1 for m in msgs if 'CyclomaticComplexity' in m.get('source','') and m.get('type')=='ERROR'),
            'cc_total_over_10':sum(1 for m in msgs if 'CyclomaticComplexity' in m.get('source','')),
            'nesting_over_4':sum(1 for m in msgs if 'NestingLevel' in m.get('source','') and m.get('type')=='WARNING'),
            'nesting_over_6':sum(1 for m in msgs if 'NestingLevel' in m.get('source','') and m.get('type')=='ERROR'),
            'cc_files':len(d.get('files',{}))}
    except Exception as e:
        cc['error']=str(e)
res={
 'repo':name,'measured_at':datetime.datetime.now().isoformat(timespec='seconds'),
 'version':version or None,'commit':commit or None,'dirty_files':int(dirty or 0),
 'loc_php':int(loc or 0),'php_files':int(files or 0),'test_files':int(tests or 0),
 'coverage_floor':float(floor) if floor.replace('.','',1).isdigit() else None,
 'phpstan_config':cfg or None,'phpstan_level':level or None,
 'phpstan_current_errors':None if ps_cur!='ok' else int(ps_cur_err) if str(ps_cur_err).isdigit() else ps_cur_err,
 'phpstan_level8_errors':None if ps8 in ('no_medido','?') else int(ps8) if str(ps8).isdigit() else ps8,
 'phpstan_level8_top':ps8_top or None,
 'composer_audit_prod':None if audit!='ok' else int(audit_n) if str(audit_n).isdigit() else audit_n,
 'complexity':cc,
}
with open(o,'w') as fh:
    json.dump(res,fh,indent=2,ensure_ascii=False); fh.write('\n')
print(f"   LOC={loc} ficheros={files} tests={tests} phpstan={ps_cur}/{ps_cur_err} nivel8={ps8} cc>15={cc['cc_over_15']} (>10={cc['cc_over_10']}) audit={audit_n}")
PY
  rm -f "$OUT/.cc-$name.json" "$OUT/.ps-$name.json" "$OUT/.ps8-$name.json" "$OUT/.audit-$name.json" \
        "$OUT/.cc-$name.err" "$OUT/.ps-$name.err" "$OUT/.ps8-$name.err" 2>/dev/null
done

# Consolidado + tabla
python3 - "$OUT" <<'PY'
import json,glob,os,sys
out=sys.argv[1]
rows=[json.load(open(p)) for p in sorted(glob.glob(os.path.join(out,'*.json'))) if os.path.basename(p)!='summary.json']
rows=[r for r in rows if isinstance(r,dict)]
with open(os.path.join(out,'summary.json'),'w') as fh:
    json.dump(rows,fh,indent=2,ensure_ascii=False); fh.write('\n')
lines=['| Repo | LOC | Tests | Cobertura | PHPStan | Nivel 8 | CC>15 | CC 10-15 | Nidos>6 | Audit | Versión / commit |',
       '|---|---|---|---|---|---|---|---|---|---|---|']
for r in rows:
    cc=r.get('complexity') or {}
    lines.append('| {repo} | {loc} | {t} | {cov} | {lvl} ({e}) | {p8} | {c15} | {c10} | {n6} | {aud} | {ver} / {commit} |'.format(
        repo=r['repo'],loc=r['loc_php'],t=r['test_files'],
        cov=(f"{r['coverage_floor']:.1f} %" if r.get('coverage_floor') is not None else '—'),
        lvl=r.get('phpstan_level') or '—',e=r.get('phpstan_current_errors'),
        p8=r.get('phpstan_level8_errors'),c15=cc.get('cc_over_15'),c10=cc.get('cc_over_10'),
        n6=cc.get('nesting_over_6'),aud=r.get('composer_audit_prod'),ver=r.get('version') or '—',commit=r.get('commit') or '—'))
open(os.path.join(out,'RESUMEN.md'),'w').write(
 '# Línea base de calidad — '+os.path.basename(out)+'\n\nGenerado por `quality/measure-quality.sh`.\n\n'+'\n'.join(lines)+'\n\n'
 '`CC>15` son funciones con ciclomática >15; `CC 10-15`, entre 10 y 15. `Nidos>6` es anidamiento.\n'
 '`Nivel 8` es PHPStan con la misma config del CI y `--level=8`.\n'
 '`Cobertura` es el **suelo** declarado en `.coverage-floor`, no una medición.\n')
print('RESUMEN:',os.path.join(out,'RESUMEN.md'))
PY
