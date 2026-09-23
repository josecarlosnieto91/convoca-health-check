#!/usr/bin/env bash
# =============================================================================
# check-api-freeze.sh — Verifica que la API pública no ha cambiado
#
# Compara el inventario actual del código contra el baseline congelado
# (api/api-v3.0.json). Si un hook/endpoint/shortcode se añadió, renombró o
# eliminó SIN actualizar el baseline, falla.
#
# Uso:
#   bash check-api-freeze.sh            # usa ~/repos
#   API_WS=/ruta/workspace bash check-api-freeze.sh
#
# Exit 0 = API estable. Exit 1 = hay cambios sin congelar (revisar y
# actualizar api/api-v3.0.json SOLO si el cambio es intencionado).
#
# NOTA: no silenciar la salida del extractor ni el exit code de este script
# (un `| tail` en el CI devuelve el de tail y el job pasa siempre sin mirar
# nada). Errores del extractor = fallo explícito.
# =============================================================================
set -euo pipefail

# Directorio del workspace (donde están los repos convoca-*)
API_WS="${API_WS:-$HOME/repos}"
# El extractor usa WS = workspace raíz; ajustar a API_WS
export WS="$API_WS"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FROZEN="$SCRIPT_DIR/api/api-v3.0.json"
EXTRACTOR="$SCRIPT_DIR/scripts/api-extract.py"

if [[ ! -f "$FROZEN" ]]; then
    echo "ERROR: baseline congelado no encontrado: $FROZEN" >&2
    exit 1
fi

if [[ ! -f "$EXTRACTOR" ]]; then
    echo "ERROR: extractor no encontrado: $EXTRACTOR" >&2
    exit 1
fi

if [[ ! -d "$API_WS" ]]; then
    echo "ERROR: workspace no encontrado: $API_WS" >&2
    exit 1
fi

# 1. Regenerar inventario actual (sin silenciar errores)
TMP_INV="/tmp/api-inventory-current.json"
if ! python3 "$EXTRACTOR" >/dev/null; then
    echo "ERROR: el extractor de API falló ($EXTRACTOR)" >&2
    exit 1
fi
if [[ ! -f /tmp/api-inventory.json ]]; then
    echo "ERROR: el extractor no generó /tmp/api-inventory.json" >&2
    exit 1
fi
cp /tmp/api-inventory.json "$TMP_INV"

# 2. Construir firmas actuales en el mismo formato que el baseline y comparar
python3 - "$TMP_INV" "$FROZEN" << 'PYEOF'
import json, sys

current = {
    repo: {
        'hooks': sorted(data['hooks'].keys()),
        'rest': sorted([f"{e['namespace']} {e['route']} {e['methods']}" for e in data['rest']]),
        'shortcodes': data['shortcodes'],
    }
    for repo, data in json.load(open(sys.argv[1])).items()
}
frozen = json.load(open(sys.argv[2]))

diffs = []
for repo in sorted(set(frozen) | set(current)):
    f = frozen.get(repo, {})
    c = current.get(repo, {})
    for kind in ("hooks", "rest", "shortcodes"):
        fset = set(f.get(kind, []))
        cset = set(c.get(kind, []))
        for added in sorted(cset - fset):
            diffs.append(f"+ {kind} en {repo}: {added}")
        for removed in sorted(fset - cset):
            diffs.append(f"- {kind} en {repo}: {removed}")

if diffs:
    print("❌ LA API PÚBLICA HA CAMBIADO — baseline v3.0 desactualizado:")
    print("\n".join(diffs))
    print("")
    print("Si el cambio es INTENCIONADO: actualizar api/api-v3.0.json")
    print("Si es accidental: revertir el cambio de código.")
    sys.exit(1)

total_h = sum(len(v.get("hooks", [])) for v in current.values())
total_r = sum(len(v.get("rest", [])) for v in current.values())
total_s = sum(len(v.get("shortcodes", [])) for v in current.values())
print(f"✅ API v3.0 congelada: sin cambios ({total_h} hooks + {total_r} REST + {total_s} shortcodes)")
PYEOF
