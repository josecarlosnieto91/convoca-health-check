<?php
/**
 * Las comprobaciones de Gateway y Shifts.
 *
 * Las dos tienen casos con historia detrás, y son los que más importa fijar:
 *
 *  - **La clave secreta del Gateway**: el check miraba que empezara por «enc:» y daba PASS sobre una clave de
 *    longitud efectiva 0 — imposible de descifrar, pagos bloqueados — mientras el propio plugin lo avisaba en su
 *    registro. Un check que mira la FORMA y no la CAPACIDAD deja pasar justo el fallo que debería cazar.
 *  - **Las estadísticas de Shifts**: un alias en el `SELECT` sin su `JOIN` no da error de PHP, da error de BASE DE
 *    DATOS; con `WP_DEBUG_DISPLAY` en false no se pinta, la función devuelve vacío y la pantalla sale sin datos.
 *    Lo único que lo delata es `$wpdb->last_error`.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Gateway y Shifts.
 *
 * @package Convoca\HealthCheck
 */
final class PasarelaYTurnosTest extends TestCase {

	/**
	 * Directorio del plugin de Shifts.
	 *
	 * @var string
	 */
	private string $dir_shifts = '';

	/**
	 * Carga la herramienta y deja un sitio coherente.
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

		$GLOBALS['hc_test']['post_types']       = array( 'pago', 'centro_turno' );
		$GLOBALS['hc_test']['taxonomias']       = array( 'convoca_shifts_actividad' );
		$GLOBALS['hc_test']['posts']            = array();
		$GLOBALS['hc_test']['posts_encontrados'] = array();
		$GLOBALS['hc_test']['buscados']         = array();
		$GLOBALS['hc_test']['http']             = array();
		$GLOBALS['hc_test']['http_post']        = array();
		$GLOBALS['hc_test']['respuesta_http']   = array( 'response' => array( 'code' => 200 ), 'body' => '' );
		$GLOBALS['hc_test']['respuesta_post']   = array( 'response' => array( 'code' => 400 ) );
		$GLOBALS['hc_test']['opciones']         = array(
			'convoca_gateway_settings' => array(
				'merchant_code'     => '123456789',
				'environment'       => 'production',
				'secret_key'        => 'enc:clave-cifrada-en-la-base',
				'privacidad_texto'  => 'Información básica sobre protección de datos.',
			),
		);
		$GLOBALS['hc_test']['stats_falla']      = false;
		\Convoca\Gateway\Redsys_Client::$clave = 'clave-descifrada-de-24-caracteres';
		$GLOBALS['wpdb']                        = new \wpdb();

		$GLOBALS['shortcode_tags'] = array(
			'convoca_pago'             => 'cb',
			'convoca_pago_ok'          => 'cb',
			'convoca_pago_ko'          => 'cb',
			'convoca_calendario'       => 'cb',
			'convoca_proximos_turnos'  => 'cb',
			'convoca_resumen_turnos'   => 'cb',
			'convoca_boton_apuntarse'  => 'cb',
		);

		$this->dir_shifts = WP_PLUGIN_DIR . '/convoca-shifts';
		if ( ! is_dir( $this->dir_shifts . '/includes' ) ) {
			mkdir( $this->dir_shifts . '/includes', 0777, true );
		}
		touch( $this->dir_shifts . '/includes/cpt-turno.php' );
	}

	/**
	 * Limpia.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( file_exists( $this->dir_shifts . '/includes/cpt-turno.php' ) ) {
			unlink( $this->dir_shifts . '/includes/cpt-turno.php' );
		}
		if ( is_dir( $this->dir_shifts . '/includes' ) ) {
			rmdir( $this->dir_shifts . '/includes' );
		}
		if ( is_dir( $this->dir_shifts ) ) {
			rmdir( $this->dir_shifts );
		}
		unset( $GLOBALS['shortcode_tags'], $GLOBALS['wpdb'] );
		$GLOBALS['hc_test'] = null;
	}

	/**
	 * Con el Gateway bien configurado no hay un solo FAIL.
	 */
	public function test_gateway_bien_configurado_no_da_fallos(): void {
		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertSame( 0, $GLOBALS['hc_fail'], 'un sitio bien configurado no puede dar FAIL' );
		$this->assertStringContainsString( '[PASS] Gateway :: Merchant code', $salida );
		$this->assertStringContainsString( '[PASS] Gateway :: Aviso de privacidad configurado', $salida );
	}

	/**
	 * El entorno se compara con el SITIO, no con una preferencia fija: en un sitio real se espera `production`.
	 */
	public function test_el_entorno_se_espera_segun_el_sitio(): void {
		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'esperado: production', $salida );
	}

	/**
	 * En la demo lo esperado es `test`, y un sitio real en `test` es un fallo.
	 */
	public function test_en_la_demo_lo_esperado_es_test(): void {
		$GLOBALS['hc_test']['home'] = 'https://demo.example.test';
		$GLOBALS['hc_test']['opciones']['convoca_gateway_settings']['environment'] = 'production';

		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'esperado: test', $salida );
		$this->assertStringContainsString( '[FAIL] Gateway :: Entorno coherente con el sitio', $salida );
	}

	/**
	 * Una clave con el prefijo `enc:` que NO descifra a nada es un fallo.
	 *
	 * Es el defecto que ya pasó: el check miraba la forma y daba PASS sobre una clave imposible de descifrar.
	 */
	public function test_una_clave_que_no_descifra_es_un_fallo(): void {
		$GLOBALS['hc_test']['opciones']['convoca_gateway_settings']['secret_key'] = 'enc:basura';
		\Convoca\Gateway\Redsys_Client::$clave = ''; // El cliente del gateway descifra a nada: pagos bloqueados.

		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Gateway :: Clave secreta utilizable', $salida );
		$this->assertStringContainsString( 'longitud efectiva 0', $salida, 'el detalle dice la longitud de verdad' );
	}

	/**
	 * Sin clave guardada, también es un fallo (no hay nada que descifrar).
	 */
	public function test_sin_clave_guardada_es_un_fallo(): void {
		$GLOBALS['hc_test']['opciones']['convoca_gateway_settings']['secret_key'] = '';

		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Gateway :: Clave secreta utilizable', $salida );
		$this->assertStringContainsString( 'sin guardar', $salida );
	}

	/**
	 * Sin texto de privacidad es un AVISO, no un fallo: el sitio informa con el texto por defecto.
	 */
	public function test_sin_texto_de_privacidad_es_aviso(): void {
		$GLOBALS['hc_test']['opciones']['convoca_gateway_settings']['privacidad_texto'] = '';

		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[WARN] Gateway :: Aviso de privacidad configurado', $salida );
		$this->assertStringContainsString( 'el de por defecto', $salida, 'y se dice que se usa el genérico' );
		$this->assertSame( 0, $GLOBALS['hc_fail'], 'un sitio pendiente de revisar no está roto' );
	}

	/**
	 * Un webhook que no existe (404) sí es un fallo: sin él no llegan las confirmaciones de pago.
	 */
	public function test_un_webhook_que_no_existe_es_un_fallo(): void {
		$GLOBALS['hc_test']['respuesta_post'] = array( 'response' => array( 'code' => 404 ) );

		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Gateway :: Webhook /notify existe', $salida );
		$this->assertStringContainsString( 'HTTP 404', $salida );
	}

	/**
	 * Si el sitio no tiene página de retorno, es SKIP y no un FAIL falso.
	 */
	public function test_sin_pagina_de_retorno_es_skip(): void {
		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[SKIP] Gateway :: Página de retorno [convoca_pago_ok]', $salida );
		$this->assertSame( 0, $GLOBALS['hc_fail'], 'un FAIL falso es peor que no medir' );
	}

	/**
	 * Y si la hay, se comprueba que responde 200.
	 */
	public function test_la_pagina_de_retorno_se_comprueba(): void {
		$GLOBALS['hc_test']['posts'][77]        = array( 'post_type' => 'page', 'post_title' => 'Pago OK', 'post_status' => 'publish' );
		$GLOBALS['hc_test']['contenido'][77]    = '[convoca_pago_ok]';
		$GLOBALS['hc_test']['posts_encontrados'] = array( 77 );

		ob_start();
		hc_gateway();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Gateway :: Página de retorno [convoca_pago_ok]', $salida );
	}

	/**
	 * Shifts con todo en su sitio no da fallos.
	 */
	public function test_shifts_correcto_no_da_fallos(): void {
		ob_start();
		hc_shifts();
		$salida = (string) ob_get_clean();

		$this->assertSame( 0, $GLOBALS['hc_fail'] );
		$this->assertStringContainsString( '[PASS] Shifts :: CPT centro_turno', $salida );
		$this->assertStringContainsString( '[PASS] Shifts :: duplicar_semana', $salida );
	}

	/**
	 * Si la consulta de estadísticas deja error de BASE DE DATOS, el check lo caza.
	 *
	 * Sin `last_error` esto sería invisible: PHP no da error, la función devuelve vacío y la pantalla sale sin datos.
	 */
	public function test_un_error_de_base_de_datos_en_las_estadisticas_es_un_fallo(): void {
		$GLOBALS['hc_test']['stats_falla'] = true;

		ob_start();
		hc_shifts();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Shifts :: Estadísticas de voluntarios se ejecutan', $salida );
		$this->assertStringContainsString( 'Unknown column', $salida, 'y se dice el error, no solo que falló' );
	}

	/**
	 * Y el `last_error` se deja como estaba: el check no puede ensuciar el estado del sitio.
	 */
	public function test_el_last_error_se_restaura(): void {
		$GLOBALS['wpdb']->last_error     = 'error previo del sitio';
		$GLOBALS['hc_test']['stats_falla'] = true;

		ob_start();
		hc_shifts();
		ob_end_clean();

		$this->assertSame( 'error previo del sitio', $GLOBALS['wpdb']->last_error, 'el check no deja su marca' );
	}

	/**
	 * El flujo de turno crea, comprueba el estado y borra.
	 */
	public function test_el_flujo_de_turno_no_deja_residuos(): void {
		ob_start();
		hc_shifts();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Shifts :: CRUD turno + estado válido', $salida );
		$this->assertSame( array(), $GLOBALS['hc_test']['posts'], 'el turno de prueba se borra' );
	}

	/**
	 * En modo safe el flujo de turno no se ejecuta.
	 */
	public function test_en_modo_safe_el_flujo_de_turno_no_se_ejecuta(): void {
		$GLOBALS['hc_safe'] = true;

		ob_start();
		hc_shifts();
		ob_end_clean();

		$this->assertSame( array(), $GLOBALS['hc_test']['posts'] );
	}
}
