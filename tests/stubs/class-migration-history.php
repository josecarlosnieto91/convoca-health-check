<?php
/**
 * Doble de INTEGRACIÓN de `Convoca\Core\Migration_History`.
 *
 * La clase es del núcleo, no de esta herramienta: el health check la consulta para saber si alguna migración
 * quedó fallida. Se dobla la integración, nunca el producto.
 *
 * Vive en su propio fichero porque un bloque `namespace` no puede convivir con código global en el mismo
 * fichero: el bootstrap de esta herramienta es global, así que el doble va aparte y se requiere desde allí.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\Core;

/**
 * Historial de migraciones.
 */
class Migration_History {

	/**
	 * Entradas del historial.
	 *
	 * @var array
	 */
	public static array $historial = array();

	/**
	 * Devuelve el historial.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		return self::$historial;
	}
}
