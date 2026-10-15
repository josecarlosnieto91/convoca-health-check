#!/usr/bin/env python3
r"""Gate: dos plugins del ecosistema no pueden definir la MISMA función global.

Por qué existe (issue convoca-core#16): comparando los repos función a función salen copias literales entre
plugins. Duplicar código se arregla cuando se pueda, pero hay una clase de duplicado que **no es deuda
técnica, es un fallo esperando**: si dos plugins activos definen la misma función **global**, PHP revienta con
"cannot redeclare" al cargar el segundo. Con `function_exists()` de por medio a veces no explota, y entonces es
peor: gana la primera que cargue y la segunda nunca se ejecuta, en silencio.

Lo mismo con las CLASES sin espacio de nombres: dos plugins no pueden declarar la misma.

Qué se compara:
  - Funciones **globales** (`function nombre(` a nivel de columna cero), por nombre.
  - Clases **sin namespace**, por nombre.
    Los métodos de clase no cuentan (dos clases distintas pueden tener un método con el mismo nombre y es
    normal), y las clases con namespace tampoco: `Convoca\Members\Utils` y `Convoca\Core\Utils` son dos.

Qué se informa:
  - **COLISION**: el mismo nombre en dos plugins. Es lo que falla. Se enseña si los cuerpos son iguales
    (duplicado literal) o distintos (dos cosas distintas con el mismo nombre, que es lo peligroso).
  - El recuento de copias literales, como información: no falla, pero se ve.

Uso: check-colisiones.py <ruta-repo> [<ruta-repo> ...]
Sale 1 si hay colisión; 0 si no.
"""
from __future__ import annotations

import os
import re
import sys

EXCLUIR = ("vendor", "node_modules", "tests", ".git", "stubs", "languages")
RX_FUNCION = re.compile(r"^function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(", re.MULTILINE)
RX_CLASE = re.compile(r"^(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)", re.MULTILINE)
RX_NAMESPACE = re.compile(r"^namespace\s+[^;]+;", re.MULTILINE)


def limpiar(texto: str) -> str:
    texto = re.sub(r"/\*.*?\*/", "", texto, flags=re.DOTALL)
    texto = re.sub(r"//[^\n]*", "", texto)
    return texto

def inventario(raiz):
    """(funciones globales, clases sin namespace) de un repo: nombre -> (fichero, firma)."""
    funciones = {}
    clases = {}
    for base, dirs, ficheros in os.walk(raiz):
        dirs[:] = [d for d in dirs if d not in EXCLUIR]
        for f in ficheros:
            if not f.endswith(".php"):
                continue
            ruta = os.path.join(base, f)
            try:
                crudo = open(ruta, encoding="utf-8", errors="replace").read()
            except OSError:
                continue
            texto = limpiar(crudo)
            rel = os.path.relpath(ruta, raiz)
            con_namespace = bool(RX_NAMESPACE.search(texto))
            for m in RX_FUNCION.finditer(texto):
                nombre = m.group(1)
                trozo = texto[m.start():m.start() + 4000]
                firma = " ".join(trozo[:600].split())
                funciones.setdefault(nombre, (rel, firma))
            if not con_namespace:
                for m in RX_CLASE.finditer(texto):
                    clases.setdefault(m.group(1), rel)
    return funciones, clases


def main(argv):
    raices = [r for r in argv[1:] if os.path.isdir(r)]
    if not raices:
        print("uso: check-colisiones.py <ruta-repo> [...]")
        return 2

    todos = {}
    todas_clases = {}
    for raiz in raices:
        nombre = os.path.basename(os.path.abspath(raiz))
        fs, cs = inventario(raiz)
        for n, (rel, firma) in fs.items():
            todos.setdefault(n, []).append((nombre, firma))
        for n, rel in cs.items():
            todas_clases.setdefault(n, []).append(nombre)

    colisiones = {n: v for n, v in todos.items() if len(v) > 1}
    iguales = [n for n, v in colisiones.items() if len(set(f for _, f in v)) == 1]
    distintas = {n: v for n, v in colisiones.items() if len(set(f for _, f in v)) > 1}
    colisiones_clase = {n: v for n, v in todas_clases.items() if len(v) > 1}

    for n, v in sorted(distintas.items()):
        print("COLISION  funcion " + n + "()  ->  " + ", ".join(sorted({r for r, _ in v})) + "   [cuerpos DISTINTOS]")
    for n, v in sorted(colisiones_clase.items()):
        print("COLISION  clase " + n + "  ->  " + ", ".join(sorted(v)))

    print()
    print("funciones globales distintas: " + str(len(todos)))
    print("nombres repetidos entre plugins: " + str(len(colisiones)) + "  (identicos: " + str(len(iguales)) + " - distintos: " + str(len(distintas)) + ")")
    print("clases sin namespace repetidas: " + str(len(colisiones_clase)))
    hay = bool(distintas or colisiones_clase)
    print()
    print("Sin colisiones que revienten." if not hay else "HAY colisiones: dos plugins no pueden declarar lo mismo.")
    return 1 if hay else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
