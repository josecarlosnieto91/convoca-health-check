<?php
/**
 * Dobles de INTEGRACIÓN de `Convoca\Core\Logger` y `Convoca\Core\License_Manager`.
 *
 * Son clases del núcleo: el panel de estado las consulta para informar. Se dobla la integración, nunca el panel.
 * En su propio fichero porque declaran `namespace`.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\Core;

/**
 * Registro del núcleo.
 */
class Logger {

	/**
	 * Estadísticas que devuelve `get_stats()`.
	 *
	 * @var array
	 */
	public static array $stats = array(
		'total'    => 0,
		'by_level' => array(),
		'size_kb'  => 0,
	);

	/**
	 * Estadísticas del registro.
	 *
	 * @return array
	 */
	public static function get_stats(): array {
		return self::$stats;
	}
}

/**
 * Licencias del núcleo.
 */
class License_Manager {

	/**
	 * Licencia que devuelve `get_license()`.
	 *
	 * @var array
	 */
	public static array $licencia = array();

	/**
	 * Estado legible.
	 *
	 * @var string
	 */
	public static string $estado = 'Activa';

	/**
	 * Licencia.
	 *
	 * @return array
	 */
	public static function get_license(): array {
		return self::$licencia;
	}

	/**
	 * Estado legible de la licencia.
	 *
	 * @return string
	 */
	public static function get_status_label(): string {
		return self::$estado;
	}
}
