#!/usr/bin/env bash
# =============================================================================
# check-api-freeze.sh — Verifica que la API pública no ha cambiado
#
# Compara el inventario actual del código contra el baseline congelado
# (api/api-v3.0.json). Si un hook/endpoint/shortcode se añadió, renombró o
# eliminó SIN actualizar el baseline, falla.
#
# Uso:
#   bash check-api-freeze.sh            # usa workspace local
#   API_WS=/ruta/workspace bash check-api-freeze.sh
#
# Exit 0 = API estable. Exit 1 = hay cambios sin congelar (revisar y
# actualizar api/api-v3.0.json SOLO si el cambio es intencionado).
# =============================================================================
set -euo pipefail

# Directorio del workspace (donde están los repos convoca-*)
API_WS="${API_WS:-$HOME/.openclaw/workspace}"
# El extractor usa WS = workspace raíz; ajustar a API_WS
export WS="$API_WS"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FROZEN="$SCRIPT_DIR/api/api-v3.0.json"

if [[ ! -f "$FROZEN" ]]; then
    echo "ERROR: baseline congelado no encontrado: $FROZEN" >&2
    exit 1
fi

# 1. Regenerar inventario actual
TMP_INV="/tmp/api-inventory-current.json"
python3 "$SCRIPT_DIR/api-extract.py" >/dev/null 2>&1 && cp /tmp/api-inventory.json "$TMP_INV"

# 2. Construir firmas actuales en el mismo formato que el baseline
python3 - "$TMP_INV" << 'PYEOF'
import json, sys
inv = json.load(open(sys.argv[1]))
current = {
    repo: {
        'hooks': sorted(data['hooks'].keys()),
        'rest': sorted([f"{e['namespace']} {e['route']} {e['methods']}" for e in data['rest']]),
        'shortcodes': data['shortcodes'],
    }
    for repo, data in inv.items()
}
json.dump(current, open('/tmp/api-current-sigs.json', 'w'), ensure_ascii=False, indent=2)
PYEOF

# 3. Comparar
CHANGES=$(python3 - << 'PYEOF'
import json
frozen = json.load(open("/home/josecnr91/.openclaw/workspace/convoca-health-check/api/api-v3.0.json"))
current = json.load(open("/tmp/api-current-sigs.json"))
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
print("\n".join(diffs))
PYEOF
)

if [[ -n "$CHANGES" ]]; then
    echo "❌ LA API PÚBLICA HA CAMBIADO — baseline v3.0 desactualizado:"
    echo "$CHANGES"
    echo ""
    echo "Si el cambio es INTENCIONADO: actualizar api/api-v3.0.json"
    echo "Si es accidental: revertir el cambio de código."
    exit 1
fi

echo "✅ API v3.0 congelada: sin cambios (127 hooks + 51 REST + 22 shortcodes)"
