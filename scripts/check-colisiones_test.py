#!/usr/bin/env python3
"""El contrato de colisiones se prueba solo: un positivo y sus negativos.

Un instrumento que sólo sabe decir «OK» no mide: certifica. El guardián de colisiones afirma que dos plugins
no pueden declarar la misma función global ni la misma clase sin espacio de nombres — y hasta el 15/10/2026
lo afirmaba **sin caer** cuando los cuerpos eran idénticos: el resumen decía «Sin colisiones que revientan»
con nombres repetidos delante. Este fichero monta sandboxes con el defecto puesto y comprueba que el guardián
**habla y sale con 1**, y que sigue callándose cuando no hay nada.

Casos:
  1. limpio (cada plugin con lo suyo)                                  → 0, «Sin nombres repetidos»
  2. mismo nombre, cuerpos DISTINTOS (dos cosas con el mismo nombre)   → 1
  3. mismo nombre, cuerpo IDÉNTICO (duplicado literal, que también revienta) → 1
  4. misma CLASE sin namespace en dos plugins                          → 1
  5. el mismo nombre de MÉTODO en dos clases con namespace              → 0 (no es colisión)

Uso: python3 check-colisiones_test.py
Sale 1 si algún caso no se comporta como dice el guardián.
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
import tempfile

GUARDIAN = os.path.join(os.path.dirname(os.path.abspath(__file__)), "check-colisiones.py")

PLUGIN = """<?php
/**
 * Plugin Name: {nombre}
 */
{extra}
"""

FUNCION = "function convoca_compartida() {{ return {cuerpo}; }}"
METODO = "namespace Convoca\\{ns};\nclass ConNombre {{\n\tpublic function convoca_compartida() {{ return {cuerpo}; }}\n}}"

CASOS = (
	(
		"limpio: cada plugin con lo suyo",
		{"uno": "function convoca_uno() { return 1; }", "dos": "function convoca_dos() { return 2; }"},
		0,
		"Sin nombres repetidos",
	),
	(
		"misma funcion global, cuerpos DISTINTOS",
		{"uno": FUNCION.format(cuerpo="1"), "dos": FUNCION.format(cuerpo="2")},
		1,
		"[cuerpos DISTINTOS",
	),
	(
		"misma funcion global, cuerpo IDENTICO (antes NO fallaba)",
		{"uno": FUNCION.format(cuerpo="1"), "dos": FUNCION.format(cuerpo="1")},
		1,
		"[cuerpos IDENTICOS",
	),
	(
		"misma clase sin namespace en dos plugins",
		{"uno": "class Convoca_Duplicada {}", "dos": "class Convoca_Duplicada {}"},
		1,
		"COLISION  clase",
	),
	(
		"mismo nombre de METODO en dos clases con namespace (no es colision)",
		{"uno": METODO.format(ns="Uno", cuerpo="1"), "dos": METODO.format(ns="Dos", cuerpo="2")},
		0,
		"Sin nombres repetidos",
	),
)


def montar(base: str, repos: dict) -> list:
	"""Escribe un plugin por repo y devuelve sus rutas."""
	rutas = []
	for nombre, extra in repos.items():
		directorio = os.path.join(base, "convoca-" + nombre)
		os.makedirs(directorio, exist_ok=True)
		with open(os.path.join(directorio, "convoca-" + nombre + ".php"), "w", encoding="utf-8") as f:
			f.write(PLUGIN.format(nombre=nombre, extra=extra))
		rutas.append(directorio)
	return rutas


def main() -> int:
	fallos = 0

	for titulo, repos, esperado, texto in CASOS:
		base = tempfile.mkdtemp(prefix="hermes-verify-colisiones-")
		try:
			rutas = montar(base, repos)
			proceso = subprocess.run(
				[sys.executable, GUARDIAN, *rutas], capture_output=True, text=True
			)
			salida = proceso.stdout + proceso.stderr
			problemas = []
			if proceso.returncode != esperado:
				problemas.append("sale con " + str(proceso.returncode) + " y deberia salir con " + str(esperado))
			if texto not in salida:
				problemas.append("no dice «" + texto + "»")
			if problemas:
				fallos += 1
				print("FALLO " + titulo + " -> " + "; ".join(problemas))
				print("      salida: " + " | ".join(linea for linea in salida.splitlines() if linea.strip()))
			else:
				print("OK    " + titulo)
		finally:
			shutil.rmtree(base, ignore_errors=True)

	print()
	if fallos:
		print("RESUMEN: " + str(fallos) + " caso(s) en los que el guardian no dice lo que promete.")
		return 1
	print("RESUMEN: " + str(len(CASOS)) + " casos, el guardian habla cuando debe y calla cuando toca.")
	return 0


if __name__ == "__main__":
	sys.exit(main())
