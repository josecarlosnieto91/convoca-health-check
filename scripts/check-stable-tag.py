#!/usr/bin/env python3
"""Gate: el `Stable tag` del readme.txt de un repo coincide con su version (issue health-check#13).

Por que existe: al subir la version de un plugin se toca la cabecera del fichero principal y el CHANGELOG,
pero el `Stable tag` del readme.txt se queda atras sin que nadie lo note. No es cosmetico —un Stable tag que
no cuadra impide a wordpress.org servir los archivos correctos— y ademas ponia en ROJO el trabajo de Plugin
Check de CINCO repos el 15/10/2026, donde ese rojo se confundio con el fallo de red que describia
convoca-members#11. Costo horas de diagnostico por algo que se comprueba leyendo dos ficheros.

Que comprueba, y por que asi:

  1. La version del repo: la cabecera del fichero principal del plugin (`Version:` en el que declara
     `Plugin Name:`) o, si es un tema, `Version:` de `style.css`. Es la misma vara que usa
     check-pot-al-dia.py: buscar el fichero y no la posicion.

  2. El `Stable tag:` del readme.txt.

  3. Que coincidan. Si el readme no trae Stable tag, se dice (no se da por bueno).

Lo que NO hace: mirar el CHANGELOG ni el .pot. Cada guardian vigila lo suyo; el .pot ya tiene el suyo.

Uso: check-stable-tag.py <ruta-repo> [<ruta-repo> ...]
Sale 0 si todos cuadran; 1 con el detalle de los que no.
"""
from __future__ import annotations

import os
import re
import sys

RX_VERSION_CABECERA = re.compile(r"^\s*\*?\s*Version:\s*([0-9][0-9A-Za-z.\-]*)", re.MULTILINE)
RX_VERSION_CSS = re.compile(r"^Version:\s*([0-9][0-9A-Za-z.\-]*)", re.MULTILINE)
RX_PLUGIN_NAME = re.compile(r"^\s*\*?\s*Plugin Name:", re.MULTILINE)
RX_STABLE_TAG = re.compile(r"^\s*Stable tag:\s*([0-9][0-9A-Za-z.\-]*)", re.MULTILINE | re.IGNORECASE)


def leer(ruta: str) -> str:
    return open(ruta, encoding="utf-8", errors="replace").read()


def version_del_repo(raiz: str) -> tuple[str, str]:
    """(version, de donde) del repositorio. Tema: style.css. Plugin: el fichero que declara Plugin Name."""
    style = os.path.join(raiz, "style.css")
    if os.path.exists(style):
        m = RX_VERSION_CSS.search(leer(style)[:2000])
        if m:
            return m.group(1), "style.css"

    for nombre in sorted(os.listdir(raiz)):
        if not nombre.endswith(".php"):
            continue
        ruta = os.path.join(raiz, nombre)
        if not os.path.isfile(ruta):
            continue
        texto = leer(ruta)[:4000]
        if not RX_PLUGIN_NAME.search(texto):
            continue
        m = RX_VERSION_CABECERA.search(texto)
        if m:
            return m.group(1), nombre

    return "", ""


def stable_tag(raiz: str) -> str:
    readme = os.path.join(raiz, "readme.txt")
    if not os.path.exists(readme):
        return ""
    m = RX_STABLE_TAG.search(leer(readme)[:4000])
    return m.group(1) if m else ""


def main(rutas: list[str]) -> int:
    fallos = 0
    for raiz in rutas:
        nombre = os.path.basename(raiz.rstrip("/"))
        version, origen = version_del_repo(raiz)
        if not version:
            print(f"FALLO {nombre}: no encuentro la version (ni style.css ni cabecera de plugin)")
            fallos += 1
            continue

        tag = stable_tag(raiz)
        if not tag:
            print(f"FALLO {nombre}: el readme.txt no trae `Stable tag` (version {version} en {origen})")
            fallos += 1
            continue

        if tag != version:
            print(f"FALLO {nombre}: Stable tag {tag} != version {version} (de {origen})")
            fallos += 1
            continue

        print(f"  OK  {nombre}")

    if fallos:
        print(f"\n{fallos} repositorio(s) con el Stable tag sin cuadrar.")
        return 1

    return 0


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(2)
    sys.exit(main(sys.argv[1:]))
