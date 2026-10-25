<?php
/**
 * Doble de INTEGRACIÓN de `Convoca\Gateway\Redsys_Client`.
 *
 * El health check pregunta al cliente del gateway por la clave secreta DESCIFRADA: es la única forma de saber si la
 * clave guardada sirve. Sin el plugin cargado no se puede descifrar y el check no suspende por eso; con este doble
 * se puede probar el caso que importa: una clave con el prefijo `enc:` que descifra a vacío (imposible de usar, y
 * ese fue un falso PASS real).
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\Gateway;

/**
 * Cliente de Redsys.
 */
class Redsys_Client {

	/**
	 * Lo que devuelve `secret_key()`.
	 *
	 * @var string
	 */
	public static string $clave = 'clave-descifrada-de-24-caracteres';

	/**
	 * Clave secreta descifrada.
	 *
	 * @return string
	 */
	public static function secret_key(): string {
		return self::$clave;
	}
}
