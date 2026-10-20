#!/usr/bin/env python3
"""Contrato del guardián de higiene: qué caza, qué NO caza y qué hace sin lista.

Por qué existe: el guardián buscaba con `\\b` y daba por limpios los identificadores que de
verdad viajan en el producto (`_nombre_event_price`, `convoca_theme_nombre_url`, `{nombre_url}`),
porque `_` es carácter de palabra y no hay frontera. Medido: un fichero con los seis ganchos del
nombre viejo dentro salía «OK». Al arreglarlo apareció el riesgo contrario — la subcadena pura
caza un nombre corto dentro de un hash base64 por azar (le pasó a un fixture del asistente) —,
así que la frontera es «lo de al lado no es letra ni dígito».

Los nombres de cliente no viven en el repositorio (es público): los pone el entorno. Estas
pruebas usan nombres inventados para no volver a escribir los reales aquí.
"""
from __future__ import annotations

import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

GUARDIAN = Path(__file__).with_name("check-data-hygiene.py")


def ejecutar(raiz: str, clientes: str = "") -> tuple[int, str]:
    entorno = dict(os.environ)
    entorno.pop("CONVOCA_HYGIENE_CLIENTES_FILE", None)
    # HOME aislado: si no, el guardián lee la lista real del puesto de trabajo
    # (~/.convoca/higiene-clientes.txt) y la prueba deja de medir lo que dice medir.
    entorno["HOME"] = tempfile.mkdtemp()
    if clientes:
        entorno["CONVOCA_HYGIENE_CLIENTES"] = clientes
    else:
        entorno.pop("CONVOCA_HYGIENE_CLIENTES", None)
        # Que no lea la lista real del puesto de trabajo: estas pruebas van con la suya.
        entorno["CONVOCA_HYGIENE_CLIENTES_FILE"] = "/no/existe"
    salida = subprocess.run(
        [sys.executable, str(GUARDIAN), raiz],
        capture_output=True, text=True, env=entorno,
    )
    return salida.returncode, salida.stdout + salida.stderr


class GuardianDeHigieneTest(unittest.TestCase):

    def setUp(self) -> None:
        self.dir = tempfile.mkdtemp()

    def escribir(self, nombre: str, contenido: str) -> None:
        Path(self.dir, nombre).write_text(contenido, encoding="utf-8")

    def test_caza_los_identificadores_que_el_boundary_se_comia(self) -> None:
        # Los tres casos reales: gancho con guion bajo, meta key y alias de filtro.
        self.escribir("a.php", "$hook = 'acme_enroll_process_queue';\n")
        self.escribir("b.php", "$m = '_acme_event_price';\n")
        self.escribir("c.php", "$u = apply_filters( 'convoca_theme_acme_url', $u );\n")
        self.escribir("d.txt", "Pega {acme_url} en tu calendario\n")

        codigo, salida = ejecutar(self.dir, clientes="acme")

        self.assertEqual(2, codigo, salida)
        self.assertEqual(4, salida.count("cliente "), salida)

    def test_no_caza_un_nombre_corto_dentro_de_un_hash(self) -> None:
        # Falso positivo real: "ucam" dentro de un base64 de un fixture.
        self.escribir("index.json", '{"hash":"85ebf496af399ae9f7eucamd0a1b2","total":1}\n')

        codigo, salida = ejecutar(self.dir, clientes="ucam")

        self.assertEqual(0, codigo, salida)
        self.assertIn("OK:", salida)

    def test_la_frontera_no_es_una_letra_ni_un_digito(self) -> None:
        self.escribir("e.php", "// pluggable, acmeweb, acme2 y 2acme no son el nombre\n")

        codigo, salida = ejecutar(self.dir, clientes="acme")

        self.assertEqual(0, codigo, salida)

    def test_sin_lista_avisa_y_no_revienta(self) -> None:
        self.escribir("f.php", "$hook = 'acme_enroll_process_queue';\n")

        codigo, salida = ejecutar(self.dir, clientes="")

        # Sin lista no hay nombres que vigilar: ni falla ni se calla.
        self.assertEqual(0, codigo, salida)
        self.assertIn("AVISO: no hay lista de nombres de clientes", salida)

    def test_la_marca_de_excepcion_sigue_valiendo(self) -> None:
        self.escribir(
            "g.php",
            "$u = apply_filters( 'convoca_theme_acme_url', $u ); // convoca-hygiene-ignore: alias publicado.\n",
        )

        codigo, salida = ejecutar(self.dir, clientes="acme")

        self.assertEqual(0, codigo, salida)

    def test_dominios_y_correos_del_producto_tienen_subdominios_permitidos(self) -> None:
        self.escribir("h.php", "// https://docs.getconvoca.app y hola@mg.getconvoca.app\n")

        codigo, salida = ejecutar(self.dir, clientes="")

        self.assertEqual(0, codigo, salida)


if __name__ == "__main__":
    unittest.main(verbosity=2)
