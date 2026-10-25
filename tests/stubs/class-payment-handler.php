<?php
/**
 * Doble de INTEGRACIÓN de `Convoca\Gateway\Payment_Handler`.
 *
 * Es el otro lado de la integración (el gateway), no el sujeto que se prueba (la herramienta). Lo que la prueba
 * necesita es poder decidir si el pago se crea o falla, y comprobar que la herramienta lanza el hook con los
 * argumentos correctos cuando hay pago.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\Gateway;

/**
 * Gestor de pagos del gateway.
 */
class Payment_Handler {

	/**
	 * Si es `false`, `create_payment` devuelve un error.
	 *
	 * @var bool
	 */
	public static bool $crea = true;

	/**
	 * Pagos creados.
	 *
	 * @var array
	 */
	public static array $pagos = array();

	/**
	 * Crea un pago.
	 *
	 * @param array $args Argumentos.
	 * @return array|\WP_Error
	 */
	public function create_payment( array $args = array() ) {
		// El contrato REAL del gateway, que es lo que el health check comprueba: sin importe o con un origen
		// que no existe, no hay pago. Un doble que creara el pago igual haría que el check diera un fallo que
		// no existe en el producto.
		if ( empty( $args['amount_cents'] ) || (int) $args['amount_cents'] <= 0 ) {
			return new \WP_Error( 'importe_invalido', 'el importe tiene que ser mayor que 0' );
		}

		if ( ! in_array( (string) ( $args['origin'] ?? '' ), array( 'members', 'enroll' ), true ) ) {
			return new \WP_Error( 'origin_invalido', 'origen desconocido: ' . (string) ( $args['origin'] ?? '' ) );
		}

		if ( ! self::$crea ) {
			return new \WP_Error( 'hc_sin_pago', 'no se pudo crear el pago' );
		}

		$id                 = 9000 + count( self::$pagos ) + 1;
		self::$pagos[]      = array( 'pago_id' => $id, 'args' => $args );

		return array( 'pago_id' => $id, 'payment_url' => 'https://pasarela.example.test/' . $id );
	}
}
