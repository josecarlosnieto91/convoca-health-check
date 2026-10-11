#!/usr/bin/env python3
"""
Genera docs/hooks-inventario.md desde api/api-v3.0.json.

El documento es una instantánea legible del inventario congelado: shortcodes y
tabla de hooks por plugin, con tipo (dispara / escucha) y fichero de origen.
La fuente de verdad sigue siendo api/api-v3.0.json; este script solo lo pinta.

Antes este generador no existía («se perdió», decía el propio documento), así que
las tablas de hooks se quedaban viejas sin que nada lo notara: el check
check-inventory-doc.py solo validaba los shortcodes y los dos recuentos.

Uso:
    python3 scripts/build-hooks-inventory-doc.py            # escribe el documento
    python3 scripts/build-hooks-inventory-doc.py --check    # falla si está desactualizado
"""
import argparse
import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
API = ROOT / "api" / "api-v3.0.json"
DOC = ROOT / "docs" / "hooks-inventario.md"

ORDEN_PLUGINS = [
    "convoca-core",
    "convoca-members",
    "convoca-enroll",
    "convoca-gateway",
    "convoca-shifts",
    "convoca-publisher",
    "convoca-assistant",
    "convoca-theme",
]


def tipo(hook_data):
    dispara = hook_data.get("fires", False) or not hook_data.get("listens", False)
    escucha = hook_data.get("listens", False)
    if dispara and escucha:
        etiqueta = "dispara y escucha"
    elif escucha:
        etiqueta = "escucha"
    else:
        etiqueta = "dispara"
    if hook_data.get("deprecated"):
        etiqueta += " (retirado)"
    return etiqueta


def render(datos):
    total_hooks = sum(len(d.get("hooks", {})) for d in datos.values())
    total_shortcodes = sum(len(d.get("shortcodes", [])) for d in datos.values())

    out = []
    out.append("# Convoca API Pública v3.0 — Hooks y Shortcodes")
    out.append("")
    out.append("> Instantánea legible de la API pública. La fuente de verdad es `api/api-v3.0.json`")
    out.append("> (lo regenera `scripts/api-extract.py`) y este documento se regenera con")
    out.append("> `scripts/build-hooks-inventory-doc.py`. `scripts/check-inventory-doc.py` contrasta")
    out.append("> los dos en CI: si el documento miente, el check falla.")
    out.append("")
    out.append("## Resumen")
    out.append("")
    out.append("| Recurso | Cantidad |")
    out.append("|---------|----------|")
    out.append(f"| Hooks | {total_hooks} |")
    out.append(f"| Shortcodes | {total_shortcodes} |")
    out.append("")
    out.append("## Shortcodes")
    out.append("")
    out.append("| Shortcode | Plugin |")
    out.append("|-----------|--------|")
    for plugin in ORDEN_PLUGINS + [p for p in sorted(datos) if p not in ORDEN_PLUGINS]:
        if plugin not in datos:
            continue
        for tag in sorted(datos[plugin].get("shortcodes", [])):
            out.append(f"| `[{tag}]` | {plugin} |")
    out.append("")
    out.append("## Hooks por plugin")
    for plugin in ORDEN_PLUGINS + [p for p in sorted(datos) if p not in ORDEN_PLUGINS]:
        if plugin not in datos:
            continue
        detalle = datos[plugin].get("hooks_detail", {})
        nombres = sorted(datos[plugin].get("hooks", {}))
        # Sin detalle (baseline antiguo) se cae a la lista de nombres con el tipo
        # «—»: mejor un documento honesto que uno inventado.
        out.append("")
        out.append(f"### {plugin} ({len(nombres)} hooks)")
        out.append("")
        out.append("| Hook | Tipo | Origen |")
        out.append("|------|------|--------|")
        for name in nombres:
            data = detalle.get(name)
            if data is None:
                out.append(f"| `{name}` | — | — |")
                continue
            origen = ", ".join(data.get("files", [])) or "—"
            out.append(f"| `{name}` | {tipo(data)} | {origen} |")
    out.append("")
    return "\n".join(out)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="No escribe: falla si el documento está desactualizado")
    args = ap.parse_args()

    datos = json.loads(API.read_text(encoding="utf-8"))
    nuevo = render(datos)

    actual = DOC.read_text(encoding="utf-8") if DOC.exists() else ""

    if args.check:
        if actual.strip() != nuevo.strip():
            print("✗ docs/hooks-inventario.md está desactualizado respecto a api/api-v3.0.json")
            print("  Regenerar con: python3 scripts/build-hooks-inventory-doc.py")
            return 1
        print("✓ docs/hooks-inventario.md coincide con el inventario.")
        return 0

    DOC.write_text(nuevo, encoding="utf-8")
    print(f"✓ Documento regenerado: {DOC}")
    total_hooks = sum(len(d.get("hooks", {})) for d in datos.values())
    print(f"  {total_hooks} hooks · {sum(len(d.get('shortcodes', [])) for d in datos.values())} shortcodes")
    return 0


if __name__ == "__main__":
    sys.exit(main())
