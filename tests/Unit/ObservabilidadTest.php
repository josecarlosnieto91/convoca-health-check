<?php
/**
 * El panel de estado en vivo (`observability.php`): sus seis comprobaciones, una a una.
 *
 * Este fichero no tenía ni una prueba — 0 de 89 sentencias — porque todo el panel estaba a nivel superior y
 * acababa en `exit()`. Y es justo lo que se mira cuando producción va mal: si el panel miente sobre el cron, los
 * logs o los errores de PHP, no te enteras de que el sitio está roto.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Comprobaciones del panel.
 *
 * @package Convoca\HealthCheck
 */
final class ObservabilidadTest extends TestCase {

	/**
	 * Contenido del `wp-content` de mentira del caso.
	 *
	 * @var string
	 */
	private string $contenido = '';

	/**
	 * Carga el panel como biblioteca y prepara el almacén.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! defined( 'CONVOCA_HC_LIBRARY' ) ) {
			define( 'CONVOCA_HC_LIBRARY', true );
		}

		// Cargado dentro del caso: lo que se incluye en el bootstrap no lo mide el medidor de cobertura.
		require_once dirname( __DIR__, 2 ) . '/observability.php';

		$GLOBALS['hc_test'] = null;
		hc_test_store();
		$GLOBALS['hc_test']['cron']           = array();
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ) );

		$this->contenido = sys_get_temp_dir() . '/hc-obs-' . uniqid( '', true );
		mkdir( $this->contenido, 0777, true );
	}

	/**
	 * Limpia.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$log = $this->contenido . '/debug.log';
		if ( file_exists( $log ) ) {
			unlink( $log );
		}

		// Solo se borra si es el directorio TEMPORAL del caso: cuando el panel usa `WP_CONTENT_DIR`, ese
		// directorio es compartido con otras pruebas y no se toca.
		if ( is_dir( $this->contenido ) && \WP_CONTENT_DIR !== $this->contenido ) {
			rmdir( $this->contenido );
		}
		$GLOBALS['hc_test'] = null;
	}

	/**
	 * Las diferencias de tiempo se escriben en la unidad que se entiende de un vistazo.
	 */
	public function test_las_diferencias_de_tiempo_legibles(): void {
		$this->assertSame( '45s', human_readable_diff( 45 ) );
		$this->assertSame( '5m', human_readable_diff( 300 ) );
		$this->assertSame( '2h', human_readable_diff( 7200 ) );
		$this->assertSame( '3d', human_readable_diff( 3 * 86400 ) );
		$this->assertSame( '-2h', human_readable_diff( -7200 ), 'un retraso se lee con su signo' );
	}

	/**
	 * Sin cron de Convoca, se dice que hay cero y no se inventa ningún problema.
	 */
	public function test_sin_cron_de_convoca_no_hay_problemas(): void {
		ob_start();
		$problemas = hco_cron();
		$salida    = (string) ob_get_clean();

		$this->assertSame( 0, $problemas );
		$this->assertStringContainsString( 'Total eventos cron Convoca: 0', $salida );
	}

	/**
	 * El cron de otros plugins no entra en la cuenta: el panel es de Convoca.
	 */
	public function test_solo_se_cuentan_los_cron_de_convoca(): void {
		$GLOBALS['hc_test']['cron'] = array(
			time() + 600 => array( 'convoca_retry' => array( array( 'x' => 1 ) ) ),
			time() + 700 => array( 'wp_version_check' => array( array( 'x' => 1 ) ) ),
			time() + 300 => array( 'convoca_otro' => array( array( 'x' => 1 ) ) ),
		);

		ob_start();
		hco_cron();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'Total eventos cron Convoca: 2', $salida );
		$this->assertStringNotContainsString( 'wp_version_check', $salida );
	}

	/**
	 * Un evento atrasado más de una hora es un problema: el cron no corre.
	 */
	public function test_un_cron_atrasado_mas_de_una_hora_es_un_problema(): void {
		$GLOBALS['hc_test']['cron'] = array(
			time() - 7200 => array( 'convoca_atrasado' => array( array( 'x' => 1 ) ) ),
		);

		ob_start();
		$problemas = hco_cron();
		$salida    = (string) ob_get_clean();

		$this->assertSame( 1, $problemas );
		$this->assertStringContainsString( 'ATRASADO', $salida );
	}

	/**
	 * Un retraso pequeño se avisa pero NO cuenta como problema.
	 */
	public function test_un_retraso_pequeno_avisa_pero_no_cuenta(): void {
		$GLOBALS['hc_test']['cron'] = array(
			time() - 120 => array( 'convoca_retrasado' => array( array( 'x' => 1 ) ) ),
		);

		ob_start();
		$problemas = hco_cron();
		$salida    = (string) ob_get_clean();

		$this->assertSame( 0, $problemas, 'dos minutos de retraso es normal' );
		$this->assertStringContainsString( 'retraso', $salida );
	}

	/**
	 * Los eventos se enseñan ordenados por lo que falta, no por el orden en que llegan.
	 */
	public function test_los_eventos_salen_ordenados_por_cuando_tocan(): void {
		$GLOBALS['hc_test']['cron'] = array(
			time() + 900 => array( 'convoca_lejos' => array( array( 'x' => 1 ) ) ),
			time() + 60  => array( 'convoca_pronto' => array( array( 'x' => 1 ) ) ),
		);

		ob_start();
		hco_cron();
		$salida = (string) ob_get_clean();

		$this->assertLessThan(
			strpos( $salida, 'convoca_lejos' ),
			strpos( $salida, 'convoca_pronto' ),
			'primero lo que toca antes'
		);
	}

	/*
	 * La rama «License_Manager no disponible» NO se prueba: con el doble de integración cargado la clase existe
	 * siempre y `class_exists()` no se puede deshacer dentro de un proceso. Queda como excepción escrita (2
	 * sentencias), igual que la rama equivalente de `hc_register_module` en ext-gateway.
	 */

	/**
	 * Los logs se cuentan por nivel y solo `error` es un problema.
	 */
	public function test_los_logs_por_nivel(): void {
		\Convoca\Core\Logger::$stats = array(
			'total'      => 12,
			'by_level'   => array( 'info' => 10, 'warning' => 1, 'error' => 1 ),
			'size_kb'    => 34,
			'by_context' => array(
				array( 'context' => 'Enroll', 'count' => 5 ),
				array( 'context' => 'Gateway', 'count' => 2 ),
			),
		);

		ob_start();
		$problemas = hco_logs();
		$salida    = (string) ob_get_clean();

		$this->assertSame( 1, $problemas, 'un solo error ya cuenta' );
		$this->assertStringContainsString( 'Total logs: 12', $salida );
		$this->assertStringContainsString( '⚠️ error: 1', $salida );
		$this->assertStringContainsString( 'Tamaño tabla: 34 KB', $salida );
		$this->assertStringContainsString( 'Enroll: 5', $salida, 'los contextos ayudan a distinguir pruebas de producción' );
	}

	/**
	 * Sin errores en el registro, no hay problema.
	 */
	public function test_sin_errores_en_el_registro_no_hay_problema(): void {
		\Convoca\Core\Logger::$stats = array(
			'total'    => 3,
			'by_level' => array( 'info' => 3 ),
			'size_kb'  => 1,
		);

		ob_start();
		$problemas = hco_logs();
		ob_end_clean();

		$this->assertSame( 0, $problemas );
	}

	/**
	 * La memoria se informa (no es un problema, pero tiene que salir).
	 */
	public function test_la_memoria_se_informa(): void {
		ob_start();
		$problemas = hco_memoria();
		$salida    = (string) ob_get_clean();

		$this->assertSame( 0, $problemas );
		$this->assertStringContainsString( 'Uso actual:', $salida );
		$this->assertStringContainsString( 'Pico:', $salida );
		$this->assertStringContainsString( 'Límite:', $salida );
	}

	/**
	 * Sin `debug.log`, el panel dice que está limpio.
	 */
	public function test_sin_debug_log_esta_limpio(): void {
		$this->apuntar_wp_content( $this->contenido );

		ob_start();
		$problemas = hco_errores();
		$salida    = (string) ob_get_clean();

		$this->assertSame( 0, $problemas );
		$this->assertStringContainsString( 'vacío o no existe', $salida );
	}

	/**
	 * Con `debug.log`, se cuentan los errores PHP de las últimas diez líneas.
	 */
	public function test_los_errores_php_del_debug_log(): void {
		$this->apuntar_wp_content( $this->contenido );

		$lineas = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$lineas[] = sprintf( '[%02d] algo irrelevante', $i );
		}
		$lineas[] = '[13] PHP Fatal error: algo roto';
		$lineas[] = '[14] PHP Warning: otro aviso';
		file_put_contents( $this->contenido . '/debug.log', implode( "\n", $lineas ) . "\n" );

		ob_start();
		$problemas = hco_errores();
		$salida    = (string) ob_get_clean();

		$this->assertSame( 0, $problemas, 'un log pequeño no es un problema de tamaño' );
		$this->assertStringContainsString( 'Errores PHP en últimas 10 líneas: 2', $salida );
	}

	/**
	 * Los endpoints se miden y un 401 cuenta como vivo (pide autenticación, el endpoint existe).
	 */
	public function test_los_endpoints_rest_se_miden(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 401 ) );

		ob_start();
		$problemas = hco_rest();
		$salida    = (string) ob_get_clean();

		$this->assertSame( 0, $problemas );
		$this->assertStringContainsString( 'HTTP 401', $salida );
		$this->assertStringContainsString( '[OK]', $salida, 'un 401 es respuesta viva' );
		$this->assertCount( 2, (array) $GLOBALS['hc_test']['http'], 'se piden los dos endpoints de referencia' );
	}

	/**
	 * Un error del endpoint se marca, y las dos peticiones llevan tiempo límite.
	 */
	public function test_un_endpoint_que_no_responde_se_marca(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 500 ) );

		ob_start();
		hco_rest();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'HTTP 500', $salida );
		$this->assertStringContainsString( '⚠️', $salida );
		$this->assertSame( 10, $GLOBALS['hc_test']['http'][0]['args']['timeout'] );
	}

	/**
	 * Apunta `WP_CONTENT_DIR` a un directorio del caso.
	 *
	 * `WP_CONTENT_DIR` es una constante y no se puede redefinir: en vez de pelearse con ella, el panel la lee y
	 * aquí se comprueba con un `debug.log` de verdad en el directorio que la constante ya apunta. Si no coinciden,
	 * la prueba lo dice en vez de pasar por casualidad.
	 *
	 * @param string $dir Directorio.
	 * @return void
	 */
	private function apuntar_wp_content( string $dir ): void {
		if ( \WP_CONTENT_DIR !== $dir ) {
			// El panel usa la constante; se escribe en el directorio que la constante indica.
			if ( ! is_dir( \WP_CONTENT_DIR ) ) {
				mkdir( \WP_CONTENT_DIR, 0777, true );
			}
			$this->contenido = \WP_CONTENT_DIR;
			$log             = $this->contenido . '/debug.log';
			if ( file_exists( $log ) ) {
				unlink( $log );
			}
		}
	}
}
