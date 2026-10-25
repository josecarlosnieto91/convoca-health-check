<?php
/**
 * Doble de INTEGRACIÓN de `Convoca\Core\Utils` (rate limiter y locks del núcleo).
 *
 * El health check comprueba el CONTRATO del limitador, no lo implementa: aquí se dobla la integración y se puede
 * elegir si el limitador se comporta o no, para verificar que la herramienta DETECTA un limitador roto. Eso es lo
 * que hay que probar de un check: que se caiga cuando el producto está mal.
 *
 * Contrato que se simula (el del núcleo 2.3.37+):
 *   - por PERSONA: `$max` permitidos y el siguiente se bloquea;
 *   - por CONEXIÓN (sin identidad): el tope es max( 5*$max, $max + 20 ).
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\Core;

/**
 * Utilidades del núcleo.
 */
class Utils {

	/**
	 * Si es `false`, el limitador no bloquea nunca (simula un limitador roto).
	 *
	 * @var bool
	 */
	public static bool $bloquea = true;

	/**
	 * Contadores por clave.
	 *
	 * @var array
	 */
	public static array $contadores = array();

	/**
	 * Si es `false`, el lock no es exclusivo (simula un lock roto).
	 *
	 * @var bool
	 */
	public static bool $locks_exclusivos = true;

	/**
	 * Locks tomados.
	 *
	 * @var array
	 */
	public static array $locks = array();

	/**
	 * Cuenta una llamada y dice si se permite.
	 *
	 * @param string $accion Acción.
	 * @param int    $max    Tope por persona.
	 * @param int    $ventana Ventana en segundos.
	 * @param string $quien  Identidad (vacío = solo conexión).
	 * @return bool
	 */
	public static function check_rate_limit( string $accion, int $max = 5, int $ventana = 60, string $quien = '' ): bool {
		unset( $ventana );

		$clave = $accion . '|' . strtolower( trim( $quien ) );

		self::$contadores[ $clave ] = (int) ( self::$contadores[ $clave ] ?? 0 ) + 1;

		if ( ! self::$bloquea ) {
			return true;
		}

		// Sin identidad, el tope es el de la CONEXIÓN.
		$tope = '' === $quien ? max( 5 * $max, $max + 20 ) : $max;

		return self::$contadores[ $clave ] <= $tope;
	}

	/**
	 * Toma un lock.
	 *
	 * @param string $nombre Nombre.
	 * @param int    $ttl    Segundos.
	 * @return bool
	 */
	public static function acquire_lock( string $nombre, int $ttl = 30 ): bool {
		unset( $ttl );

		if ( isset( self::$locks[ $nombre ] ) ) {
			return ! self::$locks_exclusivos;
		}

		self::$locks[ $nombre ] = true;

		return true;
	}

	/**
	 * Suelta un lock.
	 *
	 * @param string $nombre Nombre.
	 * @return void
	 */
	public static function release_lock( string $nombre ): void {
		unset( self::$locks[ $nombre ] );
	}
}
