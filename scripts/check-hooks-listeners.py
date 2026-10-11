#!/usr/bin/env python3
"""
Comprueba que ningún plugin del ecosistema registra un oyente sobre un nombre de
hook RETIRADO.

Por qué existe: `Utils::do_action($nuevo, $viejo, …)` dispara el nombre nuevo y,
si queda alguien escuchando el viejo, lo dispara también con aviso de deprecación.
Dejar oyentes internos en el nombre viejo tiene dos consecuencias, y las dos son
graves:

  · un aviso `PHP Deprecated` en cada disparo (con WP_DEBUG_LOG eso es un log que
    no para de crecer en producción);
  · si el mismo callback se registra en los DOS nombres, el efecto se ejecuta dos
    veces (un correo enviado dos veces, un webhook duplicado).

Ni el lint ni el CI de un plugin suelto lo ven: cada lado es válido por separado.
Hay que cruzar los dos lados, y eso es lo que hace este script.

Uso:
    WS=~/repos python3 scripts/check-hooks-listeners.py
    python3 scripts/check-hooks-listeners.py --dir /ruta/a/repos

Salida 0 = sin oyentes internos de hooks retirados. 1 = hay alguno (con detalle).
"""
import argparse
import os
import re
import sys

REPOS = [
    "convoca-core",
    "convoca-members",
    "convoca-enroll",
    "convoca-gateway",
    "convoca-shifts",
    "convoca-publisher",
    "convoca-assistant",
    "convoca-theme",
]

# Lanzador con nombre retirado: Utils::do_action('nuevo', 'viejo', …)
RE_LANZADOR = re.compile(
    r"Utils::\s*(?:do_action|apply_filters)\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]+)['\"]"
)
# Oyente: add_action('hook', …) / add_filter('hook', …)
RE_OYENTE = re.compile(r"add_(?:action|filter)\(\s*['\"]([^'\"]+)['\"]")


def php_files(root):
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [
            d for d in dirnames if d not in ("vendor", "node_modules", ".git", "tests", "build-dir")
        ]
        for f in filenames:
            if f.endswith(".php"):
                yield os.path.join(dirpath, f)


def sin_comentarios(src):
    """Quita comentarios y literales de comentario: el comentario que explica el
    defecto nombra justo lo que se quiere prohibir."""
    src = re.sub(r"/\*.*?\*/", "", src, flags=re.S)
    src = re.sub(r"//[^\n]*", "", src)
    src = re.sub(r"(?m)^\s*#[^\n]*", "", src)
    return src


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dir", default=os.environ.get("WS") or os.path.expanduser("~/repos"))
    args = ap.parse_args()
    ws = args.dir

    if not os.path.isdir(ws):
        print(f"ERROR: no existe el directorio de repos: {ws}", file=sys.stderr)
        return 1

    pares = {}      # viejo -> nuevo
    oyentes = {}    # viejo -> [(repo, fichero)]
    lanzadores = 0
    oyentes_total = 0

    for repo in REPOS:
        root = os.path.join(ws, repo)
        if not os.path.isdir(root):
            print(f"ERROR: falta el repositorio {repo} en {ws}", file=sys.stderr)
            return 1
        for path in php_files(root):
            rel = os.path.relpath(path, ws)
            try:
                src = sin_comentarios(open(path, encoding="utf-8", errors="replace").read())
            except OSError:
                continue
            for m in RE_LANZADOR.finditer(src):
                pares[m.group(2)] = m.group(1)
                lanzadores += 1
            for m in RE_OYENTE.finditer(src):
                oyentes_total += 1
                oyentes.setdefault(m.group(1), []).append(rel)

    # Guarda: si no se encuentra ningún lanzador, el análisis no ha mirado el
    # código que cree (WS equivocado) y el verde sería cosmético.
    if lanzadores == 0:
        print(f"ERROR: no se encontró ningún lanzador Utils::do_action(nuevo, viejo) en {ws}", file=sys.stderr)
        return 1
    if oyentes_total == 0:
        print(f"ERROR: no se encontró ningún add_action/add_filter en {ws}", file=sys.stderr)
        return 1

    malos = []
    for viejo, nuevo in sorted(pares.items()):
        for rel in sorted(set(oyentes.get(viejo, []))):
            malos.append(f"{viejo} (retirado; usar {nuevo}) lo escucha {rel}")

    if malos:
        print(f"✗ {len(malos)} oyente(s) interno(s) sobre hooks retirados:")
        for m in malos:
            print(f"   · {m}")
        print("")
        print("Arreglo: registrar el oyente con el nombre NUEVO (y solo con ese).")
        return 1

    print(f"✓ Sin oyentes internos de hooks retirados ({len(pares)} pares revisados, {oyentes_total} registros).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
