#!/usr/bin/env python3
"""Guardián de la documentación de la API: no puede declarar números que no sean ciertos.

Por qué existe: `docs/api-v3.0.md` declaraba **164 hooks y 243 elementos** cuando la realidad
era **202 y 281**, y nada lo comprobaba — el job `api-freeze` mira el código contra el baseline
congelado, y el inventario legible, pero **nadie miraba el documento**. Dos pasadas del
gauntlet lo dieron por bueno leyéndolo, que es exactamente la trampa que el gauntlet prohíbe:
un documento que dice «congelado» y unos números que nadie mide.

Qué comprueba: la tabla «Inventario congelado» de `docs/api-v3.0.md` contra el inventario real
(que se regenera del código con `api-extract.py`, igual que hacen los otros guardas).

Uso:
    python3 check-api-doc.py            # WS por defecto: ~/repos
    WS=/ruta/a/repos python3 check-api-doc.py

Sale 0 si el documento cuadra, 2 si miente, 1 si no puede medir (y lo dice).
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DOC = RAIZ / "docs" / "api-v3.0.md"
EXTRACTOR = RAIZ / "scripts" / "api-extract.py"

# Qué número del documento se compara con qué magnitud del inventario.
FILAS = {
    "Hooks": "hooks",
    "REST endpoints": "rest",
    "Shortcodes": "shortcodes",
    "Meta keys": "meta_keys",
    "Opciones": "options",
}


def inventario_real() -> dict:
    """Regenera el inventario del código y devuelve los totales."""
    env = dict(os.environ)
    env.setdefault("WS", os.path.expanduser("~/repos"))
    if not os.path.isdir(env["WS"]):
        print(f"ERROR: no encuentro el espacio de trabajo ({env['WS']}); no puedo medir.", file=sys.stderr)
        sys.exit(1)

    # OJO: el extractor escribe en `/tmp/api-inventory.json` (ruta fija). No uses
    # `tempfile.gettempdir()`: en este entorno TMPDIR apunta al directorio de trabajo temporal
    # de Hermes, así que la ruta no coincidía y el guardián se declaraba incapaz de medir.
    salida = Path("/tmp/api-inventory.json")
    if salida.exists():
        salida.unlink()

    r = subprocess.run([sys.executable, str(EXTRACTOR)], capture_output=True, text=True, env=env)
    if r.returncode != 0 or not salida.exists():
        print("ERROR: el extractor de la API falló; no puedo medir sin él.", file=sys.stderr)
        print(r.stdout[-2000:], r.stderr[-2000:], file=sys.stderr)
        sys.exit(1)

    datos = json.loads(salida.read_text())
    reales = {"hooks": 0, "rest": 0, "shortcodes": 0, "meta_keys": 0, "options": 0}
    for _repo, d in datos.items():
        reales["hooks"] += len(d.get("hooks", []))
        reales["rest"] += len(d.get("rest", []))
        reales["shortcodes"] += len(d.get("shortcodes", []))
        reales["meta_keys"] += len(d.get("meta_keys", []))
        reales["options"] += len(d.get("options", []))
    reales["total"] = reales["hooks"] + reales["rest"] + reales["shortcodes"]
    return reales


def declarado() -> dict:
    """Lee los números que declara el documento."""
    if not DOC.exists():
        print(f"ERROR: no encuentro {DOC}", file=sys.stderr)
        sys.exit(1)

    texto = DOC.read_text(encoding="utf-8")
    valores = {}
    for etiqueta, clave in FILAS.items():
        m = re.search(r"\|\s*\**" + re.escape(etiqueta) + r"\**\s*\|\s*\**(\d+)\**", texto)
        if not m:
            print(f"AVISO: el documento no declara «{etiqueta}».", file=sys.stderr)
            continue
        valores[clave] = int(m.group(1))
    m = re.search(r"Total elementos API\**\s*\|\s*\**(\d+)", texto)
    if m:
        valores["total"] = int(m.group(1))
    return valores


def main() -> int:
    real = inventario_real()
    dice = declarado()

    nombres = {"hooks": "hooks", "rest": "REST", "shortcodes": "shortcodes",
               "meta_keys": "meta keys", "options": "opciones", "total": "elementos (hooks+REST+shortcodes)"}

    problemas = []
    for clave, nombre in nombres.items():
        if clave not in dice:
            continue
        if dice[clave] != real[clave]:
            problemas.append(f"  {nombre}: el documento dice {dice[clave]}, la realidad es {real[clave]}")

    if problemas:
        print("❌ LA DOCUMENTACIÓN DE LA API NO CUADRA CON EL CÓDIGO:")
        print("\n".join(problemas))
        print("")
        print("La fuente de verdad es el código; el documento se corrige, no al revés.")
        print("Si el cambio de inventario es intencionado, actualiza el baseline con")
        print("`scripts/api-freeze-update.py --motivo \"...\"` y después los números del documento.")
        return 2

    medidas = ", ".join(f"{dice[k]} {nombres[k]}" for k in ("hooks", "rest", "shortcodes", "total") if k in dice)
    print(f"✓ La documentación de la API dice la verdad ({medidas}).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
