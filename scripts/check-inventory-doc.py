#!/usr/bin/env python3
"""
Comprueba que docs/hooks-inventario.md no mienta respecto a api/api-v3.0.json.

El documento se generaba con un script que se perdió, así que nada impedía que se
quedara con shortcodes retirados (pasó con `[convoca_dark_mode_toggle]`, que el
theme ya no registra). Esto compara lo que el documento DICE con lo que el
extractor ENCUENTRA, y falla si hay diferencias en cualquier sentido:

  · en el documento y no en el código  → fila fantasma
  · en el código y no en el documento  → documentación incompleta
  · recuentos del resumen que no cuadran

Uso:  python3 scripts/check-inventory-doc.py [ruta-del-repo]
Sale 0 si cuadra; 1 con el detalle de las diferencias.
"""
import json
import pathlib
import re
import sys


def main() -> int:
    raiz = pathlib.Path(sys.argv[1] if len(sys.argv) > 1 else ".")
    doc = raiz / "docs" / "hooks-inventario.md"
    api = raiz / "api" / "api-v3.0.json"

    if not doc.exists() or not api.exists():
        print(f"ERROR: falta {doc} o {api}")
        return 1

    datos = json.loads(api.read_text(encoding="utf-8"))
    texto = doc.read_text(encoding="utf-8")

    # ── Shortcodes: el documento los lista como `[tag]` | plugin
    en_doc = set(re.findall(r"^\|\s*`\[([a-z0-9_]+)\]`\s*\|\s*([a-z0-9-]+)\s*\|", texto, re.M))
    en_codigo = {
        (tag, plugin)
        for plugin, d in datos.items()
        for tag in d.get("shortcodes", [])
    }

    # ── Recuentos del resumen
    resumen = dict(
        (m.group(1).lower(), int(m.group(2)))
        for m in re.finditer(r"^\|\s*(Hooks|Shortcodes)\s*\|\s*(\d+)\s*\|", texto, re.M)
    )
    total_hooks = sum(len(d.get("hooks", [])) for d in datos.values())
    total_shortcodes = sum(len(d.get("shortcodes", [])) for d in datos.values())

    problemas = []

    for tag, plugin in sorted(en_doc - en_codigo):
        problemas.append(f"fila fantasma: `[{tag}]` figura como de {plugin}, y el código no lo registra")
    for tag, plugin in sorted(en_codigo - en_doc):
        problemas.append(f"falta en el documento: `[{tag}]` ({plugin})")

    if resumen.get("shortcodes") != total_shortcodes:
        problemas.append(f"resumen de shortcodes: el documento dice {resumen.get('shortcodes')} y hay {total_shortcodes}")
    if resumen.get("hooks") != total_hooks:
        problemas.append(f"resumen de hooks: el documento dice {resumen.get('hooks')} y hay {total_hooks}")

    if problemas:
        print(f"✗ docs/hooks-inventario.md no cuadra con api/api-v3.0.json ({len(problemas)} problema(s)):")
        for p in problemas:
            print(f"   · {p}")
        return 1

    print(f"✓ Inventario coherente: {total_hooks} hooks y {total_shortcodes} shortcodes, "
          f"y el documento dice lo mismo.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
