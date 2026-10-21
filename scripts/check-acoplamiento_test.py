#!/usr/bin/env python3
"""Contrato del guardián de acoplamiento: qué caza, qué NO caza y qué exige el escape.

Por qué existe: el guardián se escribió a mano y su primera versión dio 273 hallazgos, casi todos falsos
positivos —acusaba a `Convoca\\Core`, que es requisito obligatorio de todos los plugins, y no entendía ni las
guardas tempranas (`if ( ! Features::is_gateway_active() ) { … continue; }`) ni las llamadas partidas en
varias líneas—. Un guardián que grita se ignora; y uno que no grita cuando debe deja pasar el defecto. Estas
pruebas fijan las dos caras: **el negativo de las cuatro reglas** y **los casos legítimos que no debe
señalar**.

Cada caso se construye en un árbol temporal: no depende de los repos reales.
"""
from __future__ import annotations

import subprocess
import sys
import tempfile
import textwrap
import unittest
from pathlib import Path

GUARDIAN = Path(__file__).with_name("check-acoplamiento.py")


def analizar(ficheros: dict, plugin: str, argumentos: tuple = ("--reglas", "R1,R2,R3,R4")) -> tuple:
    """Escribe un plugin de mentira con esos ficheros y devuelve (rc, salida)."""
    with tempfile.TemporaryDirectory() as tmp:
        raiz = Path(tmp) / plugin
        raiz.mkdir()
        for nombre, codigo in ficheros.items():
            destino = raiz / nombre
            destino.parent.mkdir(parents=True, exist_ok=True)
            destino.write_text(textwrap.dedent(codigo), encoding="utf-8")
        # `check=False` a propósito: el código de salida (0 limpio / 2 con hallazgos) es lo que se afirma.
        r = subprocess.run(
            [sys.executable, str(GUARDIAN), str(raiz), *argumentos],
            check=False,
            capture_output=True,
            text=True,
            timeout=120,
        )
        return r.returncode, r.stdout


CABECERA = "<?php\n"


class NegativoDeLasCuatroReglas(unittest.TestCase):
    """Lo que el guardián tiene que cazar, con el defecto tal como existió."""

    def test_r1_crear_un_rol_fuera_de_core(self):
        rc, salida = analizar(
            {"plugin.php": CABECERA + """
                if ( ! get_role( 'voluntario_aprobado' ) ) {
                    add_role( 'voluntario_aprobado', 'Voluntario', array( 'read' => true ) );
                }
            """},
            "convoca-shifts",
        )
        self.assertEqual(2, rc, salida)
        self.assertIn("R1", salida)

    def test_r2_llamar_a_una_clase_de_otro_plugin_sin_comprobar(self):
        # El defecto de members#64: sin Gateway, esto es un fatal y ningún test lo ve.
        rc, salida = analizar(
            {"cron.php": CABECERA + """
                function renovar( $member_id ) {
                    return \\Convoca\\Gateway\\Payment_Handler::get_member_token( $member_id );
                }
            """},
            "convoca-members",
        )
        self.assertEqual(2, rc, salida)
        self.assertIn("R2", salida)

    def test_r3_escribir_un_meta_de_otro_plugin(self):
        rc, salida = analizar(
            {"metas.php": CABECERA + """
                function guardar( $post_id ) {
                    update_post_meta( $post_id, '_id_responsable', 12 );
                }
            """},
            "convoca-members",
        )
        self.assertEqual(2, rc, salida)
        self.assertIn("R3", salida)

    def test_r4_decidir_por_rol_con_la_llamada_partida(self):
        # El caso que se escapaba: `get_users( array( 'role' => …` va en tres líneas.
        rc, salida = analizar(
            {"selector.php": CABECERA + """
                function lista() {
                    return get_users(
                        array(
                            'role'    => 'voluntario_aprobado',
                            'orderby' => 'display_name',
                        )
                    );
                }
            """},
            "convoca-shifts",
        )
        self.assertEqual(2, rc, salida)
        self.assertIn("R4", salida)


class LoQueNoDebeSenalar(unittest.TestCase):
    """Código correcto que un guardián ingenuo acusa (y que se ignoraría si no se corrige)."""

    def test_core_es_obligatorio_y_no_necesita_comprobarse(self):
        rc, salida = analizar(
            {"init.php": CABECERA + """
                \\Convoca\\Core\\Installer::db_init();
                \\Convoca\\Core\\Capabilities::register();
            """},
            "convoca-members",
        )
        self.assertEqual(0, rc, salida)

    def test_class_exists_en_la_misma_linea(self):
        rc, salida = analizar(
            {"auth.php": CABECERA + """
                if ( class_exists( '\\Convoca\\Members\\Member_Auth' ) && \\Convoca\\Members\\Member_Auth::is_authenticated() ) {
                    return true;
                }
            """},
            "convoca-enroll",
        )
        self.assertEqual(0, rc, salida)

    def test_guarda_temprana_cubre_el_resto_del_bloque(self):
        rc, salida = analizar(
            {"cron.php": CABECERA + """
                foreach ( $miembros as $id ) {
                    if ( ! \\Convoca\\Core\\Features::is_gateway_active() ) {
                        \\Convoca\\Core\\Logger::error( 'sin gateway', 'X', $id );
                        \\Convoca\\Core\\Utils::release_lock( $id );
                        continue;
                    }
                    $token = \\Convoca\\Gateway\\Payment_Handler::get_member_token( $id );
                    $cobro = \\Convoca\\Gateway\\Payment_Handler::auto_renew_charge( $id, 100, 'cuota' );
                }
            """},
            "convoca-members",
        )
        self.assertEqual(0, rc, salida)

    def test_las_declaraciones_de_dependencia_de_ajustes_no_son_llamadas(self):
        rc, salida = analizar(
            {"admin/settings.php": CABECERA + """
                $modulos = array(
                    array( 'class' => '\\Convoca\\Gateway\\Payment_Handler', 'nombre' => 'Pasarela' ),
                );
            """},
            "convoca-enroll",
        )
        self.assertEqual(0, rc, salida)

    def test_los_roles_de_personas_fuera_de_members_no_se_crean_por_su_cuenta(self):
        # Members sí puede leer roles: es el dueño de las personas.
        rc, salida = analizar(
            {"selector.php": CABECERA + """
                function lista() { return get_users( array( 'role' => 'voluntario_aprobado' ) ); }
            """},
            "convoca-members",
        )
        self.assertEqual(0, rc, salida)


class EscapeYFases(unittest.TestCase):

    def test_el_escape_exige_motivo(self):
        # Sin motivo escrito, el escape no vale: una excepción sin explicación es deuda disfrazada.
        codigo = CABECERA + """
            // convoca-acoplamiento-ignore: ok
            update_post_meta( $post_id, '_convoca_member_id', 12 );
        """
        rc, salida = analizar({"metas.php": codigo}, "convoca-shifts")
        self.assertEqual(2, rc, "un motivo de dos letras no puede desactivar la regla: " + salida)

    def test_el_escape_con_motivo_si_vale(self):
        codigo = CABECERA + """
            // convoca-acoplamiento-ignore: migración de datos heredados; se copia lo que ya existía.
            update_post_meta( $post_id, '_convoca_member_id', 12 );
        """
        rc, salida = analizar({"metas.php": codigo}, "convoca-shifts")
        self.assertEqual(0, rc, salida)

    def test_una_regla_en_aviso_mide_pero_no_bloquea(self):
        codigo = CABECERA + """
            function lista() { return get_users( array( 'role' => 'monitor_actividad' ) ); }
        """
        rc, salida = analizar({"selector.php": codigo}, "convoca-enroll", ("--reglas", "R1,R2,R3", "--avisos", "R4"))
        self.assertEqual(0, rc, salida)
        self.assertIn("AVISOS", salida)
        self.assertIn("R4", salida)

    def test_una_regla_en_aviso_que_se_pide_como_regla_bloquea(self):
        codigo = CABECERA + """
            function lista() { return get_users( array( 'role' => 'monitor_actividad' ) ); }
        """
        rc, salida = analizar({"selector.php": codigo}, "convoca-enroll", ("--reglas", "R1,R2,R3,R4", "--avisos", "R4"))
        self.assertEqual(2, rc, salida)


if __name__ == "__main__":
    unittest.main(verbosity=2)
