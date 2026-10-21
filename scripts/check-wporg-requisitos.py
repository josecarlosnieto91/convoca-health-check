#!/usr/bin/env python3
"""Requisitos de wordpress.org, medidos plugin a plugin (sin publicar nada).

Por qué existe: la comprobación de «¿esto se puede publicar?» se hacía a mano y se perdía en cuanto pasaba
la revisión. El CI ya ejecuta Plugin Check (PCP) sobre el paquete limpio, pero PCP no mira todo lo que pide
el directorio: la coherencia del `readme.txt`, la versión de WordPress soportada, el dominio de texto, los
servicios externos declarados, el código ofuscado o los recursos de fuera. Esto lo mide y lo deja en una
tabla.

**No publica nada.** Es un auditor de preparación.

Uso:
    python3 check-wporg-requisitos.py [--repos ~/repos] [--wp-version 7.1] [--json]

Sin `--wp-version`, la última versión de WordPress se consulta a la API oficial; si no hay red, se avisa y se
omite ese punto (no se inventa el dato).
Salida 0 si todos los plugins pasan; 2 si a alguno le falta algo.
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
import urllib.request

PLUGINS = [
    "convoca-core",
    "convoca-members",
    "convoca-enroll",
    "convoca-gateway",
    "convoca-shifts",
    "convoca-courses",
    "convoca-publisher",
    "convoca-assistant",
]

# Los que declaran `Requires Plugins: convoca-core` y los que no deben declararlo (son autónomos).
DEPENDIENTES_DE_CORE = {"convoca-members", "convoca-enroll", "convoca-gateway", "convoca-shifts", "convoca-courses", "convoca-publisher"}
AUTONOMOS = {"convoca-core", "convoca-assistant"}

CABECERAS = ("Plugin Name", "Version", "Requires at least", "Requires PHP", "License", "Text Domain")
SECCIONES_README = ("== Description ==", "== Installation ==", "== Changelog ==")

# Código que el directorio no admite, y llamadas que obligan a declarar servicios externos.

# Ofuscar es esconder CÓDIGO, no descifrar datos. `base64_decode()` a secas aparece en la firma de Redsys,
# en los tokens de OAuth y en el cifrado del publisher: son datos, y señalarlos convertía el auditor en
# ruido (tres falsos positivos en la primera pasada). Lo que se busca es el patrón de ejecución dinámica.
RX_OFUSCADO = re.compile(r"\b(eval|assert|create_function)\s*\(|eval\s*\(\s*base64_decode|gzinflate\s*\(")
RX_SERVICIO = re.compile(r"\bwp_remote_(get|post|request)\s*\(")
RX_ENQUEUE_EXTERNO = re.compile(r"wp_(?:enqueue|register)_(?:script|style)\s*\([^)]*['\"]https?://(?!localhost)")

FALTA = "FALTA"


def aprobado(valor: str) -> bool:
    """¿Este punto está bien? Lo que no aplica («no usa servicios externos») también lo está."""
    return valor == "OK" or valor.startswith(("no ", "sin dato"))


def ultima_wp() -> str | None:
    """Última versión estable de WordPress, según la API oficial."""
    try:
        with urllib.request.urlopen("https://api.wordpress.org/core/version-check/1.7/", timeout=20) as r:
            datos = json.load(r)
        return datos["offers"][0]["current"]
    except Exception:  # noqa: BLE001 — sin red se avisa y se omite el punto, no se inventa.
        return None


def leer(ruta: str) -> str:
    """Contenido de un fichero, o cadena vacía."""
    try:
        with open(ruta, encoding="utf-8", errors="ignore") as fh:
            return fh.read()
    except OSError:
        return ""


def cabecera_php(raiz: str, slug: str) -> dict:
    """Cabeceras del fichero principal."""
    principal = os.path.join(raiz, slug + ".php")
    texto = leer(principal)
    out = {}
    for clave in CABECERAS + ("Requires Plugins",):
        m = re.search(rf"^ \* {re.escape(clave)}:\s*(.+)$", texto, re.MULTILINE)
        out[clave] = m.group(1).strip() if m else FALTA
    return out


def campos_readme(raiz: str) -> dict:
    """Campos del readme.txt."""
    texto = leer(os.path.join(raiz, "readme.txt"))
    out = {}
    for clave in ("Requires at least", "Tested up to", "Requires PHP", "Stable tag", "License"):
        m = re.search(rf"^{re.escape(clave)}:\s*(.+)$", texto, re.MULTILINE)
        out[clave] = m.group(1).strip() if m else FALTA
    out["_texto"] = texto
    return out


def codigo_publicable(raiz: str):
    """Recorre el código que viaja al directorio (sin pruebas, vendor, herramientas)."""
    for base, dirs, ficheros in os.walk(raiz):
        dirs[:] = [d for d in dirs if d not in {"vendor", "node_modules", "tests", "dev-tools", "build", "dist"} and not d.startswith(".")]
        for f in ficheros:
            if f.endswith((".php", ".js")):
                yield os.path.join(base, f)


def auditar(raiz: str, wp_ultima: str | None) -> dict:
    """Audita un plugin y devuelve sus puntos."""
    slug = os.path.basename(raiz.rstrip("/"))
    php = cabecera_php(raiz, slug)
    rm = campos_readme(raiz)
    puntos = {}

    # ── Cabecera: informativa, sólo es fallo si falta ──
    for clave in CABECERAS:
        valor = php[clave]
        puntos[f"cabecera:{clave}"] = "OK" if valor != FALTA else f"falta {clave}"

    # ── Coherencia de versiones (los tres sitios) ──
    puntos["coherencia:stable tag = versión"] = (
        "OK" if rm["Stable tag"] != FALTA and rm["Stable tag"] == php["Version"] else f"{rm['Stable tag']} ≠ {php['Version']}"
    )

    # ── Tested up to contra la última de WordPress ──
    if wp_ultima:
        puntos["tested up to"] = "OK" if rm["Tested up to"] == wp_ultima else f"{rm['Tested up to']} (última: {wp_ultima})"
    else:
        puntos["tested up to"] = "sin dato (sin red)"

    # ── Requires Plugins: los dependientes sí, los autónomos no ──
    declarado = php.get("Requires Plugins", FALTA)
    if slug in DEPENDIENTES_DE_CORE:
        puntos["requires plugins"] = "OK" if "convoca-core" in declarado else "debe declarar convoca-core"
    elif slug in AUTONOMOS:
        puntos["requires plugins"] = "OK" if declarado == FALTA else f"no debe declararlo ({declarado})"
    else:
        puntos["requires plugins"] = "no aplica"

    # ── Dominio de texto = slug ──
    puntos["text domain = slug"] = "OK" if php["Text Domain"] == slug else f"{php['Text Domain']} ≠ {slug}"

    # ── readme: secciones obligatorias ──
    faltan = [s for s in SECCIONES_README if s not in rm["_texto"]]
    puntos["readme: secciones"] = "OK" if not faltan else "faltan " + ", ".join(faltan)

    # ── Licencia: declara y fichero presente ──
    tiene_lic = os.path.exists(os.path.join(raiz, "LICENSE")) or os.path.exists(os.path.join(raiz, "LICENSE.md"))
    puntos["licencia GPL"] = "OK" if php["License"].startswith("GPL") and tiene_lic else f"{php['License']} · fichero LICENSE: {'sí' if tiene_lic else 'no'}"

    # ── Código ofuscado y servicios externos ──
    ofuscado, servicio, enqueue_ext = [], False, []
    for f in codigo_publicable(raiz):
        texto = leer(f)
        rel = os.path.relpath(f, raiz)
        if RX_OFUSCADO.search(texto):
            ofuscado.append(rel)
        if RX_SERVICIO.search(texto):
            servicio = True
        if RX_ENQUEUE_EXTERNO.search(texto):
            enqueue_ext.append(rel)
    puntos["sin código ofuscado"] = "OK" if not ofuscado else "en " + ", ".join(ofuscado[:3])
    puntos["recursos propios (no CDN)"] = "OK" if not enqueue_ext else "enqueue externo en " + ", ".join(enqueue_ext[:3])
    if servicio:
        declarado_servicios = "== External services ==" in rm["_texto"] or "external services" in rm["_texto"].lower()
        puntos["servicios externos"] = "OK" if declarado_servicios else "usa wp_remote_* y no hay sección de servicios externos"
    else:
        puntos["servicios externos"] = "no usa servicios externos"

    # ── Paquete: .distignore que deje fuera lo que el directorio no admite ──
    dist = leer(os.path.join(raiz, ".distignore"))
    if not dist:
        puntos["paquete limpio"] = "no hay .distignore"
    else:
        necesarios = {"tests", "dev-tools", ".htaccess"}
        faltan_d = [n for n in necesarios if n not in dist]
        puntos["paquete limpio"] = "OK" if not faltan_d else "el .distignore no excluye " + ", ".join(sorted(faltan_d))

    return {"slug": slug, "version": php["Version"], "puntos": puntos}


def main() -> int:
    ap = argparse.ArgumentParser(description="Requisitos de wordpress.org por plugin.")
    ap.add_argument("--repos", default=os.path.expanduser("~/repos"), help="dónde están los repos")
    ap.add_argument("--wp-version", default="", help="última versión de WordPress (si no, se consulta)")
    ap.add_argument("--json", action="store_true", help="salida en JSON")
    args = ap.parse_args()

    wp_ultima = args.wp_version or ultima_wp()
    if not wp_ultima:
        print("AVISO: no se ha podido consultar la última versión de WordPress; ese punto se omite.\n")

    resultados = []
    for slug in PLUGINS:
        raiz = os.path.join(args.repos, slug)
        if not os.path.isdir(raiz):
            # Un plugin que no está NO aprueba: antes decía «no encontrado» y el filtro de «lo que no
            # aplica» lo contaba como bueno. Lo cazó el negativo.
            resultados.append({"slug": slug, "version": "?", "puntos": {"plugin": f"{FALTA} (no está en la ruta)"}})
            continue
        resultados.append(auditar(raiz, wp_ultima))

    if args.json:
        print(json.dumps(resultados, ensure_ascii=False, indent=2))
        return 0

    fallos = 0
    for r in resultados:
        pendientes = [k for k, v in r["puntos"].items() if not aprobado(v)]
        estado = "OK" if not pendientes else f"{len(pendientes)} punto(s) a revisar"
        print(f"== {r['slug']} ({r['version']}) → {estado}")
        for k, v in r["puntos"].items():
            print(f"{'  ok  ' if aprobado(v) else '  >>  '}{k}: {v}")
        print()
        fallos += len(pendientes)

    print(f"RESULTADO: {fallos} punto(s) a revisar en {len(resultados)} plugins")
    return 0 if fallos == 0 else 2


if __name__ == "__main__":
    sys.exit(main())
