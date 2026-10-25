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
require_once __DIR__ . '/stubs/class-utils.php';
require_once __DIR__ . '/stubs/class-core-vacias.php';
require_once __DIR__ . '/stubs/funciones-shifts.php';
require_once __DIR__ . '/stubs/class-redsys-client.php';

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

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * Contraseña aleatoria: aquí, un identificador único para que las claves no choquen entre casos.
	 *
	 * @param int  $len          Longitud.
	 * @param bool $especiales   Especiales.
	 * @param bool $extra        Extra.
	 * @return string
	 */
	function wp_generate_password( $len = 12, $especiales = true, $extra = false ) {
		unset( $len, $especiales, $extra );
		return substr( uniqid( '', true ), -8 );
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	/**
	 * Borra de la caché (se apunta).
	 *
	 * @param string $clave Clave.
	 * @param string $grupo Grupo.
	 * @return bool
	 */
	function wp_cache_delete( $clave, $grupo = '' ) {
		hc_test_store();
		$GLOBALS['hc_test']['cache_borrada'][] = array( 'clave' => $clave, 'grupo' => $grupo );
		return true;
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	/**
	 * Base de datos de mentira: apunta las consultas.
	 */
	class wpdb {

		/**
		 * Tabla de opciones.
		 *
		 * @var string
		 */
		public string $options = 'wp_options';

		/**
		 * Consultas ejecutadas.
		 *
		 * @var array
		 */
		public array $consultas = array();

		/**
		 * Último error de la base de datos (WordPress lo rellena; el health check lo mira).
		 *
		 * @var string
		 */
		public string $last_error = '';

		/**
		 * Prepara una consulta.
		 *
		 * @param string $sql    Consulta.
		 * @param mixed  ...$args Argumentos.
		 * @return string
		 */
		public function prepare( $sql, ...$args ) {
			return $sql . ' /* ' . implode( ',', array_map( 'strval', $args ) ) . ' */';
		}

		/**
		 * Ejecuta y apunta.
		 *
		 * @param string $sql Consulta.
		 * @return int
		 */
		public function query( $sql ) {
			$this->consultas[] = $sql;
			return 1;
		}
	}
}

/* ── WordPress: contenido, meta, transitorios y HTTP ─────────────── */

if ( ! function_exists( 'post_type_exists' ) ) {
	/**
	 * ¿Existe el tipo de contenido?
	 *
	 * @param string $tipo Tipo.
	 * @return bool
	 */
	function post_type_exists( $tipo ) {
		hc_test_store();
		return in_array( (string) $tipo, (array) ( $GLOBALS['hc_test']['post_types'] ?? array() ), true );
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	/**
	 * ¿Existe la taxonomía?
	 *
	 * @param string $tax Taxonomía.
	 * @return bool
	 */
	function taxonomy_exists( $tax ) {
		hc_test_store();
		return in_array( (string) $tax, (array) ( $GLOBALS['hc_test']['taxonomias'] ?? array() ), true );
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * Inserta contenido y devuelve su id (0 si se fuerza el fallo).
	 *
	 * @param array $args Argumentos.
	 * @return int
	 */
	function wp_insert_post( $args = array() ) {
		hc_test_store();

		if ( ! empty( $GLOBALS['hc_test']['insert_falla'] ) ) {
			return 0;
		}

		$id = (int) ( $GLOBALS['hc_test']['siguiente_id'] ?? 1000 ) + 1;
		$GLOBALS['hc_test']['siguiente_id'] = $id;
		$GLOBALS['hc_test']['posts'][ $id ] = array(
			'post_type'   => (string) ( $args['post_type'] ?? 'post' ),
			'post_title'  => (string) ( $args['post_title'] ?? '' ),
			'post_status' => (string) ( $args['post_status'] ?? 'draft' ),
		);

		return $id;
	}

	/**
	 * Borra contenido (se apunta qué se borró).
	 *
	 * @param int  $id    Id.
	 * @param bool $fuerza Fuerza.
	 * @return bool
	 */
	function wp_delete_post( $id, $fuerza = false ) {
		hc_test_store();
		$GLOBALS['hc_test']['borrados'][] = array( 'id' => (int) $id, 'fuerza' => (bool) $fuerza );
		unset( $GLOBALS['hc_test']['posts'][ (int) $id ] );
		return true;
	}

	/**
	 * Tipo de contenido de un id.
	 *
	 * @param int $id Id.
	 * @return string|false
	 */
	function get_post_type( $id ) {
		hc_test_store();
		return $GLOBALS['hc_test']['posts'][ (int) $id ]['post_type'] ?? false;
	}

	/**
	 * Enlace permanente de un id.
	 *
	 * @param int $id Id.
	 * @return string|false
	 */
	function get_permalink( $id ) {
		hc_test_store();
		if ( ! isset( $GLOBALS['hc_test']['posts'][ (int) $id ] ) ) {
			return false;
		}
		return 'https://sitio.example.test/?p=' . (int) $id;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Guarda meta (los escalares se leen como texto, como la base).
	 *
	 * @param int    $id    Id.
	 * @param string $clave Clave.
	 * @param mixed  $valor Valor.
	 * @return bool
	 */
	function update_post_meta( $id, $clave, $valor ) {
		hc_test_store();
		$GLOBALS['hc_test']['meta'][ (int) $id ][ (string) $clave ] = is_scalar( $valor ) ? (string) $valor : $valor;
		return true;
	}

	/**
	 * Lee meta.
	 *
	 * @param int    $id    Id.
	 * @param string $clave Clave.
	 * @param bool   $unico Único.
	 * @return mixed
	 */
	function get_post_meta( $id, $clave = '', $unico = false ) {
		hc_test_store();
		$valor = $GLOBALS['hc_test']['meta'][ (int) $id ][ (string) $clave ] ?? '';
		return $unico ? $valor : array( $valor );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Guarda un transitorio.
	 *
	 * @param string $clave Clave.
	 * @param mixed  $valor Valor.
	 * @param int    $ttl   Segundos.
	 * @return bool
	 */
	function set_transient( $clave, $valor, $ttl = 0 ) {
		hc_test_store();
		$GLOBALS['hc_test']['transients'][ (string) $clave ] = $valor;
		return true;
	}

	/**
	 * Lee un transitorio.
	 *
	 * @param string $clave Clave.
	 * @return mixed
	 */
	function get_transient( $clave ) {
		hc_test_store();
		return $GLOBALS['hc_test']['transients'][ (string) $clave ] ?? false;
	}

	/**
	 * Borra un transitorio.
	 *
	 * @param string $clave Clave.
	 * @return bool
	 */
	function delete_transient( $clave ) {
		hc_test_store();
		unset( $GLOBALS['hc_test']['transients'][ (string) $clave ] );
		return true;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Cuerpo de la respuesta.
	 *
	 * @param array $r Respuesta.
	 * @return string
	 */
	function wp_remote_retrieve_body( $r ) {
		return (string) ( $r['body'] ?? '' );
	}
}

if ( ! function_exists( 'get_privacy_policy_url' ) ) {
	/**
	 * URL de la política de privacidad del sitio.
	 *
	 * @return string
	 */
	function get_privacy_policy_url() {
		hc_test_store();
		return (string) ( $GLOBALS['hc_test']['privacidad_url'] ?? 'https://sitio.example.test/privacidad/' );
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * POST HTTP apuntado, sin red.
	 *
	 * @param string $url  URL.
	 * @param array  $args Argumentos.
	 * @return array
	 */
	function wp_remote_post( $url, $args = array() ) {
		hc_test_store();
		$GLOBALS['hc_test']['http_post'][] = array( 'url' => $url, 'args' => $args );
		return (array) ( $GLOBALS['hc_test']['respuesta_post'] ?? array( 'response' => array( 'code' => 400 ) ) );
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Búsqueda de contenido (devuelve los ids que fija la prueba).
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	function get_posts( $args = array() ) {
		hc_test_store();
		$GLOBALS['hc_test']['buscados'][] = $args;
		return (array) ( $GLOBALS['hc_test']['posts_encontrados'] ?? array() );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Contenido por id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	function get_post( $id ) {
		hc_test_store();
		if ( ! isset( $GLOBALS['hc_test']['posts'][ (int) $id ] ) ) {
			return null;
		}
		$post = $GLOBALS['hc_test']['posts'][ (int) $id ];
		return (object) array(
			'ID'           => (int) $id,
			'post_type'    => $post['post_type'],
			'post_title'   => $post['post_title'],
			'post_content' => (string) ( $GLOBALS['hc_test']['contenido'][ (int) $id ] ?? '' ),
		);
	}
}

if ( ! function_exists( 'has_shortcode' ) ) {
	/**
	 * ¿El contenido lleva ese shortcode?
	 *
	 * @param string $contenido Contenido.
	 * @param string $tag       Shortcode.
	 * @return bool
	 */
	function has_shortcode( $contenido, $tag ) {
		return false !== strpos( (string) $contenido, '[' . $tag );
	}
}
