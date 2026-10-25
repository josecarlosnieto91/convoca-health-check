<?php
/**
 * Las comprobaciones de Members y Enroll: los tipos de contenido, los shortcodes, los endpoints y los flujos.
 *
 * Los flujos son la parte que importa: el de Members da de alta un socio, lo edita y pide la renovación; el de
 * Enroll crea una actividad, una inscripción y comprueba que la página lleva JSON-LD y **ningún placeholder `%%`**.
 * Si esos flujos dejan basura o mienten, la batería diaria valida un sitio que no es el que se sirve.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Members y Enroll.
 *
 * @package Convoca\HealthCheck
 */
final class ComponentesTest extends TestCase {

	/**
	 * Directorio del plugin de Enroll.
	 *
	 * @var string
	 */
	private string $dir_enroll = '';

	/**
	 * Carga la herramienta, siembra los tipos de contenido y limpia el almacén.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! defined( 'CONVOCA_HC_LIBRARY' ) ) {
			define( 'CONVOCA_HC_LIBRARY', true );
		}

		require_once dirname( __DIR__, 2 ) . '/health-check.php';

		$GLOBALS['hc_pass']              = 0;
		$GLOBALS['hc_fail']              = 0;
		$GLOBALS['hc_warn']              = 0;
		$GLOBALS['hc_skip']              = 0;
		$GLOBALS['hc_results']           = array();
		$GLOBALS['hc_safe']              = false;
		$GLOBALS['hc_local']             = false;
		// El almacén se crea ANTES de escribir en él (si no, nace sin las claves base y `home_url()` avisa).
		$GLOBALS['hc_test'] = null;
		hc_test_store();

		$GLOBALS['hc_test']['posts']     = array();
		$GLOBALS['hc_test']['meta']      = array();
		$GLOBALS['hc_test']['borrados']  = array();
		$GLOBALS['hc_test']['post_types'] = array( 'miembro', 'convoca_documento', 'actividad', 'inscripcion', 'convoca_evaluacion' );
		$GLOBALS['hc_test']['taxonomias'] = array( 'tipo_miembro' );
		$GLOBALS['hc_test']['rutas_rest'] = array();
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 401 ), 'body' => '' );
		$GLOBALS['shortcode_tags']       = array(
			'convoca_mi_area'          => 'cb',
			'convoca_mi_perfil'        => 'cb',
			'convoca_renovar'          => 'cb',
			'convoca_alta_socio'       => 'cb',
			'convoca_voluntariado'     => 'cb',
			'convoca_inscripcion_page' => 'cb',
			'convoca_form_inscripcion' => 'cb',
			'convoca_panel_reservas'   => 'cb',
			'convoca_evaluacion'       => 'cb',
			'convoca_actividad_meta'   => 'cb',
			'convoca_inscripcion_actual' => 'cb',
		);

		$this->dir_enroll = WP_PLUGIN_DIR . '/convoca-enroll';
		foreach ( array( 'includes', 'media' ) as $sub ) {
			if ( ! is_dir( $this->dir_enroll . '/' . $sub ) ) {
				mkdir( $this->dir_enroll . '/' . $sub, 0777, true );
			}
		}
		foreach ( array( 'includes/Google_Calendar.php', 'includes/Google_Photos.php', 'media/class-poster-engine.php', 'includes/Motor_Inscripcion.php' ) as $f ) {
			touch( $this->dir_enroll . '/' . $f );
		}
	}

	/**
	 * Borra lo sembrado.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( array( 'includes/Google_Calendar.php', 'includes/Google_Photos.php', 'media/class-poster-engine.php', 'includes/Motor_Inscripcion.php' ) as $f ) {
			if ( file_exists( $this->dir_enroll . '/' . $f ) ) {
				unlink( $this->dir_enroll . '/' . $f );
			}
		}
		foreach ( array( 'includes', 'media', '' ) as $sub ) {
			$ruta = '' === $sub ? $this->dir_enroll : $this->dir_enroll . '/' . $sub;
			if ( is_dir( $ruta ) ) {
				rmdir( $ruta );
			}
		}
		unset( $GLOBALS['shortcode_tags'], $GLOBALS['_COOKIE'] );
		$GLOBALS['hc_test'] = null;
	}

	/**
	 * Con el plugin instalado y su contrato cumplido, Members no da un solo FAIL.
	 */
	public function test_members_correcto_no_da_fallos(): void {
		ob_start();
		hc_members();
		$salida = (string) ob_get_clean();

		$this->assertSame( 0, $GLOBALS['hc_fail'], 'el check no puede dar falsos avisos' );
		$this->assertStringContainsString( '[PASS] Members :: CPT miembro', $salida );
		$this->assertStringContainsString( '[PASS] Members :: Shortcode convoca_renovar', $salida );
	}

	/**
	 * Un tipo de contenido que falta es un fallo, con su nombre.
	 */
	public function test_un_cpt_que_falta_es_un_fallo(): void {
		$GLOBALS['hc_test']['post_types'] = array( 'convoca_documento' );

		ob_start();
		hc_members();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Members :: CPT miembro', $salida );
	}

	/**
	 * Un shortcode que falta es un fallo: es el contrato con quien lo escribe en una página.
	 */
	public function test_un_shortcode_que_falta_es_un_fallo(): void {
		unset( $GLOBALS['shortcode_tags']['convoca_renovar'] );

		ob_start();
		hc_members();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Members :: Shortcode convoca_renovar', $salida );
	}

	/**
	 * El flujo de socio crea, edita y **borra** lo que creó: la batería pasa cada día por el sitio.
	 */
	public function test_el_flujo_de_socio_no_deja_residuos(): void {
		ob_start();
		hc_members();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Members :: Alta de socio', $salida );
		$this->assertStringContainsString( '[PASS] Members :: Edición dirección', $salida );
		$this->assertNotEmpty( $GLOBALS['hc_test']['borrados'], 'el socio de prueba se borra' );
		$this->assertSame( array(), $GLOBALS['hc_test']['posts'], 'no puede quedar contenido de prueba en el sitio' );
	}

	/**
	 * En modo safe el flujo NO se ejecuta: en producción no se crean socios de prueba.
	 */
	public function test_en_modo_safe_el_flujo_de_socio_no_se_ejecuta(): void {
		$GLOBALS['hc_safe'] = true;

		ob_start();
		hc_members();
		$salida = (string) ob_get_clean();

		$this->assertSame( array(), $GLOBALS['hc_test']['posts'], 'ni un socio de prueba en producción' );
		$this->assertStringContainsString( 'modo safe', $salida );
		$this->assertSame( 1, $GLOBALS['hc_warn'] );
	}

	/**
	 * Enroll con todo en su sitio tampoco da fallos.
	 */
	public function test_enroll_correcto_no_da_fallos(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ), 'body' => '<script type="application/ld+json">{}</script>' );
		$GLOBALS['hc_test']['cron']           = array(
			time() + 60 => array( 'convoca_enroll_reminder' => array( array( 'x' => 1 ) ) ),
			time() + 120 => array( 'convoca_enroll_cleanup' => array( array( 'x' => 1 ) ) ),
			time() + 180 => array( 'convoca_enroll_eval' => array( array( 'x' => 1 ) ) ),
		);

		ob_start();
		hc_enroll();
		$salida = (string) ob_get_clean();

		$this->assertSame( 0, $GLOBALS['hc_fail'], 'con el plugin correcto no puede haber FAIL' );
		$this->assertStringContainsString( '[PASS] Enroll :: Motor_Inscripcion', $salida );
		$this->assertStringContainsString( '[PASS] Enroll :: JSON-LD Event', $salida );
	}

	/**
	 * Un placeholder `%%` que se escapa a la página es un fallo: se lo come el visitante.
	 */
	public function test_un_placeholder_sin_sustituir_es_un_fallo(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '<h1>%%TITULO%%</h1><script type="application/ld+json">{}</script>',
		);

		ob_start();
		hc_enroll();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Enroll :: Sin placeholders %%', $salida );
		$this->assertStringContainsString( '%%TITULO%%', $salida, 'y se dice cuál se ha quedado sin sustituir' );
	}

	/**
	 * Sin JSON-LD, el acto no se anuncia bien en los buscadores: es un fallo de configuración del sitio.
	 */
	public function test_sin_json_ld_es_un_fallo_de_configuracion(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ), 'body' => '<h1>Actividad</h1>' );

		ob_start();
		hc_enroll();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Enroll :: JSON-LD Event', $salida );
	}

	/**
	 * Pocos cron de Enroll es un fallo: sin ellos no salen recordatorios ni limpiezas.
	 */
	public function test_pocos_cron_de_enroll_es_un_fallo(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ), 'body' => 'application/ld+json' );
		$GLOBALS['hc_test']['cron']           = array( time() + 60 => array( 'convoca_enroll_uno' => array( array( 'x' => 1 ) ) ) );

		ob_start();
		hc_enroll();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Enroll :: Cron programados', $salida );
		$this->assertStringContainsString( '1 hooks', $salida );
	}

	/**
	 * El flujo de Enroll crea actividad e inscripción, y las borra al terminar.
	 */
	public function test_el_flujo_de_enroll_no_deja_residuos(): void {
		$GLOBALS['hc_test']['respuesta_http'] = array( 'response' => array( 'code' => 200 ), 'body' => 'application/ld+json' );

		ob_start();
		hc_enroll();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Enroll :: Crear actividad', $salida );
		$this->assertStringContainsString( '[PASS] Enroll :: Inscripción creada', $salida );
		$this->assertCount( 2, (array) $GLOBALS['hc_test']['borrados'], 'se borran la inscripción y la actividad' );
		$this->assertSame( array(), $GLOBALS['hc_test']['posts'] );
	}

	/**
	 * En modo safe, el flujo de Enroll tampoco se ejecuta.
	 */
	public function test_en_modo_safe_el_flujo_de_enroll_no_se_ejecuta(): void {
		$GLOBALS['hc_safe'] = true;

		ob_start();
		hc_enroll();
		ob_end_clean();

		$this->assertSame( array(), $GLOBALS['hc_test']['posts'], 'ni una actividad de prueba en producción' );
	}
}
