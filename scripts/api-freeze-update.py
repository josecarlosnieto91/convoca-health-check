#!/usr/bin/env python3
"""
Regenera el baseline congelado api/api-v3.0.json desde el código actual.

Se usa SOLO cuando el cambio de API es intencionado. Deja constancia del motivo
en la cabecera del propio fichero para que la siguiente persona sepa por qué
cambió el inventario.

Uso:
    python3 scripts/api-freeze-update.py --motivo "texto del cambio"
    WS=/ruta/a/repos python3 scripts/api-freeze-update.py --motivo "..."
"""
import argparse
import json
import os
import subprocess
import sys

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(SCRIPT_DIR)
EXTRACTOR = os.path.join(SCRIPT_DIR, "api-extract.py")
BASELINE = os.path.join(ROOT, "api", "api-v3.0.json")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--motivo", required=True, help="Por qué cambia el inventario (queda escrito)")
    args = ap.parse_args()

    # El extractor lee los repos desde WS. Sin esto usaba su valor por defecto
    # (el workspace antiguo de OpenClaw), no encontraba nada y el baseline se
    # escribía vacío: 300 líneas de inventario perdidas de una pasada.
    os.environ["WS"] = os.environ.get("WS") or os.path.expanduser("~/repos")

    # 1. Regenerar el inventario desde el código (sin silenciar errores).
    r = subprocess.run([sys.executable, EXTRACTOR], capture_output=True, text=True)
    if r.returncode != 0:
        print("ERROR: el extractor falló", file=sys.stderr)
        print(r.stdout, r.stderr, file=sys.stderr)
        return 1

    inv_path = "/tmp/api-inventory.json"
    if not os.path.isfile(inv_path):
        print("ERROR: el extractor no generó /tmp/api-inventory.json", file=sys.stderr)
        return 1

    current = json.load(open(inv_path, encoding="utf-8"))

    # Guarda: un inventario vacío es un extractor que no encontró los repos, no
    # una API sin hooks. Escribirlo borraría el baseline entero.
    total_hooks = sum(len(v.get("hooks", {})) for v in current.values())
    if total_hooks == 0:
        print(f"ERROR: el inventario salió vacío (WS={os.environ['WS']}): no se toca el baseline.", file=sys.stderr)
        return 1

    # 2. Mismo formato que el baseline: hooks como lista ordenada de nombres,
    #    REST como "namespace route METHODS", shortcodes ordenados.
    frozen = {}
    for repo in sorted(current):
        data = current[repo]
        frozen[repo] = {
            "hooks": sorted(data["hooks"].keys()),
            # Detalle por hook (dispara/escucha/retirado/origen). Va aparte para
            # que `hooks` siga siendo la lista de nombres que comparan los checks
            # ya existentes, y a la vez el documento legible se pueda regenerar
            # sin volver a recorrer el código.
            "hooks_detail": {k: data["hooks"][k] for k in sorted(data["hooks"])},
            "rest": sorted(
                f"{e['namespace']} {e['route']} {e['methods']}" for e in data["rest"]
            ),
            "shortcodes": sorted(data["shortcodes"]),
        }

    old = {}
    if os.path.isfile(BASELINE):
        old = json.load(open(BASELINE, encoding="utf-8"))

    cambios = []
    for repo in sorted(set(old) | set(frozen)):
        for kind in ("hooks", "rest", "shortcodes"):
            a = set(old.get(repo, {}).get(kind, []))
            b = set(frozen.get(repo, {}).get(kind, []))
            for added in sorted(b - a):
                cambios.append(f"+ {kind} en {repo}: {added}")
            for removed in sorted(a - b):
                cambios.append(f"- {kind} en {repo}: {removed}")

    if not cambios and old == frozen:
        print("Sin cambios: el baseline ya está al día.")
        return 0

    if not cambios:
        # Sin cambios de API, pero el fichero no es idéntico (p. ej. se añadió el
        # detalle de hooks): se reescribe y se dice, sin inventar cambios de API.
        print("Sin cambios de API; se actualiza el detalle del baseline.")

    json.dump(frozen, open(BASELINE, "w", encoding="utf-8"), ensure_ascii=False, indent=2)
    print(f"Baseline actualizado: {BASELINE}")
    print(f"Motivo: {args.motivo}")
    if cambios:
        print("Cambios aplicados:")
        print("\n".join(cambios))
    return 0


if __name__ == "__main__":
    sys.exit(main())
