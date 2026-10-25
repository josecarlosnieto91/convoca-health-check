<?php
/**
 * La infraestructura del health check: contadores, salidas y registro de componentes.
 *
 * Es la mitad del valor de la herramienta: si un FAIL se cuenta como PASS, o si un plugin ausente se cuenta como
 * fallo, el veredicto entero deja de servir. Estas funciones estaban **sin una sola prueba** porque el fichero se
 * auto-ejecutaba al incluirlo y terminaba en `exit()`.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contadores, salidas y componentes.
 *
 * @package Convoca\HealthCheck
 */
final class InfraestructuraTest extends TestCase {

	/**
	 * Carga el health check como biblioteca y limpia los contadores.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! defined( 'CONVOCA_HC_LIBRARY' ) ) {
			define( 'CONVOCA_HC_LIBRARY', true );
		}

		// Se carga AQUÍ y no en el bootstrap: lo que se incluye en el bootstrap corre antes de que el medidor
		// empiece a grabar y sus sentencias salen al 0 %.
		require_once dirname( __DIR__, 2 ) . '/health-check.php';

		$GLOBALS['hc_pass']    = 0;
		$GLOBALS['hc_fail']    = 0;
		$GLOBALS['hc_warn']    = 0;
		$GLOBALS['hc_skip']    = 0;
		$GLOBALS['hc_results'] = array();
		$GLOBALS['hc_local']   = false;
		$GLOBALS['hc_safe']    = false;
		$GLOBALS['hc_test']    = null;
		hc_test_store();
	}

	/**
	 * Limpia.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['hc_test'] = null;
		unset( $GLOBALS['shortcode_tags'] );
	}

	/**
	 * Un PASS sale por pantalla, se apunta y suma.
	 */
	public function test_un_pass_sale_y_suma(): void {
		ob_start();
		hc_out( 'Core', 'algo bien', true, 'detalle' );
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Core :: algo bien (detalle)', $salida );
		$this->assertSame( 1, $GLOBALS['hc_pass'] );
		$this->assertSame( 0, $GLOBALS['hc_fail'] );
		$this->assertSame( 'PASS', $GLOBALS['hc_results'][0]['status'] );
	}

	/**
	 * Un FAIL suma donde toca: confundirlos invalida el veredicto entero.
	 */
	public function test_un_fail_suma_en_los_fallos(): void {
		ob_start();
		hc_out( 'Core', 'algo mal', false, 'roto' );
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Core :: algo mal (roto)', $salida );
		$this->assertSame( 0, $GLOBALS['hc_pass'] );
		$this->assertSame( 1, $GLOBALS['hc_fail'] );
	}

	/**
	 * Sin detalle, el paréntesis no se pinta (la salida se lee en un terminal).
	 */
	public function test_sin_detalle_no_hay_parentesis(): void {
		ob_start();
		hc_out( 'Core', 'sin detalle', true );
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( "Core :: sin detalle\n", $salida );
		$this->assertStringNotContainsString( '()', $salida );
	}

	/**
	 * En modo local, un fallo de CONFIGURACIÓN del sitio es WARN, no FAIL: el sitio de CI no es el de producción.
	 */
	public function test_en_local_un_fallo_de_configuracion_es_warn(): void {
		$GLOBALS['hc_local'] = true;

		ob_start();
		hc_cfg( 'Tema', 'índice', false, 'no hay' );
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[WARN]', $salida );
		$this->assertStringContainsString( '[config sitio]', $salida, 'y se marca de dónde viene la indulgencia' );
		$this->assertSame( 0, $GLOBALS['hc_fail'], 'un fallo de config del sitio no tumba el CI' );
		$this->assertSame( 1, $GLOBALS['hc_warn'] );
	}

	/**
	 * Fuera de modo local, el mismo fallo es FAIL: en producción sí importa.
	 */
	public function test_fuera_de_local_un_fallo_de_configuracion_es_fail(): void {
		ob_start();
		hc_cfg( 'Tema', 'índice', false, 'no hay' );
		ob_end_clean();

		$this->assertSame( 1, $GLOBALS['hc_fail'] );
		$this->assertSame( 0, $GLOBALS['hc_warn'] );
	}

	/**
	 * Y cuando la configuración está bien, es PASS en los dos modos.
	 */
	public function test_la_configuracion_correcta_es_pass(): void {
		$GLOBALS['hc_local'] = true;

		ob_start();
		hc_cfg( 'Tema', 'índice', true );
		ob_end_clean();

		$this->assertSame( 1, $GLOBALS['hc_pass'] );
	}

	/**
	 * Un SKIP no es un maquillaje de FAIL: se cuenta aparte y dice por qué.
	 */
	public function test_el_skip_se_cuenta_aparte(): void {
		ob_start();
		hc_skip( 'Members', 'no aplica', 'plugin ausente' );
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[SKIP] Members :: no aplica (plugin ausente)', $salida );
		$this->assertSame( 1, $GLOBALS['hc_skip'] );
		$this->assertSame( 0, $GLOBALS['hc_pass'] );
		$this->assertSame( 0, $GLOBALS['hc_fail'] );
	}

	/**
	 * La cabecera de sección se pinta con su nombre.
	 */
	public function test_la_seccion_se_pinta(): void {
		ob_start();
		hc_section( 'Convoca Core' );
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '== Convoca Core ==', $salida );
	}

	/**
	 * Un plugin activo por su fichero principal se detecta.
	 */
	public function test_detecta_el_plugin_activo_por_su_fichero(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php' );

		$this->assertTrue( hc_plugin_active( 'convoca-core' ) );
	}

	/**
	 * Y también cuando el fichero principal no se llama igual que la carpeta.
	 */
	public function test_detecta_el_plugin_activo_con_otro_nombre_de_fichero(): void {
		$dir = WP_PLUGIN_DIR . '/convoca-raro';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
		touch( $dir . '/otro-nombre.php' );

		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-raro/otro-nombre.php' );

		$this->assertTrue( hc_plugin_active( 'convoca-raro' ), 'el nombre del fichero principal no tiene por qué ser el del slug' );

		unlink( $dir . '/otro-nombre.php' );
	}

	/**
	 * Un plugin que no está, no está.
	 */
	public function test_un_plugin_inactivo_no_se_detecta(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array();

		$this->assertFalse( hc_plugin_active( 'convoca-que-no-esta' ) );
	}

	/**
	 * Con el plugin activo, el componente se ejecuta.
	 */
	public function test_el_componente_activo_se_ejecuta(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php' );

		$ejecutado = false;
		$fn        = static function () use ( &$ejecutado ) {
			$ejecutado = true;
		};

		ob_start();
		hc_component( 'Convoca Core', 'convoca-core', $fn );
		ob_end_clean();

		$this->assertTrue( $ejecutado );
		$this->assertSame( 0, $GLOBALS['hc_skip'] );
	}

	/**
	 * Y si no está activo, se declara SKIP con su motivo en vez de dar un FAIL falso.
	 */
	public function test_el_componente_inactivo_se_salta(): void {
		$ejecutado = false;
		$fn        = static function () use ( &$ejecutado ) {
			$ejecutado = true;
		};

		ob_start();
		hc_component( 'Convoca Members', 'convoca-members', $fn );
		$salida = (string) ob_get_clean();

		$this->assertFalse( $ejecutado, 'si el plugin no está, su comprobación no se ejecuta' );
		$this->assertSame( 1, $GLOBALS['hc_skip'] );
		$this->assertSame( 0, $GLOBALS['hc_fail'], 'un plugin ausente no puede fallar' );
		$this->assertStringContainsString( 'Plugin no activo', $salida );
	}

	/**
	 * En modo seguro, los flujos con datos de prueba se saltan (y se dice).
	 */
	public function test_en_modo_seguro_los_flujos_se_saltan(): void {
		$GLOBALS['hc_safe'] = true;

		ob_start();
		$saltar = hc_skip_side_effects( 'Members' );
		$salida = (string) ob_get_clean();

		$this->assertTrue( $saltar );
		$this->assertSame( 1, $GLOBALS['hc_warn'] );
		$this->assertStringContainsString( 'modo safe', $salida );
	}

	/**
	 * Y sin modo seguro, no se salta nada.
	 */
	public function test_sin_modo_seguro_no_se_salta_nada(): void {
		$this->assertFalse( hc_skip_side_effects( 'Members' ) );
		$this->assertSame( 0, $GLOBALS['hc_warn'] );
	}

	/**
	 * Los espacios de nombres REST se sacan de las rutas registradas, y solo los de Convoca.
	 */
	public function test_los_namespaces_rest_de_convoca(): void {
		$GLOBALS['hc_test']['rutas_rest'] = array(
			'/convoca/v1/actividades',
			'/convoca/v1/actividades/(?P<id>\d+)',
			'/convoca-enroll/v2/inscripciones',
			'/wp/v2/posts',
			'/otro/v1/cosas',
		);

		$this->assertSame( array( 'convoca/v1', 'convoca-enroll/v2' ), hc_rest_namespaces() );
	}

	/**
	 * Los shortcodes registrados, tal cual: es lo que se comprueba luego contra el contrato.
	 */
	public function test_los_shortcodes_registrados(): void {
		$GLOBALS['shortcode_tags'] = array(
			'convoca_actividades' => 'cb',
			'convoca_inscripcion' => 'cb',
		);

		$this->assertSame( array( 'convoca_actividades', 'convoca_inscripcion' ), hc_shortcodes() );
	}
}
