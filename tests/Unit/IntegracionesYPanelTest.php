<?php
/**
 * Las integraciones entre plugins y el acceso al panel.
 *
 * Las dos comprobaciones más acopladas al producto, y las dos con un caso que ya pasó en un sitio real:
 *
 *  - **Integraciones**: el flujo Members←Gateway y Enroll←Gateway se comprobaba creando datos de prueba, y dejaba
 *    un usuario de WordPress (`hc.int`) en el sitio que estaba auditando: una demo se llena de basura y en un
 *    sitio real sería una cuenta más con acceso.
 *  - **Acceso al panel**: con convoca-courses 0.2.3 activo, el tipo de contenido declaraba `read_post => 'read'`;
 *    WordPress copiaba ese valor al mapa de capacidades y `/wp-admin` respondía **403 a todo el mundo**,
 *    administrador incluido, mientras la batería daba 27 PASS y 3 FAIL de otra cosa.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use Convoca\Gateway\Payment_Handler;
use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Integraciones y panel.
 *
 * @package Convoca\HealthCheck
 */
final class IntegracionesYPanelTest extends TestCase {

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

		$GLOBALS['hc_test']['posts']                = array();
		$GLOBALS['hc_test']['meta']                 = array();
		$GLOBALS['hc_test']['borrados']             = array();
		$GLOBALS['hc_test']['usuarios']             = array();
		$GLOBALS['hc_test']['usuarios_wp']          = array();
		$GLOBALS['hc_test']['usuarios_borrados']    = array();
		$GLOBALS['hc_test']['rutas_rest']           = array();
		$GLOBALS['hc_test']['post_types_objetos']   = array();
		$GLOBALS['hc_test']['mapa_caps']            = array();
		$GLOBALS['hc_test']['puede']                = array();
		$GLOBALS['hc_test']['opciones']             = array( 'active_plugins' => array() );
		$GLOBALS['hc_test']['plugins_activos']      = array();
		$GLOBALS['hc_test']['respuesta_http']       = array( 'response' => array( 'code' => 401 ), 'body' => '' );

		Payment_Handler::$crea  = true;
		Payment_Handler::$pagos = array();
		Utils::$locks           = array();
	}

	/**
	 * Limpia.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['hc_test'] = null;
		Payment_Handler::$pagos = array();
	}

	/**
	 * Con todos los plugins activos y sus namespaces registrados, PASS.
	 */
	public function test_los_namespaces_de_los_plugins_activos(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php', 'convoca-members/convoca-members.php' );
		$GLOBALS['hc_test']['rutas_rest']      = array( '/convoca/v1/x', '/convoca-members/v1/me' );

		ob_start();
		hc_integrations();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Integraciones :: 2 namespaces REST', $salida );
	}

	/**
	 * Un namespace que falta es un fallo, y se dice cuál.
	 *
	 * Solo se exigen los de los plugins ACTIVOS: exigirlos todos daba FAIL en un sitio que legítimamente solo tiene
	 * el núcleo.
	 */
	public function test_un_namespace_que_falta_es_un_fallo(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php', 'convoca-members/convoca-members.php' );
		$GLOBALS['hc_test']['rutas_rest']      = array( '/convoca/v1/x' );

		ob_start();
		hc_integrations();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Integraciones :: 2 namespaces REST', $salida );
		$this->assertStringContainsString( 'faltan: convoca-members/v1', $salida );
	}

	/**
	 * Un sitio que solo tiene el núcleo no falla por no tener los namespaces de los demás.
	 */
	public function test_un_sitio_solo_con_el_nucleo_no_falla(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php' );
		$GLOBALS['hc_test']['rutas_rest']      = array( '/convoca/v1/x' );

		ob_start();
		hc_integrations();
		$salida = (string) ob_get_clean();

		// No se afirma «cero FAIL»: los flujos Members←Gateway fallan aquí porque su listener (el producto) no
		// está cargado, y eso es lo correcto — el check no puede dar por buena una integración que no ocurre.
		// Lo que se afirma es lo que esta prueba mide: que los namespaces de los plugins activos están.
		$this->assertStringContainsString( '[PASS] Integraciones :: 1 namespaces REST', $salida );
		$this->assertStringNotContainsString( 'faltan:', $salida );
	}

	/**
	 * En modo safe los flujos NO se ejecutan: en producción no se crean socios ni pagos de prueba.
	 */
	public function test_en_modo_safe_no_se_crean_datos(): void {
		$GLOBALS['hc_safe'] = true;

		ob_start();
		hc_integrations();
		$salida = (string) ob_get_clean();

		$this->assertSame( array(), $GLOBALS['hc_test']['posts'], 'ni un socio de prueba en producción' );
		$this->assertStringContainsString( '[WARN] Integraciones :: Flujos', $salida );
		$this->assertSame( 1, $GLOBALS['hc_warn'] );
	}

	/**
	 * El flujo de Members crea el pago, lanza el hook con sus argumentos y LIMPIA lo que creó.
	 */
	public function test_el_flujo_de_members_lanza_el_hook_y_limpia(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php' );
		$GLOBALS['hc_test']['rutas_rest']      = array( '/convoca/v1/x' );

		ob_start();
		hc_integrations();
		$salida = (string) ob_get_clean();

		// Se ha pedido un pago con el importe y el concepto correctos.
		$this->assertNotEmpty( Payment_Handler::$pagos );
		$this->assertSame( 'members', Payment_Handler::$pagos[0]['args']['origin'] );
		$this->assertSame( 3000, Payment_Handler::$pagos[0]['args']['amount_cents'] );

		// Lo que importa del flujo: se lanza el hook de pago completado con sus argumentos.
		$lanzados = (array) ( $GLOBALS['hc_test']['hooks_lanzados'] ?? array() );
		$this->assertNotEmpty( $lanzados, 'sin el hook, el producto no se entera del pago' );
		$this->assertSame( 'convoca_gateway_payment_completed', $lanzados[0]['nuevo'] );
		$this->assertSame( 'members', $lanzados[0]['args'][1], 'el origen viaja en el hook' );

		// Y NO da por buena una integración que no ha ocurrido: el listener del producto no está cargado, así
		// que el estado no cambia y el check tiene que decirlo con el estado real, no con un «ok».
		$this->assertStringContainsString( '[FAIL] Integraciones :: Members←Gateway activa membresía', $salida );
		$this->assertStringContainsString( 'estado=pendiente_pago', $salida );

		$this->assertSame( array(), $GLOBALS['hc_test']['posts'], 'no queda contenido de prueba en el sitio' );
	}

	/**
	 * Un pago que falla es un FAIL con el motivo del gateway, no un «sin pago» a secas.
	 */
	public function test_un_pago_que_falla_es_un_fail_con_su_motivo(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php' );
		$GLOBALS['hc_test']['rutas_rest']      = array( '/convoca/v1/x' );
		Payment_Handler::$crea                 = false;

		ob_start();
		hc_integrations();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Integraciones :: Members←Gateway', $salida );
		$this->assertStringContainsString( 'no se pudo crear el pago', $salida );
	}

	/**
	 * El usuario de WordPress que se crea para el socio de prueba SE BORRA.
	 *
	 * Antes no se borraba: la batería dejaba un `hc.int` en el sitio que estaba auditando —una demo se llena de
	 * basura y en un sitio real sería una cuenta más con acceso—.
	 */
	public function test_el_usuario_de_prueba_se_borra(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php' );
		$GLOBALS['hc_test']['rutas_rest']      = array( '/convoca/v1/x' );
		// El producto pone el usuario vinculado al crear el socio; el usuario es el de la prueba.
		$GLOBALS['hc_test']['meta_auto']['miembro'] = array( '_convoca_user_id' => 55 );
		$GLOBALS['hc_test']['usuarios_wp'][55]      = array( 'ID' => 55, 'user_email' => 'hc.int@example.com' );

		ob_start();
		hc_integrations();
		ob_end_clean();

		$this->assertSame( array( 55 ), (array) $GLOBALS['hc_test']['usuarios_borrados'], 'la cuenta de prueba se borra' );
		$this->assertSame( array(), $GLOBALS['hc_test']['posts'], 'y el contenido de prueba también' );
	}

	/**
	 * Pero una cuenta AJENA no se toca, aunque el socio de prueba apunte a ella.
	 *
	 * Solo se borra lo que es nuestro: se comprueba el correo antes de tocar nada.
	 */
	public function test_una_cuenta_ajena_no_se_borra(): void {
		$GLOBALS['hc_test']['plugins_activos'] = array( 'convoca-core/convoca-core.php' );
		$GLOBALS['hc_test']['rutas_rest']      = array( '/convoca/v1/x' );
		$GLOBALS['hc_test']['meta_auto']['miembro'] = array( '_convoca_user_id' => 55 );
		// El usuario 55 es de una persona real, no el de la prueba.
		$GLOBALS['hc_test']['usuarios_wp'][55] = array( 'ID' => 55, 'user_email' => 'persona@example.test' );

		ob_start();
		hc_integrations();
		ob_end_clean();

		$this->assertSame( array(), (array) $GLOBALS['hc_test']['usuarios_borrados'], 'una cuenta real no se toca' );
	}

	/**
	 * Un tipo de contenido con capacidad global como capacidad por entrada CONTAMINA el panel.
	 *
	 * Es el caso del 22-10-2026: `/wp-admin` respondía 403 a todo el mundo, administrador incluido.
	 */
	public function test_una_capacidad_global_por_entrada_es_un_fallo(): void {
		$GLOBALS['hc_test']['post_types_objetos'] = array(
			'curso' => (object) array(
				'name'           => 'curso',
				'cap'            => (object) array( 'read_post' => 'read' ),
				'map_meta_cap'   => true,
			),
		);

		ob_start();
		hc_panel();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Acceso al panel :: Ninguna capacidad por entrada es global', $salida );
		$this->assertStringContainsString( 'CONTAMINA EL PANEL', $salida );
	}

	/**
	 * Con las capacidades bien puestas, PASS y se dice cuántos tipos se revisaron.
	 */
	public function test_las_capacidades_bien_puestas_pasan(): void {
		$GLOBALS['hc_test']['post_types_objetos'] = array(
			'miembro' => (object) array(
				'name'         => 'miembro',
				'cap'          => (object) array( 'read_post' => 'read_miembro' ),
				'map_meta_cap' => true,
			),
		);
		$GLOBALS['hc_test']['usuarios'] = array( (object) array( 'ID' => 1 ) );

		ob_start();
		hc_panel();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Acceso al panel :: Ninguna capacidad por entrada es global (1 tipos revisados)', $salida );
		$this->assertSame( 0, $GLOBALS['hc_fail'] );
	}

	/**
	 * Sin ningún administrador que medir, se salta en vez de dar un FAIL falso.
	 */
	public function test_sin_administrador_se_salta(): void {
		$GLOBALS['hc_test']['post_types_objetos'] = array();
		$GLOBALS['hc_test']['usuarios']           = array();

		ob_start();
		hc_panel();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[SKIP] Acceso al panel :: Un administrador puede entrar en el panel', $salida );
		$this->assertSame( 0, $GLOBALS['hc_fail'] );
	}

	/**
	 * Si `map_meta_cap('read')` devuelve `do_not_allow`, el panel está cerrado para quien administra.
	 */
	public function test_si_read_no_esta_permitido_es_un_fallo(): void {
		$GLOBALS['hc_test']['post_types_objetos'] = array();
		$GLOBALS['hc_test']['usuarios']           = array( (object) array( 'ID' => 1 ) );
		$GLOBALS['hc_test']['mapa_caps']          = array( 'read' => array( 'do_not_allow' ) );

		ob_start();
		hc_panel();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( "[FAIL] Acceso al panel :: map_meta_cap('read') no está bloqueado", $salida );
		$this->assertStringContainsString( 'do_not_allow', $salida, 'y se dice qué devolvió, no solo que falló' );
	}

	/**
	 * Y si quien administra pierde `manage_options`, también.
	 */
	public function test_si_el_administrador_pierde_sus_capacidades_es_un_fallo(): void {
		$GLOBALS['hc_test']['post_types_objetos'] = array();
		$GLOBALS['hc_test']['usuarios']           = array( (object) array( 'ID' => 1 ) );
		$GLOBALS['hc_test']['puede']              = array( 'read' => true, 'manage_options' => false );

		ob_start();
		hc_panel();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Acceso al panel :: Quien administra conserva read y manage_options', $salida );
		$this->assertStringContainsString( 'manage_options=0', $salida );
	}
}
