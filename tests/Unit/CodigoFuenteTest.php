<?php
/**
 * Los contratos de hooks y la higiene del código fuente.
 *
 * Las dos miran el CÓDIGO DESPLEGADO, no el sitio en marcha, y las dos tienen trampas que ya han dado falsos
 * avisos:
 *
 *  - **Contratos**: un nombre de hook es un pacto entre dos ficheros — quien lo lanza y quien lo escucha — y el
 *    aviso de deprecación no lo ve ningún lint de un solo plugin. Hay que cruzar los dos lados.
 *  - **Higiene**: el comentario que explica el defecto nombra justo lo que se quiere prohibir, así que hay que
 *    quitar comentarios ANTES de buscar; y la frontera de la palabra no puede ser `\b`, porque `_` es carácter de
 *    palabra: con `\b` no se caza lo que de verdad viaja en el código (`_nombre_event_price`).
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contratos e higiene.
 *
 * @package Convoca\HealthCheck
 */
final class CodigoFuenteTest extends TestCase {

	/**
	 * Raíz de los plugins de mentira del caso.
	 *
	 * @var string
	 */
	private string $raiz = '';

	/**
	 * Carga la herramienta y prepara la raíz de plugins.
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

		$GLOBALS['hc_test'] = null;
		hc_test_store();
		$GLOBALS['hc_test']['opciones'] = array( 'active_plugins' => array() );

		// Los dos checks miran `WP_PLUGIN_DIR` (es su contrato: el código DESPLEGADO), así que los ficheros de
		// prueba se siembran ahí y se retiran al terminar. No se puede usar un directorio propio.
		$this->raiz = WP_PLUGIN_DIR;
		foreach ( array( 'convoca-core', 'convoca-theme' ) as $slug ) {
			$this->borrar( $this->raiz . '/' . $slug );
		}
		if ( ! is_dir( $this->raiz ) ) {
			mkdir( $this->raiz, 0777, true );
		}

		putenv( 'CONVOCA_HYGIENE_CLIENTES' );
		putenv( 'CONVOCA_HYGIENE_CLIENTES_FILE' );
	}

	/**
	 * Borra el árbol y las variables del caso.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( array( 'convoca-core', 'convoca-theme' ) as $slug ) {
			$this->borrar( $this->raiz . '/' . $slug );
		}
		putenv( 'CONVOCA_HYGIENE_CLIENTES' );
		putenv( 'CONVOCA_HYGIENE_CLIENTES_FILE' );
		$GLOBALS['hc_test'] = null;
	}

	/**
	 * Borra un árbol.
	 *
	 * @param string $ruta Ruta.
	 * @return void
	 */
	private function borrar( string $ruta ): void {
		if ( ! is_dir( $ruta ) ) {
			return;
		}
		foreach ( (array) scandir( $ruta ) as $hijo ) {
			if ( '.' === $hijo || '..' === $hijo ) {
				continue;
			}
			$ruta_hijo = $ruta . '/' . $hijo;
			is_dir( $ruta_hijo ) ? $this->borrar( $ruta_hijo ) : unlink( $ruta_hijo );
		}
		rmdir( $ruta );
	}

	/**
	 * Escribe un fichero de plugin.
	 *
	 * @param string $relativa Ruta relativa a la raíz de plugins.
	 * @param string $contenido Contenido.
	 * @return void
	 */
	private function plugin( string $relativa, string $contenido ): void {
		$ruta = $this->raiz . '/' . $relativa;
		if ( ! is_dir( dirname( $ruta ) ) ) {
			mkdir( dirname( $ruta ), 0777, true );
		}
		file_put_contents( $ruta, $contenido );
	}

	/**
	 * Sin plugins de Convoca activos, los contratos se saltan con su motivo.
	 */
	public function test_sin_plugins_de_convoca_se_salta(): void {
		ob_start();
		hc_hooks_contratos();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[SKIP] Contratos :: Oyentes de hooks deprecados', $salida );
		$this->assertSame( 0, $GLOBALS['hc_fail'], 'un sitio sin plugins de Convoca no está roto' );
	}

	/**
	 * Un oyente sobre el nombre VIEJO es un fallo: el aviso de deprecación no lo ve ningún lint de un solo plugin.
	 */
	public function test_un_oyente_sobre_el_nombre_viejo_es_un_fallo(): void {
		$GLOBALS['hc_test']['opciones']['active_plugins'] = array( 'convoca-core/convoca-core.php' );

		$this->plugin(
			'convoca-core/includes/Utils.php',
			"<?php\nUtils::do_action( 'convoca_nuevo', 'convoca_viejo' );\n"
		);
		// El plugin escucha el nombre VIEJO, que es justo lo que hay que cazar.
		$this->plugin( 'convoca-core/includes/Viejo.php', "<?php\nadd_action( 'convoca_viejo', 'algo' );\n" );

		ob_start();
		hc_hooks_contratos();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Contratos :: Sin oyentes internos de hooks deprecados', $salida );
		$this->assertStringContainsString( 'convoca_nuevo', $salida, 'y se dice cuál es el nombre bueno' );
	}

	/**
	 * Si todos escuchan el nombre nuevo, no hay nada que corregir.
	 */
	public function test_si_todos_escuchan_el_nombre_nuevo_todo_bien(): void {
		$GLOBALS['hc_test']['opciones']['active_plugins'] = array( 'convoca-core/convoca-core.php' );

		$this->plugin(
			'convoca-core/includes/Utils.php',
			"<?php\nUtils::do_action( 'convoca_nuevo', 'convoca_viejo' );\n"
		);
		$this->plugin( 'convoca-core/includes/Nuevo.php', "<?php\nadd_action( 'convoca_nuevo', 'algo' );\n" );

		ob_start();
		hc_hooks_contratos();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Contratos :: Sin oyentes internos', $salida );
		$this->assertStringContainsString( '1 pares revisados', $salida );
	}

	/**
	 * El comentario que explica el defecto NO cuenta como hallazgo.
	 *
	 * Es la regla anti-trampa: un comentario con el nombre prohibido puso en rojo la batería en dos sitios.
	 */
	public function test_un_comentario_con_el_nombre_prohibido_no_es_un_hallazgo(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES=cliente-prohibido' );
		$GLOBALS['hc_test']['opciones']['active_plugins'] = array();

		$this->plugin(
			'convoca-core/includes/Algo.php',
			"<?php\n// Aquí no debe aparecer cliente-prohibido\n/* ni aquí cliente-prohibido */\n\$x = 1;\n"
		);

		ob_start();
		hc_clean_code();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Higiene :: Sin cadenas de proyectos reales', $salida );
	}

	/**
	 * Pero en una CADENA sí es un hallazgo: ahí es un defecto de verdad.
	 */
	public function test_una_cadena_con_el_nombre_prohibido_es_un_hallazgo(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES=cliente-prohibido' );

		$this->plugin( 'convoca-core/includes/Algo.php', "<?php\n\$x = 'cliente-prohibido';\n" );

		ob_start();
		hc_clean_code();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Higiene :: Sin cadenas de proyectos reales', $salida );
		$this->assertStringContainsString( 'convoca-core/includes/Algo.php', $salida, 'y se dice en qué fichero' );
	}

	/**
	 * La frontera es «no alfanumérica», no `\b`: `_nombre_event_price` tiene que caer.
	 *
	 * Con `\b` no se cazaba, porque `_` es carácter de palabra y no hay frontera: el nombre viajaba en el código y
	 * el check decía que todo estaba limpio.
	 */
	public function test_el_nombre_pegado_a_guiones_bajos_se_caza(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES=nombre' );

		$this->plugin( 'convoca-core/includes/Algo.php', "<?php\n\$k = '_nombre_event_price';\n" );

		ob_start();
		hc_clean_code();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[FAIL] Higiene :: Sin cadenas de proyectos reales', $salida );
	}

	/**
	 * Y un nombre corto que aparece por azar DENTRO de un hash no es un hallazgo.
	 */
	public function test_un_nombre_corto_dentro_de_un_hash_no_es_hallazgo(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES=ana' );

		$this->plugin( 'convoca-core/includes/Algo.php', "<?php\n\$hash = 'a1b2ana3c4d5e6f7';\n" );

		ob_start();
		hc_clean_code();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Higiene :: Sin cadenas de proyectos reales', $salida );
	}

	/**
	 * La marca `convoca-hygiene-ignore` exime la línea: hay cadenas que son funcionales.
	 */
	public function test_la_marca_de_exencion_funciona(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES=cliente-prohibido' );

		$this->plugin(
			'convoca-core/includes/Algo.php',
			"<?php\n\$dominios[] = 'cliente-prohibido'; // convoca-hygiene-ignore: filtro de dominios real\n"
		);

		ob_start();
		hc_clean_code();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Higiene :: Sin cadenas de proyectos reales', $salida );
	}

	/**
	 * `vendor`, `node_modules` y `build-dir` no se escanean: son dependencias y código compilado.
	 */
	public function test_las_dependencias_no_se_escanean(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES=cliente-prohibido' );

		$this->plugin( 'convoca-core/vendor/paquete/algo.php', "<?php\n\$x = 'cliente-prohibido';\n" );
		$this->plugin( 'convoca-theme/build-dir/app.js', "const x = 'cliente-prohibido';\n" );

		ob_start();
		hc_clean_code();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Higiene :: Sin cadenas de proyectos reales', $salida );
	}

	/**
	 * Sin lista de prohibidos no se vigila nada (y no se inventa).
	 */
	public function test_sin_lista_no_hay_hallazgos(): void {
		$this->plugin( 'convoca-core/includes/Algo.php', "<?php\n\$x = 'cualquier-cosa';\n" );

		ob_start();
		hc_clean_code();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( '[PASS] Higiene :: Sin cadenas de proyectos reales', $salida );
	}
}
