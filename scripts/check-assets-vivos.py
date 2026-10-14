#!/usr/bin/env python3
"""Gate de recursos vivos: ningún fichero viaja en un plugin sin que nadie lo cargue.

Por qué existe: en la pasada 9 del gauntlet se encontró `convoca-core/assets/js/convoca-monitor.js`
—un panel de métricas en vivo— que **no lo encolaba ningún PHP** y cuyo HTML tampoco existía en
ninguna parte: el resto de una función que se retiró del servidor y dejó su JavaScript detrás.
Nadie lo miraba porque un fichero de más no rompe nada y su prueba no falla: simplemente engorda el
paquete y confunde a quien lee.

Lo que comprueba: para cada `.js` y `.css` que un plugin publica, que algún PHP del mismo repo lo
nombre (por nombre de fichero o por handle) o que esté en la lista de exenciones con su motivo.

Cómo se salta un caso justificado: en la misma línea que lo declara, un comentario con
`convoca-hygiene-ignore: <motivo>`, o añadirlo a EXENTOS con su razón escrita.

Uso:
    python3 check-assets-vivos.py [raiz-de-los-repos]
Sale con 0 si está limpio y 2 si encuentra algo (para que el CI lo note).
"""
from __future__ import annotations

import argparse
import os
import re
import sys

# Ficheros que no los carga el PHP del plugin, con el motivo escrito.
EXENTOS = {
    # Configuración del ejecutor de pruebas de navegador: la usa Playwright, no WordPress.
    "playwright.config.js": "configuracion de Playwright, no la carga WordPress",
    # El service worker lo registra el JS del plugin por su ruta, no el PHP por su nombre.
    "sw.js": "service worker de la PWA: lo registra el JS del plugin",
}

# Carpetas que no forman parte de lo que se publica.
FUERA = {"vendor", "node_modules", "tests", "test-results", ".git", "languages",
         "assets-wporg", "docs", "dev-tools", "stubs", "__pycache__"}

REPOS = ["convoca-core", "convoca-enroll", "convoca-members", "convoca-gateway",
         "convoca-shifts", "convoca-publisher", "convoca-assistant", "convoca-theme"]

RX_IGNORA = re.compile(r"convoca-hygiene-ignore\s*:\s*\S+")


def recursos(base: str):
    """Los `.js` y `.css` que publica el plugin."""
    for raiz, dirs, ficheros in os.walk(base):
        dirs[:] = [d for d in dirs if d not in FUERA]
        for f in ficheros:
            if f.endswith((".js", ".css")) and "min.js" not in f[:0]:
                yield os.path.join(raiz, f)


def php_del_repo(base: str) -> str:
    """Todo el PHP y los `block.json` del plugin, juntos: solo interesa si nombran el recurso.

    Los `block.json` cuentan porque un bloque puede declarar su script con `file:./x.js`, y
    entonces WordPress SÍ lo carga aunque ningún PHP lo nombre. Sin mirarlos, el verificador daba
    un falso positivo con cualquier bloque legítimo — y un verificador que da falsos positivos es
    un verificador que alguien acaba silenciando.
    """
    trozos = []
    for raiz, dirs, ficheros in os.walk(base):
        dirs[:] = [d for d in dirs if d not in FUERA]
        for f in ficheros:
            if f.endswith(".php") or f == "block.json":
                trozos.append(open(os.path.join(raiz, f), encoding="utf-8", errors="ignore").read())
    return "\n".join(trozos)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("raiz", nargs="?", default=os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                    help="carpeta que contiene los repos de los plugins")
    args = ap.parse_args()

    hallazgos = []
    revisados = 0

    for repo in REPOS:
        base = os.path.join(args.raiz, repo)
        if not os.path.isdir(base):
            continue
        php = php_del_repo(base)
        for recurso in recursos(base):
            nombre = os.path.basename(recurso)
            if nombre in EXENTOS or os.path.relpath(recurso, base) in EXENTOS:
                continue
            revisados += 1
            handle = re.sub(r"\.(js|css)$", "", nombre)
            if nombre in php or handle in php:
                continue
            if RX_IGNORA.search(php):
                # El motivo tiene que estar: si el repo tiene algún ignore, se avisa y se revisa a mano.
                hallazgos.append((repo, os.path.relpath(recurso, base),
                                  "hay un `convoca-hygiene-ignore` en el repo: revisar a mano"))
                continue
            hallazgos.append((repo, os.path.relpath(recurso, base),
                              "ningún PHP del plugin lo menciona"))

    print(f"   recursos revisados: {revisados}")
    if not hallazgos:
        print("   OK: todos los recursos que viajan los carga alguien.")
        return 0

    print(f"   HALLAZGOS: {len(hallazgos)}")
    for repo, ruta, motivo in hallazgos:
        print(f"      {repo}/{ruta} — {motivo}")
    print()
    print("   Un recurso que nadie carga es código muerto: o se encola, o se borra, o se añade a")
    print("   EXENTOS con el motivo escrito.")
    return 2


if __name__ == "__main__":
    sys.exit(main())
