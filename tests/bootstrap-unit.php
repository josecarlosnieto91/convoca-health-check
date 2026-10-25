<?php
/**
 * Bootstrap de las pruebas unitarias.
 *
 * `lib/` es lógica pura y se carga siempre. A partir de aquí se doblan además las pocas funciones de WordPress que
 * necesita `health-check.php` para poder cargarse como **biblioteca** (`CONVOCA_HC_LIBRARY`) y probar sus 27
 * funciones: hasta hoy el repo medía solo `lib/` (78 sentencias) porque incluir el fichero ejecutaba la batería
 * entera y terminaba en `exit()`.
 *
 * Los dobles son de INTEGRACIÓN (funciones de WordPress), no del producto: ninguna función del health check se
 * sustituye.
 *
 * @package Convoca\HealthCheck
 */

require_once dirname( __DIR__ ) . '/lib/cobertura.php';
require_once dirname( __DIR__ ) . '/lib/capacidades.php';

// Doble de integración del núcleo (historial de migraciones): en su propio fichero porque declara namespace.
require_once __DIR__ . '/stubs/class-migration-history.php';
require_once __DIR__ . '/stubs/class-logger-license.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', '/tmp/wp/wp-content/plugins' );
}

/**
 * Almacén de la prueba.
 *
 * @return void
 */
function hc_test_store(): void {
	if ( ! isset( $GLOBALS['hc_test'] ) ) {
		$GLOBALS['hc_test'] = array(
			'plugins_activos' => array(),
			'filtros'         => array(),
			'opciones'        => array(),
			'rutas_rest'      => array(),
			'entorno'         => 'local',
			'home'            => 'https://sitio.example.test',
		);
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * URL del sitio.
	 *
	 * @param string $ruta Ruta.
	 * @return string
	 */
	function home_url( $ruta = '' ) {
		hc_test_store();
		return $GLOBALS['hc_test']['home'] . $ruta;
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	/**
	 * Entorno de WordPress.
	 *
	 * @return string
	 */
	function wp_get_environment_type() {
		hc_test_store();
		return $GLOBALS['hc_test']['entorno'];
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Engancha un filtro (se apunta).
	 *
	 * @param string   $hook     Gancho.
	 * @param callable $callback Devolución.
	 * @param int      $prio     Prioridad.
	 * @param int      $args     Argumentos.
	 * @return bool
	 */
	function add_filter( $hook, $callback, $prio = 10, $args = 1 ) {
		hc_test_store();
		$GLOBALS['hc_test']['filtros'][ $hook ][] = array( $callback, $prio, $args );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Aplica los filtros enganchados.
	 *
	 * @param string $hook  Gancho.
	 * @param mixed  $valor Valor.
	 * @return mixed
	 */
	function apply_filters( $hook, $valor ) {
		hc_test_store();
		foreach ( (array) ( $GLOBALS['hc_test']['filtros'][ $hook ] ?? array() ) as $filtro ) {
			$valor = call_user_func( $filtro[0], $valor );
		}
		return $valor;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Opción del sitio (o el valor por defecto, como WordPress).
	 *
	 * @param string $nombre  Nombre.
	 * @param mixed  $defecto Valor por defecto.
	 * @return mixed
	 */
	function get_option( $nombre, $defecto = false ) {
		hc_test_store();
		return array_key_exists( $nombre, $GLOBALS['hc_test']['opciones'] ) ? $GLOBALS['hc_test']['opciones'][ $nombre ] : $defecto;
	}
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	/**
	 * ¿Está activo el plugin?
	 *
	 * @param string $fichero Fichero del plugin.
	 * @return bool
	 */
	function is_plugin_active( $fichero ) {
		hc_test_store();
		return in_array( $fichero, (array) $GLOBALS['hc_test']['plugins_activos'], true );
	}
}

if ( ! function_exists( 'rest_get_server' ) ) {
	/**
	 * Servidor REST con las rutas de la prueba.
	 *
	 * @return object
	 */
	function rest_get_server() {
		hc_test_store();
		$rutas = array();
		foreach ( (array) $GLOBALS['hc_test']['rutas_rest'] as $ruta ) {
			$rutas[ $ruta ] = array();
		}
		return new class( $rutas ) {
			/**
			 * Rutas.
			 *
			 * @var array
			 */
			private array $rutas;

			/**
			 * Constructor.
			 *
			 * @param array $rutas Rutas.
			 */
			public function __construct( array $rutas ) {
				$this->rutas = $rutas;
			}

			/**
			 * Rutas registradas.
			 *
			 * @return array
			 */
			public function get_routes(): array {
				return $this->rutas;
			}
		};
	}
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/hc-wp-content' );
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escapa HTML.
	 *
	 * @param string $texto Texto.
	 * @return string
	 */
	function esc_html( $texto ) {
		return htmlspecialchars( (string) $texto, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '_get_cron_array' ) ) {
	/**
	 * Cron del sitio (lo fija la prueba).
	 *
	 * @return array
	 */
	function _get_cron_array() {
		hc_test_store();
		return (array) ( $GLOBALS['hc_test']['cron'] ?? array() );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * GET HTTP apuntado, sin red.
	 *
	 * @param string $url  URL.
	 * @param array  $args Argumentos.
	 * @return array
	 */
	function wp_remote_get( $url, $args = array() ) {
		hc_test_store();
		$GLOBALS['hc_test']['http'][] = array( 'url' => $url, 'args' => $args );
		return (array) ( $GLOBALS['hc_test']['respuesta_http'] ?? array( 'response' => array( 'code' => 200 ) ) );
	}

	/**
	 * Código de respuesta.
	 *
	 * @param array $r Respuesta.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $r ) {
		return (int) ( $r['response']['code'] ?? 0 );
	}
}
