#!/usr/bin/env python3
"""Genera `docs/openapi.yaml` desde el código.

**Por qué existe:** el documento lleva en su cabecera «GENERADO AUTOMÁTICAMENTE. No editar a mano.» y
`docs/api-v3.0.md` dice que se regenera con «`api-extract.py` + generador OpenAPI». **Ese generador no
existía**: el fichero se escribió una vez a mano y derivó — declaraba 51 rutas cuando el código tiene
52, y faltaba `/convoca/v1/assistant/recent` desde vete a saber cuándo. Un documento que dice
«automático» y no lo es engaña más que uno que reconoce ser manual.

Uso:
    python3 build-openapi.py            # escribe docs/openapi.yaml
    python3 build-openapi.py --check    # sale 2 si el documento no coincide con el código

WS: espacio de trabajo con los repos (por defecto ~/repos; en el CI, el directorio donde se clonan).
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DOC = RAIZ / "docs" / "openapi.yaml"
EXTRACTOR = RAIZ / "scripts" / "api-extract.py"
INVENTARIO = Path("/tmp/api-inventory.json")   # ruta fija del extractor (ojo: no uses TMPDIR)

# El código declara las rutas con grupos con nombre de PHP —`/documentos/(?P<id>\d+)`—, que es lo
# correcto en `register_rest_route` pero **no es sintaxis OpenAPI**. El documento usa `{id}`. Si el
# generador copiara la ruta tal cual, «mejoraría» la fidelidad al código rompiendo la validez del
# documento: lo que se documenta es la forma de la ruta, no la expresión regular que la captura.
RX_PHP = re.compile(r"\(\?P<([^>]+)>[^)]*\)")


def a_openapi(ruta: str) -> str:
    """Traduce el grupo con nombre de PHP a la plantilla de OpenAPI."""
    return RX_PHP.sub(lambda m: "{" + m.group(1) + "}", ruta)



def inventario() -> dict:
    env = dict(os.environ)
    env.setdefault("WS", os.path.expanduser("~/repos"))
    if not os.path.isdir(env["WS"]):
        print(f"ERROR: no encuentro el espacio de trabajo ({env['WS']}).", file=sys.stderr)
        sys.exit(1)

    if INVENTARIO.exists():
        INVENTARIO.unlink()
    r = subprocess.run([sys.executable, str(EXTRACTOR)], capture_output=True, text=True, env=env)
    if r.returncode != 0 or not INVENTARIO.exists():
        print("ERROR: el extractor de la API falló.", file=sys.stderr)
        print(r.stdout[-1500:], r.stderr[-1500:], file=sys.stderr)
        sys.exit(1)
    return json.loads(INVENTARIO.read_text())


def generado() -> str:
    datos = inventario()

    rutas = []
    etiquetas = []
    for repo, d in datos.items():
        for e in d.get("rest", []):
            ns = str(e.get("namespace", "")).rstrip("/")
            ruta = a_openapi("/" + ns + "/" + str(e.get("route", "")).lstrip("/"))
            metodo = str(e.get("methods", "GET")).split(",")[0].strip().upper()
            if ns and ns not in etiquetas:
                etiquetas.append(ns)
            rutas.append({"path": ruta, "method": metodo, "ns": ns,
                          "file": e.get("file", "?"), "repo": repo})

    rutas.sort(key=lambda x: x["path"])

    lineas = [
        "# Convoca REST API — OpenAPI 3.0",
        "# GENERADO AUTOMÁTICAMENTE. No editar a mano.",
        "#",
        "# Se regenera con: python3 scripts/build-openapi.py",
        "# Y el CI comprueba que no se haya quedado atrás: scripts/build-openapi.py --check",
        "",
        "openapi: 3.0.3",
        "info:",
        "  title: Convoca REST API",
        "  version: 3.0.0",
        "  description: |",
        "    API pública del ecosistema Convoca.",
        "    Generada automáticamente desde el código.",
        "servers:",
        "  - url: 'https://{site}/wp-json'",
        "    variables:",
        "      site:",
        "        default: tu-dominio.org",
        "tags:",
    ]
    for ns in etiquetas:
        dueno = next((r["repo"] for r in rutas if r["ns"] == ns), "convoca")
        lineas.append(f"  - name: {ns}")
        lineas.append(f"    description: Endpoints de {dueno}")
    lineas.append("paths:")

    for r in rutas:
        lineas.append(f"  {r['path']}:")
        lineas.append(f"    {r['method'].lower()}:")
        lineas.append(f"      tags: [{r['ns']}]")
        lineas.append(f'      summary: "{r["method"]} {r["path"]}"')
        lineas.append(f'      description: "Endpoint del plugin {r["repo"]} (fuente: {r["file"]})."')
        lineas.append("      security:")
        lineas.append("        - cookieAuth: []")
        lineas.append("      responses:")
        lineas.append("        '200':")
        lineas.append("          description: OK")
        lineas.append("        '401':")
        lineas.append("          description: No autenticado")
        lineas.append("        '403':")
        lineas.append("          description: Sin permisos")
        lineas.append("        '404':")
        lineas.append("          description: No encontrado")

    lineas += [
        "components:",
        "  securitySchemes:",
        "    cookieAuth:",
        "      type: apiKey",
        "      in: cookie",
        "      name: wordpress_logged_in",
        "",
    ]
    return "\n".join(lineas)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="No escribe: falla si el documento está desfasado.")
    args = ap.parse_args()

    nuevo = generado()

    if args.check:
        actual = DOC.read_text(encoding="utf-8") if DOC.exists() else ""
        if actual.strip() != nuevo.strip():
            a = set(re.findall(r"^  (/[^:\s]+):\s*$", a_openapi(actual), re.M))
            b = set(re.findall(r"^  (/[^:\s]+):\s*$", a_openapi(nuevo), re.M))
            print("❌ docs/openapi.yaml no coincide con el código.")
            for x in sorted(b - a):
                print(f"   el código tiene y el documento no: {x}")
            for x in sorted(a - b):
                print(f"   el documento tiene y el código no: {x}")
            if a == b:
                print("   (las rutas cuadran: lo que difiere es otra cosa del formato)")
            print("   Se corrige regenerando: python3 scripts/build-openapi.py")
            return 2
        print(f"✓ docs/openapi.yaml está al día ({nuevo.count(chr(10) + '  /')} rutas).")
        return 0

    DOC.write_text(nuevo, encoding="utf-8")
    print(f"✓ docs/openapi.yaml regenerado ({nuevo.count(chr(10) + '  /')} rutas).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
