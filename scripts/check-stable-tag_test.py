#!/usr/bin/env python3
"""El contrato del `Stable tag` se prueba solo: los tres casos que el guardián distingue.

Hasta el 26/10/2026 el guardián trataba igual **no tener readme** y **tenerlo sin `Stable tag`**. Al ampliar el
vigilante diario de 8 a 13 repos salieron tres rojos que no eran: `convoca-brand` (un tema, que no lleva
readme) y los dos mu-plugins. Un guardián que grita donde no toca enseña a ignorarlo, y con el ruido se
aprende a ignorar también el desfase de verdad.

Casos:
  1. versión y Stable tag coinciden                     → 0
  2. Stable tag con otra versión                        → 1
  3. readme.txt sin `Stable tag`                        → 1  (es un olvido, no una ausencia)
  4. repo sin readme.txt (tema, mu-plugin)              → 0  (no hay nada que cuadrar)

Uso: python3 check-stable-tag_test.py
Sale 1 si algún caso no se comporta como dice el guardián.
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
import tempfile

GUARDIAN = os.path.join(os.path.dirname(os.path.abspath(__file__)), "check-stable-tag.py")

PLUGIN = """<?php
/**
 * Plugin Name: {nombre}
 * Version: {version}
 */

"""

README = """=== {nombre} ===
Contributors: alguien
Tags: convoca

{tag}
"""


def sandbox(version: str, tag: str | None, con_readme: bool = True) -> str:
    """Un repo de mentira con la versión puesta y el readme en el estado que se quiera."""
    raiz = tempfile.mkdtemp(prefix="stable-tag-")
    with open(os.path.join(raiz, "convoca-prueba.php"), "w", encoding="utf-8") as f:
        f.write(PLUGIN.format(nombre="Convoca Prueba", version=version))
    if con_readme:
        linea = f"Stable tag: {tag}\n" if tag is not None else ""
        with open(os.path.join(raiz, "readme.txt"), "w", encoding="utf-8") as f:
            f.write(README.format(nombre="Convoca Prueba", tag=linea))
    return raiz


def corre(raiz: str) -> tuple[int, str]:
    p = subprocess.run([sys.executable, GUARDIAN, raiz], capture_output=True, text=True)
    return p.returncode, p.stdout + p.stderr


CASOS = (
    ("version y tag coinciden", dict(version="2.9.42", tag="2.9.42"), 0, "OK"),
    ("tag de otra version", dict(version="2.9.42", tag="2.9.41"), 1, "FALLO"),
    ("readme sin Stable tag", dict(version="2.9.42", tag=None), 1, "no trae"),
    ("repo sin readme", dict(version="1.0.0", tag=None, con_readme=False), 0, "sin readme.txt"),
)


def main() -> int:
    fallos = 0
    for nombre, kwargs, rc_esperado, texto in CASOS:
        raiz = sandbox(**kwargs)  # type: ignore[arg-type]
        try:
            rc, salida = corre(raiz)
        finally:
            shutil.rmtree(raiz, ignore_errors=True)

        bien = rc == rc_esperado and texto in salida
        print(f"{'OK  ' if bien else 'MAL '} {nombre}: rc={rc} (esperado {rc_esperado})")
        if not bien:
            print(f"     esperaba «{texto}» en:\n{salida.strip()}")
            fallos += 1

    print()
    if fallos:
        print(f"FALLAN {fallos} de {len(CASOS)} casos.")
        return 1
    print(f"RESUMEN: {len(CASOS)} casos, el guardian distingue la ausencia del olvido.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
