#!/usr/bin/env python3
"""Gate: el .pot de un repo está al día respecto a su código (issue health-check#11).

Por qué existe: los cinco .pot se generaron a mano una vez y nadie dejó el comando, así que se quedaron
meses por detrás: el de members declaraba 2.8.39 con el plugin en 2.8.69. Un .pot desfasado no es
documentación vieja, es lo que se le entrega a quien traduce — las cadenas nuevas no existen para nadie.

Qué comprueba, y por qué así (que sea barato no lo hace menos cierto):

  1. **La versión.** El generador escribe en la cabecera la versión del plugin en el momento de generarlo
     (`Project-Id-Version`). Si no coincide con la del fichero principal, el .pot es de una versión anterior:
     hay código que se cambió después y sus cadenas pueden faltar. Es un testigo fiel mientras los repos
     suban la versión en cada cambio publicado, que es lo que hacen.

  2. **Que no apunte a ficheros que ya no están.** El .pot lleva las referencias `#: fichero:línea` de donde
     salió cada cadena. Si apunta a un fichero que no existe, es que el catálogo se generó sobre un árbol
     distinto del actual. Caso real que motivó esta comprobación: el del tema arrastraba **133 referencias a
     `build-dir/convoca/`**, un directorio que ya no existe en el repositorio.

Lo que NO hace: parsear el código para deducir las cadenas. Eso exige el mismo parser que `make-pot` y su
verdad es la suya; aquí se vigilan los dos rastros que ese parser deja y que se pueden comprobar sin él.

Uso: check-pot-al-dia.py <ruta-repo> [<ruta-repo> ...]
Sale 0 si todos están al día; 1 con el detalle de los que no.
"""
from __future__ import annotations

import os
import re
import sys


def version_declarada(texto: str) -> str:
    """La version que el .pot declara en su cabecera, o cadena vacia si no la lleva.

    Sin regex a proposito: en el .pot la version va seguida de un `\\n` LITERAL dentro de la
    comilla, y una expresion regular ahi es un campo de minas. Un `split` se lee y no falla.
    """
    for linea in texto.splitlines():
        if "Project-Id-Version:" not in linea:
            continue
        resto = linea.split("Project-Id-Version:", 1)[1]
        resto = resto.split(chr(92) + "n")[0]
        return resto.split(chr(34))[0].strip().strip(",")
    return ""
RX_CABECERA_PLUGIN = re.compile(r"^\s*\*?\s*Version:\s*([0-9][0-9A-Za-z.\-]*)", re.MULTILINE)
RX_VERSION_CSS = re.compile(r"^Version:\s*([0-9][0-9A-Za-z.\-]*)", re.MULTILINE)
RX_REF = re.compile(r"^#:\s*(.+)$", re.MULTILINE)


def version_del_repo(raiz: str) -> tuple[str, str]:
    """(version, de donde) del repositorio. Tema: style.css. Plugin: el fichero principal."""
    style = os.path.join(raiz, "style.css")
    if os.path.exists(style):
        texto = open(style, encoding="utf-8", errors="replace").read()[:2000]
        m = RX_VERSION_CSS.search(texto)
        if m:
            return m.group(1), "style.css"
    for nombre in sorted(os.listdir(raiz)):
        if nombre.endswith(".php"):
            texto = open(os.path.join(raiz, nombre), encoding="utf-8", errors="replace").read()[:4000]
            if "Plugin Name:" in texto:
                m = RX_CABECERA_PLUGIN.search(texto)
                if m:
                    return m.group(1), nombre
    return "", ""


def revisar(raiz: str) -> list[str]:
    problemas = []
    lang = os.path.join(raiz, "languages")
    if not os.path.isdir(lang):
        return problemas
    pots = [f for f in sorted(os.listdir(lang)) if f.endswith(".pot")]
    if not pots:
        problemas.append("no hay ningún .pot en languages/")
        return problemas

    version, origen = version_del_repo(raiz)
    for pot in pots:
        ruta = os.path.join(lang, pot)
        texto = open(ruta, encoding="utf-8", errors="replace").read()

        declarada = version_declarada(texto)
        if version and declarada and version not in declarada:
            problemas.append(
                f"{pot}: la cabecera dice «{declarada}» y {origen} va por {version} "
                f"-> el .pot es anterior al código: regenerar con `wp i18n make-pot`"
            )

        faltan = set()
        for linea in RX_REF.findall(texto):
            for ref in linea.split():
                fichero = ref.split(":")[0]
                if fichero and not os.path.exists(os.path.join(raiz, fichero)):
                    faltan.add(fichero)
        if faltan:
            muestra = ", ".join(sorted(faltan)[:3])
            problemas.append(f"{pot}: {len(faltan)} referencia(s) a ficheros que no existen ({muestra})")
    return problemas


def main(argv: list[str]) -> int:
    raices = argv[1:]
    if not raices:
        print("uso: check-pot-al-dia.py <ruta-repo> [...]")
        return 2
    fallo = False
    for raiz in raices:
        nombre = os.path.basename(os.path.abspath(raiz))
        problemas = revisar(raiz)
        if problemas:
            fallo = True
            print(f"FALLO {nombre}")
            for p in problemas:
                print(f"   {p}")
        else:
            print(f"  OK  {nombre}")
    print()
    print("Los .pot estan al dia." if not fallo else "Hay .pot desfasados: ver arriba.")
    return 1 if fallo else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
