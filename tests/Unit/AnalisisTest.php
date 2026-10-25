<?php
/**
 * Las utilidades de análisis: recorrer ficheros, quitar comentarios, leer migraciones y la lista de higiene.
 *
 * Son las piezas de las que dependen las comprobaciones de «código limpio» y «clientes prohibidos». Si recorren de
 * más (vendor, tests) o de menos, el veredicto se calcula sobre otro conjunto de ficheros y nadie se entera.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use Convoca\Core\Migration_History;
use PHPUnit\Framework\TestCase;

/**
 * Utilidades de análisis.
 *
 * @package Convoca\HealthCheck
 */
final class AnalisisTest extends TestCase {

	/**
	 * Directorio de trabajo del caso.
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * Carga la herramienta y prepara un directorio limpio.
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

		$this->dir = sys_get_temp_dir() . '/hc-analisis-' . uniqid( '', true );
		mkdir( $this->dir, 0777, true );

		Migration_History::$historial = array();
	}

	/**
	 * Borra el directorio y las variables de entorno del caso.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->borrar( $this->dir );
		putenv( 'CONVOCA_HYGIENE_CLIENTES' );
		putenv( 'CONVOCA_HYGIENE_CLIENTES_FILE' );
		Migration_History::$historial = array();
	}

	/**
	 * Borra un árbol de directorios.
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
	 * Escribe un fichero dentro del directorio del caso.
	 *
	 * @param string $relativa Ruta relativa.
	 * @param string $contenido Contenido.
	 * @return void
	 */
	private function escribir( string $relativa, string $contenido ): void {
		$ruta = $this->dir . '/' . $relativa;
		if ( ! is_dir( dirname( $ruta ) ) ) {
			mkdir( dirname( $ruta ), 0777, true );
		}
		file_put_contents( $ruta, $contenido );
	}

	/**
	 * Un directorio que no existe no revienta: devuelve vacío.
	 */
	public function test_un_directorio_inexistente_devuelve_vacio(): void {
		$this->assertSame( array(), hc_php_files( $this->dir . '/no-existe' ) );
	}

	/**
	 * Solo se recorren los `.php`, y fuera `vendor`, `node_modules` y `tests`.
	 */
	public function test_solo_los_php_del_producto(): void {
		$this->escribir( 'b.php', '<?php' );
		$this->escribir( 'a.php', '<?php' );
		$this->escribir( 'leeme.txt', 'texto' );
		$this->escribir( 'includes/c.php', '<?php' );
		$this->escribir( 'vendor/dependencia.php', '<?php' );
		$this->escribir( 'node_modules/paquete.php', '<?php' );
		$this->escribir( 'tests/AlgoTest.php', '<?php' );

		$base        = $this->dir;
		$encontrados = array_map(
			static fn( $ruta ) => substr( $ruta, strlen( $base ) + 1 ),
			hc_php_files( $this->dir )
		);

		$this->assertSame(
			array( 'a.php', 'b.php', 'includes/c.php' ),
			$encontrados,
			'ni el .txt, ni las dependencias, ni las pruebas del propio repo'
		);
	}

	/**
	 * Los comentarios se quitan antes de buscar texto prohibido; las cadenas se quedan.
	 *
	 * Es la regla anti-trampa: un comentario que explica el defecto nombra justo lo que se quiere prohibir, y si
	 * contara, la comprobación se dispararía sola con su propia documentación.
	 */
	public function test_se_quitan_los_comentarios_y_se_quedan_las_cadenas(): void {
		$fuente = "<?php\n// cliente-prohibido en un comentario\n/* otro cliente-prohibido */\n/**\n * Docblock con cliente-prohibido\n */\n\$x = 'cliente-prohibido';\n";

		$limpio = hc_strip_php_comments( $fuente );

		$this->assertStringNotContainsString( 'comentario', $limpio, 'el comentario de línea se va' );
		$this->assertStringNotContainsString( 'Docblock', $limpio, 'el docblock también' );
		$this->assertStringContainsString( "'cliente-prohibido'", $limpio, 'pero la cadena se queda: ahí sí es un defecto' );
	}

	/**
	 * Sin migraciones fallidas, la comprobación pasa y dice cuántas hay en el historial.
	 */
	public function test_sin_migraciones_fallidas_pasa(): void {
		Migration_History::$historial = array(
			array( 'plugin' => 'convoca-core', 'from' => '2.3.0', 'to' => '2.3.1', 'status' => 'ok' ),
		);

		ob_start();
		hc_migrations();
		$salida = (string) ob_get_clean();

		$this->assertSame( 1, $GLOBALS['hc_pass'] );
		$this->assertSame( 0, $GLOBALS['hc_fail'] );
		$this->assertStringContainsString( '1 en historial', $salida );
	}

	/**
	 * Una migración fallida es un FAIL, y se dice cuántas.
	 */
	public function test_una_migracion_fallida_es_un_fail(): void {
		Migration_History::$historial = array(
			array( 'plugin' => 'convoca-core', 'from' => '2.3.0', 'to' => '2.3.1', 'status' => 'failed' ),
			array( 'plugin' => 'convoca-members', 'from' => '2.8.0', 'to' => '2.8.1', 'status' => 'failed' ),
		);

		ob_start();
		hc_migrations();
		$salida = (string) ob_get_clean();

		$this->assertSame( 1, $GLOBALS['hc_fail'] );
		$this->assertStringContainsString( '2 fallidas', $salida );
	}

	/**
	 * Del historial se enseñan las tres últimas, con su marca.
	 */
	public function test_se_ensenan_las_tres_ultimas_migraciones(): void {
		Migration_History::$historial = array(
			array( 'plugin' => 'uno', 'from' => '1', 'to' => '2', 'status' => 'ok' ),
			array( 'plugin' => 'dos', 'from' => '1', 'to' => '2', 'status' => 'ok' ),
			array( 'plugin' => 'tres', 'from' => '1', 'to' => '2', 'status' => 'ok' ),
			array( 'plugin' => 'cuatro', 'from' => '1', 'to' => '2', 'status' => 'failed' ),
		);

		ob_start();
		hc_migrations();
		$salida = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'uno', $salida, 'la más antigua no se enseña' );
		$this->assertStringContainsString( 'cuatro', $salida );
		$this->assertStringContainsString( '⚠️', $salida, 'la fallida se marca' );
	}

	/**
	 * Sin lista de clientes no se vigila nada (y se dice, no se calla).
	 */
	public function test_sin_lista_no_hay_clientes_prohibidos(): void {
		$this->assertSame( array(), hc_clientes_prohibidos() );
	}

	/**
	 * La lista se puede dar por variable de entorno, separada por comas y saltos de línea.
	 */
	public function test_la_lista_por_variable_de_entorno(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES=Cliente Uno, cliente-dos' . "\n" . 'cliente-tres' );

		$this->assertSame( array( 'cliente uno', 'cliente-dos', 'cliente-tres' ), hc_clientes_prohibidos() );
	}

	/**
	 * Las líneas que empiezan por `#` son comentarios de la lista, y los repetidos no cuentan dos veces.
	 */
	public function test_los_comentarios_de_la_lista_se_ignoran(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES=# esto es una nota' . "\n" . 'cliente-uno' . "\n" . 'cliente-uno' );

		$this->assertSame( array( 'cliente-uno' ), hc_clientes_prohibidos() );
	}

	/**
	 * Y también se puede dar en un fichero.
	 */
	public function test_la_lista_por_fichero(): void {
		$ruta = $this->dir . '/higiene.txt';
		file_put_contents( $ruta, "cliente-alfa\ncliente-beta\n" );
		putenv( 'CONVOCA_HYGIENE_CLIENTES_FILE=' . $ruta );

		$this->assertSame( array( 'cliente-alfa', 'cliente-beta' ), hc_clientes_prohibidos() );
	}

	/**
	 * Si el fichero que se indica no existe, no se inventa nada.
	 */
	public function test_un_fichero_que_no_existe_no_aporta_lista(): void {
		putenv( 'CONVOCA_HYGIENE_CLIENTES_FILE=' . $this->dir . '/no-existe.txt' );

		$this->assertSame( array(), hc_clientes_prohibidos() );
	}
}
