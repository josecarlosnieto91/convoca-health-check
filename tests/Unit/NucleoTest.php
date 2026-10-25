<?php
/**
 * La comprobación del núcleo, incluido el contrato del rate limiter.
 *
 * Lo que se mide aquí **no** es si el limitador del núcleo funciona: es si el health check **se da cuenta** cuando
 * no funciona. Un check que pasa con el producto roto no sirve de nada, y este en concreto ya dio un falso aviso
 * una vez (16-10-2026, con el núcleo en la demo: pedía el tope de conexión con una llamada sin identidad y leía
 * «el 4.º debería bloquearse» cuando el contrato del núcleo es distinto).
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Comprobación del núcleo.
 *
 * @package Convoca\HealthCheck
 */
final class NucleoTest extends TestCase {

	/**
	 * Directorio del plugin del núcleo.
	 *
	 * @var string
	 */
	private string $includes = '';

	/**
	 * Carga la herramienta, siembra los ficheros del núcleo y limpia el estado.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! defined( 'CONVOCA_HC_LIBRARY' ) ) {
			define( 'CONVOCA_HC_LIBRARY', true );
		}

		require_once dirname( __DIR__, 2 ) . '/health-check.php';

		$GLOBALS['hc_pass']    = 0;
		$GLOBALS['hc_fail']    = 0;
		$GLOBALS['hc_warn']    = 0;
		$GLOBALS['hc_skip']    = 0;
		$GLOBALS['hc_results'] = array();

		// El almacén se crea ANTES de escribir en él: si no, el array nace sin las claves base y `home_url()`
		// avisa de «undefined array key».
		$GLOBALS['hc_test'] = null;
		hc_test_store();
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 401 ) );
		$GLOBALS['hc_test']['cache_borrada']  = array();
		$GLOBALS['wpdb']                      = new \wpdb();

		Utils::$bloquea          = true;
		Utils::$contadores       = array();
		Utils::$locks            = array();
		Utils::$locks_exclusivos = true;

		$this->includes = WP_PLUGIN_DIR . '/convoca-core/includes';
		if ( ! is_dir( $this->includes ) ) {
			mkdir( $this->includes, 0777, true );
		}

		foreach ( array( 'admin-appearance.php', 'Admin_Backup.php', 'Admin_Setup_Wizard.php' ) as $fichero ) {
			touch( $this->includes . '/' . $fichero );
		}
	}

	/**
	 * Borra los ficheros sembrados.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( array( 'admin-appearance.php', 'Admin_Backup.php', 'Admin_Setup_Wizard.php' ) as $fichero ) {
			if ( file_exists( $this->includes . '/' . $fichero ) ) {
				unlink( $this->includes . '/' . $fichero );
			}
		}
		if ( is_dir( $this->includes ) ) {
			rmdir( $this->includes );
		}
		$GLOBALS['hc_test'] = null;
		unset( $GLOBALS['wpdb'] );
	}

	/**
	 * Con todo en su sitio, no hay un solo FAIL.
	 *
	 * Es la prueba que dice que el check no da falsos avisos: con el limitador cumpliendo su contrato (3 por
	 * persona, el 4.º bloqueado; conexión bloqueada en la 24), con locks exclusivos, con los ficheros y las clases
	 * del núcleo y con `/admin/metrics` pidiendo autenticación.
	 */
	public function test_con_todo_en_su_sitio_no_hay_fallos(): void {
		ob_start();
		hc_core();
		$salida = (string) ob_get_clean();

		$this->assertSame( 0, $GLOBALS['hc_fail'], 'el check no puede dar falsos avisos con el producto bien' );
		$this->assertGreaterThanOrEqual( 8, $GLOBALS['hc_pass'] );
		$this->assertStringContainsString( 'Rate limiter bloquea abuso', $salida );
		$this->assertStringContainsString( 'Locks exclusivos', $salida );
	}

	/**
	 * El detalle del rate limiter enseña el contrato medido, no un «ok».
	 */
	public function test_el_detalle_del_rate_limiter_es_medible(): void {
		ob_start();
		hc_core();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'persona P,P,P,B', $salida, 'tres permitidas y la cuarta bloqueada' );
		$this->assertStringContainsString( 'conexión bloquea en la 24 de 24', $salida );
	}

	/**
	 * Y si el limitador NO bloquea, el check se cae: eso es lo que tiene que detectar.
	 */
	public function test_si_el_limitador_no_bloquea_el_check_falla(): void {
		Utils::$bloquea = false;

		ob_start();
		hc_core();
		$salida = (string) ob_get_clean();

		$this->assertSame( 1, $GLOBALS['hc_fail'], 'un limitador que no limita es un fallo, y hay que verlo' );
		$this->assertStringContainsString( '[FAIL] Core :: Rate limiter bloquea abuso', $salida );
	}

	/**
	 * Si los locks no son exclusivos, también.
	 */
	public function test_si_los_locks_no_son_exclusivos_el_check_falla(): void {
		Utils::$locks_exclusivos = false;

		ob_start();
		hc_core();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Core :: Locks exclusivos', $salida );
	}

	/**
	 * Un fichero del núcleo que falta es un fallo, y se dice cuál.
	 */
	public function test_un_fichero_que_falta_es_un_fallo(): void {
		unlink( $this->includes . '/Admin_Backup.php' );

		ob_start();
		hc_core();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Core :: Backup (Admin_Backup.php)', $salida );
		$this->assertSame( 1, $GLOBALS['hc_fail'] );
	}

	/**
	 * `/admin/metrics` sin protección es un fallo: expone las métricas del sitio.
	 */
	public function test_si_metrics_no_esta_protegido_es_un_fallo(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ) );

		ob_start();
		hc_core();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Core :: REST /admin/metrics protegido', $salida );
		$this->assertStringContainsString( 'HTTP 200', $salida );
	}

	/**
	 * Un 403 también vale: el endpoint está protegido, solo que con otra respuesta.
	 */
	public function test_un_403_tambien_es_proteccion(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 403 ) );

		ob_start();
		hc_core();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Core :: REST /admin/metrics protegido', $salida );
	}

	/**
	 * Los contadores del limitador se BORRAN al terminar.
	 *
	 * El chequeo pasa cada día por la instalación: si dejara sus contadores, la segunda pasada encontraría los
	 * del día anterior y daría un falso positivo por sí misma.
	 */
	public function test_los_contadores_se_limpian_al_terminar(): void {
		ob_start();
		hc_core();
		ob_end_clean();

		$consultas = implode( "\n", (array) $GLOBALS['wpdb']->consultas );

		$this->assertStringContainsString( 'DELETE FROM wp_options', $consultas, 'nada de dejar contadores en la base' );
		$this->assertGreaterThanOrEqual( 4, count( (array) $GLOBALS['wpdb']->consultas ) );
		$this->assertNotEmpty( $GLOBALS['hc_test']['cache_borrada'], 'y la caché del limitador también se limpia' );
	}

	/**
	 * El lock se suelta: sin soltarlo, la segunda ejecución en menos de 30 s da un falso positivo.
	 */
	public function test_el_lock_se_suelta(): void {
		ob_start();
		hc_core();
		ob_end_clean();

		$this->assertArrayNotHasKey( 'hc_lock', Utils::$locks, 'un lock residual haría que la próxima pasada fallara' );
	}
}
