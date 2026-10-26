#!/usr/bin/env bash
# metrics.sh — PHPMD y PHPmetrics por repo, con resumen en JSON (F2).
#
# Uso: ./quality/metrics.sh convoca-core convoca-members ...
# Salida: quality/metrics/reports/<fecha>/<repo>.json + tabla por consola.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHPMD="$HOME/.local/opt/quality/phpmd/3.0.0/phpmd.phar"
PHPMETRICS="$HOME/.local/opt/quality/phpmetrics/2.11.0/phpmetrics.phar"
OUT="$ROOT/quality/metrics/reports/$(date +%F)"
REPOS=("$@")
[ ${#REPOS[@]} -eq 0 ] && REPOS=(convoca-core convoca-members convoca-enroll convoca-gateway convoca-shifts \
  convoca-publisher convoca-assistant convoca-license-generator convoca-theme convoca-courses convoca-mu-plugins)
mkdir -p "$OUT"
[ -f "$PHPMD" ] || { echo "FALLO: falta phpmd"; exit 2; }
[ -f "$PHPMETRICS" ] || { echo "FALLO: falta phpmetrics"; exit 2; }

for name in "${REPOS[@]}"; do
  repo="$HOME/repos/$name"; [ -d "$repo" ] || { echo "SALTO $name"; continue; }
  echo "== $name"
  php "$PHPMD" analyze --format=json --ruleset="$ROOT/quality/phpmd.xml" \
    --exclude='*/vendor/*' --exclude='*/tests/*' --exclude='*/node_modules/*' \
    --exclude='*/build/*' --exclude='*/assets-wporg/*' --exclude='*/e2e/*' \
    --no-progress "$repo" > "$OUT/$name.phpmd.json" 2>"$OUT/$name.phpmd.err"
  php "$PHPMETRICS" --report-json="$OUT/$name.phpmetrics.raw.json" \
    --exclude=vendor,tests,node_modules,build,assets-wporg,e2e,dev-tools "$repo" \
    > "$OUT/$name.phpmetrics.log" 2>&1
  python3 - "$OUT" "$name" <<'PY'
import json,sys,os,statistics
out,name=sys.argv[1],sys.argv[2]
# PHPMD
vp=[]
try:
    d=json.load(open(os.path.join(out,name+'.phpmd.json')))
    for f in d.get('files',[]):
        for v in f.get('violations',[]):
            vp.append((v.get('rule'), os.path.relpath(f.get('file',''), os.path.expanduser('~/repos/'+name)), v.get('beginLine'), v.get('description','')[:120]))
except Exception as e:
    vp=[('ERROR_LECTURA',str(e),0,'')]
# PHPmetrics (clave = clase; valores escalares)
classes=[]; funcs=[]
try:
    pm=json.load(open(os.path.join(out,name+'.phpmetrics.raw.json')))
    for k,v in pm.items():
        if not isinstance(v,dict) or 'name' not in v: continue
        classes.append({'name':v.get('name'),'mi':v.get('mi'),'lcom':v.get('lcom'),'ccn':v.get('ccn'),'ccnMax':v.get('ccnMethodMax'),'loc':v.get('loc'),'wmc':v.get('wmc')})
        for m in v.get('methods',[]) or []:
            if isinstance(m,dict) and m.get('ccn') is not None:
                funcs.append({'class':v.get('name'),'method':m.get('name'),'ccn':m.get('ccn')})
except Exception as e:
    classes=[{'error':str(e)}]
mis=[c['mi'] for c in classes if isinstance(c.get('mi'),(int,float))]
lcoms=[c['lcom'] for c in classes if isinstance(c.get('lcom'),(int,float))]
ccns=[c['ccnMax'] for c in classes if isinstance(c.get('ccnMax'),(int,float))]
res={
 'repo':name,
 'phpmd_total':len(vp),
 'phpmd_by_rule':{r:sum(1 for x in vp if x[0]==r) for r in sorted({x[0] for x in vp})},
 'phpmd_top':vp[:12],
 'classes':len(classes),
 'mi_mean':round(statistics.mean(mis),1) if mis else None,
 'mi_min':min(mis) if mis else None,
 'mi_below_65':sum(1 for x in mis if x<65),
 'lcom_gt_2':sum(1 for x in lcoms if x>2),
 'ccn_max':max(ccns) if ccns else None,
 'ccn_gt_10':sum(1 for x in ccns if x>10),
 'ccn_gt_15':sum(1 for x in ccns if x>15),
 'funcs_ccn_gt_15':sum(1 for f in funcs if f['ccn']>15),
 'funcs_gt_15_top':sorted([f for f in funcs if f['ccn']>15],key=lambda f:-f['ccn'])[:10],
}
json.dump(res,open(os.path.join(out,name+'.json'),'w'),indent=2,ensure_ascii=False)
open(os.path.join(out,name+'.json'),'a').write('\n')
print(f"   phpmd={res['phpmd_total']} {res['phpmd_by_rule']} mi={res['mi_mean']} min={res['mi_min']} lcom>2={res['lcom_gt_2']} ccnMax={res['ccn_max']} funcs>15={res['funcs_ccn_gt_15']}")
PY
  rm -f "$OUT/$name.phpmetrics.raw.json"
done
