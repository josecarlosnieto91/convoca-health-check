<?php
/**
 * Función de `convoca-shifts` que el health check ejecuta para comprobar que su consulta NO falla.
 *
 * Es integración (una función del plugin que se está vigilando), no producto de esta herramienta. La prueba puede
 * hacer que devuelva datos o que deje un error en `$wpdb->last_error`, que es justo lo que el check mira.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\Shifts;

/**
 * Estadísticas de voluntarios con turnos.
 *
 * @return array
 */
function convoca_shifts_preload_voluntario_stats() {
	global $wpdb;

	if ( ! empty( $GLOBALS['hc_test']['stats_falla'] ) ) {
		$wpdb->last_error = "Unknown column 'a.alias' in 'field list'";
		return array();
	}

	return array( 7 => 3, 9 => 1 );
}
