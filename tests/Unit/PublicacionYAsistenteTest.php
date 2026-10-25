<?php
/**
 * Las comprobaciones de Publisher, Assistant y el tema.
 *
 * Tres casos con historia detrás:
 *
 *  - **Publisher**: el check del prefijo miraba solo el CÓDIGO, así que decía PASS mientras la base seguía con
 *    `cp_scheduled_publish`, `cp_retry_failed_posts` y `cp_retry_process` programados, disparándose cada pocos
 *    minutos sin hacer nada (medido en la demo el 12-10-2026). Ahora se miran los eventos PROGRAMADOS.
 *  - **Assistant**: el índice y el rebuild protegido; una búsqueda que responde 200 pero sin resultados no es una
 *    búsqueda que funciona.
 *  - **Tema**: si el sitio usa a propósito otro tema, no puede dar FAIL por no ser el nuestro. Lo que hay que
 *    demostrar ahí es que el sitio sirve.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Publisher, Assistant y tema.
 *
 * @package Convoca\HealthCheck
 */
final class PublicacionYAsistenteTest extends TestCase {

	/**
	 * Directorios sembrados.
	 *
	 * @var array
	 */
	private array $sembrados = array();

	/**
	 * Carga la herramienta y siembra los ficheros que miran los checks.
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
		$GLOBALS['hc_safe']    = false;
		$GLOBALS['hc_local']   = false;

		$GLOBALS['hc_test'] = null;
		hc_test_store();

		$GLOBALS['hc_test']['cron']              = array();
		$GLOBALS['hc_test']['programados']       = array();
		$GLOBALS['hc_test']['desprogramados']    = array();
		$GLOBALS['hc_test']['post_types']        = array( 'convoca_faq', 'convoca_kb' );
		$GLOBALS['hc_test']['taxonomias']        = array( 'convoca_faq_cat', 'convoca_kb_cat' );
		$GLOBALS['hc_test']['posts']             = array();
		$GLOBALS['hc_test']['opciones']          = array( 'active_plugins' => array() );
		$GLOBALS['hc_test']['respuesta_http']    = array( 'response' => array( 'code' => 401 ), 'body' => '<!DOCTYPE html>' );
		$GLOBALS['hc_test']['respuesta_post']    = array( 'response' => array( 'code' => 200 ), 'body' => '{"results":[{"id":1}]}' );
		// La búsqueda responde con resultados; el rebuild tiene que estar PROTEGIDO.
		$GLOBALS['hc_test']['respuesta_post_por_url'] = array(
			'/assistant/rebuild-index' => array( 'response' => array( 'code' => 401 ), 'body' => '' ),
		);
		$GLOBALS['hc_test']['tema']              = 'Convoca';
		$GLOBALS['hc_test']['get_var']           = null;
		$GLOBALS['wpdb']                         = new \wpdb();

		$GLOBALS['shortcode_tags'] = array( 'convoca_assistant' => 'cb' );

		$this->sembrar();
	}

	/**
	 * Crea los ficheros que los checks esperan encontrar.
	 *
	 * @return void
	 */
	private function sembrar(): void {
		$publisher = WP_PLUGIN_DIR . '/convoca-publisher/includes';
		$canales   = $publisher . '/channels';

		$ficheros = array( $publisher, $canales );
		foreach ( $ficheros as $dir ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0777, true );
				$this->sembrados[] = $dir;
			}
		}

		$pub = $publisher . '/class-publisher.php';
		file_put_contents( $pub, "<?php\n// convoca_publisher_async_publish\n" );
		$this->sembrados[] = $pub;

		foreach ( array( 'facebook', 'linkedin', 'twitter', 'tiktok', 'googlemybusiness', 'telegram', 'mastodon' ) as $ch ) {
			$f = $canales . '/class-' . $ch . '.php';
			touch( $f );
			$this->sembrados[] = $f;
		}

		$tema           = \WP_CONTENT_DIR . '/themes/convoca';
		$GLOBALS['hc_test']['dir_tema'] = $tema;
		$GLOBALS['hc_test']['uploads']  = array( 'basedir' => \WP_CONTENT_DIR . '/uploads' );

		if ( ! is_dir( $tema . '/parts' ) ) {
			mkdir( $tema . '/parts', 0777, true );
		}
		if ( ! is_dir( $tema . '/patterns' ) ) {
			mkdir( $tema . '/patterns', 0777, true );
		}
		if ( ! is_dir( $tema . '/assets/js' ) ) {
			mkdir( $tema . '/assets/js', 0777, true );
		}
		file_put_contents( $tema . '/parts/header.html', '<div class="dark-mode-toggle"></div>' );
		touch( $tema . '/theme.json' );
		touch( $tema . '/assets/js/dark-mode.js' );
		for ( $i = 1; $i <= 15; $i++ ) {
			touch( $tema . '/patterns/patron-' . $i . '.php' );
		}

		$indice = \WP_CONTENT_DIR . '/uploads/convoca-assistant';
		if ( ! is_dir( $indice ) ) {
			mkdir( $indice, 0777, true );
		}
		touch( $indice . '/index.json' );
	}

	/**
	 * Borra lo sembrado.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$tema = \WP_CONTENT_DIR . '/themes/convoca';
		foreach ( array( 'parts/header.html', 'theme.json', 'assets/js/dark-mode.js' ) as $f ) {
			@unlink( $tema . '/' . $f );
		}
		for ( $i = 1; $i <= 15; $i++ ) {
			@unlink( $tema . '/patterns/patron-' . $i . '.php' );
		}
		@rmdir( $tema . '/parts' );
		@rmdir( $tema . '/patterns' );
		@rmdir( $tema . '/assets/js' );
		@rmdir( $tema . '/assets' );
		@rmdir( $tema );
		@unlink( \WP_CONTENT_DIR . '/uploads/convoca-assistant/index.json' );
		@rmdir( \WP_CONTENT_DIR . '/uploads/convoca-assistant' );

		foreach ( array_reverse( $this->sembrados ) as $ruta ) {
			is_dir( $ruta ) ? @rmdir( $ruta ) : @unlink( $ruta );
		}
		$this->sembrados = array();

		unset( $GLOBALS['shortcode_tags'], $GLOBALS['wpdb'] );
		$GLOBALS['hc_test'] = null;
	}

	/**
	 * Con los siete canales y el prefijo nuevo, Publisher no da fallos de código.
	 */
	public function test_publisher_correcto_no_da_fallos(): void {
		$GLOBALS['hc_test']['get_var'] = 'wp_convoca_publisher_retry_queue';

		ob_start();
		hc_publisher();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Publisher :: Canales (7/7)', $salida );
		$this->assertStringContainsString( '[PASS] Publisher :: Prefijo convoca_publisher_', $salida );
		$this->assertStringContainsString( '[PASS] Publisher :: Sin eventos cp_ programados', $salida );
	}

	/**
	 * Si falta un canal, se dice cuántos hay.
	 */
	public function test_un_canal_que_falta_se_cuenta(): void {
		unlink( WP_PLUGIN_DIR . '/convoca-publisher/includes/channels/class-mastodon.php' );

		ob_start();
		hc_publisher();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Publisher :: Canales (6/7)', $salida );
	}

	/**
	 * Eventos `cp_` PROGRAMADOS es un fallo, aunque el código esté limpio.
	 *
	 * Es el defecto medido en la demo el 12-10-2026: el check miraba solo los ficheros y daba PASS mientras la base
	 * seguía disparando los ganchos viejos cada pocos minutos.
	 */
	public function test_eventos_cp_programados_es_un_fallo(): void {
		$GLOBALS['hc_test']['get_var'] = 'wp_convoca_publisher_retry_queue';
		$GLOBALS['hc_test']['cron']    = array(
			time() + 60 => array( 'cp_scheduled_publish' => array( array( 'x' => 1 ) ) ),
			time() + 90 => array( 'cp_retry_process' => array( array( 'x' => 1 ) ) ),
		);

		ob_start();
		hc_publisher();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Publisher :: Sin eventos cp_ programados', $salida );
		$this->assertStringContainsString( 'cp_scheduled_publish', $salida, 'y se dice cuáles' );
		$this->assertStringContainsString( 'cp_retry_process', $salida );
	}

	/**
	 * Con `auto_publish` apagado se avisa de que ese camino NO se ha probado (no se finge PASS).
	 */
	public function test_sin_auto_publish_se_avisa(): void {
		ob_start();
		hc_publisher();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[WARN] Publisher :: Publicación automática', $salida );
		$this->assertStringContainsString( 'auto_publish desactivado', $salida );
	}

	/**
	 * Con `auto_publish` encendido se comprueba que el evento se programa, y se LIMPIA.
	 */
	public function test_con_auto_publish_se_programa_y_se_limpia(): void {
		$GLOBALS['hc_test']['opciones']['convoca_publisher_auto_publish'] = true;
		$GLOBALS['hc_test']['programados']['convoca_publisher_async_publish'] = time() + 60;

		ob_start();
		hc_publisher();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Publisher :: Publicación automática async', $salida );
		$this->assertNotEmpty( $GLOBALS['hc_test']['desprogramados'], 'el evento de prueba se desprograma' );
		$this->assertSame( array(), $GLOBALS['hc_test']['posts'], 'y la entrada de prueba se borra' );
	}

	/**
	 * Assistant con el índice y la búsqueda funcionando no da fallos.
	 */
	public function test_assistant_correcto_no_da_fallos(): void {
		ob_start();
		hc_assistant();
		$salida = (string) ob_get_clean();

		// Se enseña la salida si falla, para no tener que adivinar cuál de las cuatro se queja.
		$this->assertSame( 0, $GLOBALS['hc_fail'], 'un sitio correcto no puede dar FAIL: ' . $salida );
		$this->assertStringContainsString( '[PASS] Assistant :: Búsqueda devuelve resultados', $salida );
	}

	/**
	 * Un 200 sin resultados NO es una búsqueda que funciona.
	 */
	public function test_una_busqueda_sin_resultados_es_un_fallo(): void {
		$GLOBALS['hc_test']['respuesta_post'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"results":[]}' );
		$GLOBALS['hc_test']['respuesta_post_por_url'] = array(
			'/assistant/rebuild-index' => array( 'response' => array( 'code' => 401 ), 'body' => '' ),
		);

		ob_start();
		hc_assistant();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Assistant :: Búsqueda devuelve resultados', $salida );
		$this->assertStringContainsString( 'results=0', $salida );
	}

	/**
	 * Si el índice no está, la búsqueda no tiene de dónde sacar nada: es un fallo.
	 */
	public function test_sin_indice_json_es_un_fallo(): void {
		unlink( \WP_CONTENT_DIR . '/uploads/convoca-assistant/index.json' );

		ob_start();
		hc_assistant();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Assistant :: Índice JSON', $salida );
	}

	/**
	 * El rebuild tiene que estar protegido: si responde 200, cualquiera puede tirar el índice.
	 */
	public function test_el_rebuild_tiene_que_estar_protegido(): void {
		$GLOBALS['hc_test']['respuesta_post_por_url'] = array(
			'/assistant/rebuild-index' => array( 'response' => array( 'code' => 200 ), 'body' => '' ),
		);

		ob_start();
		hc_assistant();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Assistant :: Rebuild protegido', $salida );
	}

	/**
	 * Un sitio con tema de tercero NO da FAIL por no ser el nuestro: se salta el tema y se mide que sirve.
	 */
	public function test_un_tema_de_tercero_no_da_fail_del_tema(): void {
		$GLOBALS['hc_test']['tema']           = 'Sculpt';
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ), 'body' => '<!DOCTYPE html><html>' );

		ob_start();
		hc_theme_component();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[SKIP] Theme :: Tema activo no es Convoca', $salida );
		$this->assertStringContainsString( '[PASS] Theme :: Home 200 + HTML (tema de tercero)', $salida );
		$this->assertSame( 0, $GLOBALS['hc_fail'], 'usar otro tema a propósito no es un fallo' );
	}

	/**
	 * Con el tema de Convoca se comprueban sus piezas, y sin el toggle de dark mode es un fallo.
	 */
	public function test_el_tema_de_convoca_se_comprueba(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ), 'body' => '<!DOCTYPE html>' );

		ob_start();
		hc_theme_component();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Theme :: Tema activo Convoca', $salida );
		$this->assertStringContainsString( '[PASS] Theme :: Dark mode toggle en header', $salida );
		$this->assertStringContainsString( '[PASS] Theme :: Block patterns', $salida );
		$this->assertSame( 0, $GLOBALS['hc_fail'] );
	}

	/**
	 * Y si falta el toggle, se caza.
	 */
	public function test_sin_dark_mode_toggle_es_un_fallo(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ), 'body' => '<!DOCTYPE html>' );
		file_put_contents( \WP_CONTENT_DIR . '/themes/convoca/parts/header.html', '<div class="otra-cosa"></div>' );

		ob_start();
		hc_theme_component();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Theme :: Dark mode toggle en header', $salida );
	}
}
