<?php
/**
 * Convoca Observability — Panel de estado en vivo.
 *
 * Consolida el estado de producción:
 *   - Cron programados (26 eventos)
 *   - Licencias (License_Manager)
 *   - Logs por nivel (Logger::get_stats)
 *   - Memoria PHP
 *   - Errores PHP recientes (debug.log)
 *   - Tiempo de respuesta REST (2 endpoints de referencia)
 *
 * Uso:
 *   wp eval-file observability.php --path=/ruta/al/wp
 * Exit 0 = OK, 1 = hay errores críticos (para CI).
 *
 * Cada sección es una FUNCIÓN que devuelve cuántos problemas ha encontrado; el MAIN solo las suma y pinta el
 * resumen. Antes todo el panel estaba a nivel superior y terminaba en `exit()`, así que incluir el fichero lo
 * ejecutaba entero y no se podía probar ninguna comprobación (0 de 89 sentencias cubiertas).
 *
 * Licencia: GPL-2.0-or-later (ver LICENSE). Es una herramienta de desarrollo,
 * no un plugin distribuible: se ejecuta con `wp eval-file`.
 *
 * @package Convoca\HealthCheck
 */

/**
 * Diferencia de segundos en algo legible.
 *
 * @param int $seconds Segundos (pueden ser negativos: retraso).
 * @return string
 */
function human_readable_diff( $seconds ) {
	$abs = abs( $seconds );
	if ( $abs < 60 ) {
		return $seconds . 's';
	}
	if ( $abs < 3600 ) {
		return round( $seconds / 60 ) . 'm';
	}
	if ( $abs < 86400 ) {
		return round( $seconds / 3600 ) . 'h';
	}
	return round( $seconds / 86400 ) . 'd';
}

/**
 * Cron de Convoca: cuántos hay y cuáles van atrasados.
 *
 * Un evento atrasado más de una hora es un problema: el cron del sitio no está corriendo.
 *
 * @return int Problemas encontrados.
 */
function hco_cron(): int {
	echo "\n== CRON ==\n";

	$convoca_events = array();

	foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
		foreach ( (array) $hooks as $hook => $data ) {
			if ( 0 === strpos( (string) $hook, 'convoca' ) ) {
				$convoca_events[] = array( $hook, (int) $timestamp - time(), count( (array) $data ) );
			}
		}
	}

	usort( $convoca_events, static fn( $a, $b ) => $a[1] <=> $b[1] );

	$fails = 0;

	foreach ( $convoca_events as $ev ) {
		$atrasado = $ev[1] < -3600;
		$status   = $atrasado ? '⚠️ ATRASADO' : ( $ev[1] < 0 ? '⚠️ retraso' : 'OK' );

		if ( $atrasado ) {
			++$fails;
		}

		printf( "  [%s] %-42s next=%s\n", $status, $ev[0], human_readable_diff( $ev[1] ) );
	}

	echo '  Total eventos cron Convoca: ' . count( $convoca_events ) . "\n";

	return $fails;
}

/**
 * Licencia del núcleo, si está disponible.
 *
 * @return int Problemas encontrados (aquí solo se informa).
 */
function hco_licencias(): int {
	echo "\n== LICENCIAS ==\n";

	if ( ! class_exists( 'Convoca\\Core\\License_Manager' ) ) {
		echo "  License_Manager no disponible\n";
		return 0;
	}

	echo '  Estado: ' . \Convoca\Core\License_Manager::get_status_label() . "\n";

	$license = \Convoca\Core\License_Manager::get_license();

	if ( is_array( $license ) ) {
		foreach ( $license as $k => $v ) {
			if ( ! is_array( $v ) && ! is_object( $v ) ) {
				echo "  {$k}: " . ( is_bool( $v ) ? var_export( $v, true ) : esc_html( (string) $v ) ) . "\n";
			}
		}
	}

	return 0;
}

/**
 * Logs por nivel: un solo `error` ya es un problema.
 *
 * @return int Problemas encontrados.
 */
function hco_logs(): int {
	echo "\n== LOGS ==\n";

	if ( ! class_exists( 'Convoca\\Core\\Logger' ) ) {
		return 0;
	}

	$stats = \Convoca\Core\Logger::get_stats();

	echo '  Total logs: ' . ( $stats['total'] ?? 0 ) . "\n";

	$fails = 0;

	foreach ( (array) ( $stats['by_level'] ?? array() ) as $level => $count ) {
		$mark = 'error' === $level ? '⚠️' : '';
		echo "  {$mark} {$level}: {$count}\n";

		if ( 'error' === $level && (int) $count > 0 ) {
			++$fails;
		}
	}

	echo '  Tamaño tabla: ' . ( $stats['size_kb'] ?? 0 ) . " KB\n";

	// Top contextos de error: ayuda a distinguir pruebas de producción.
	if ( ! empty( $stats['by_context'] ) && is_array( $stats['by_context'] ) ) {
		echo "  Top contextos:\n";

		foreach ( array_slice( $stats['by_context'], 0, 5 ) as $ctx ) {
			if ( is_array( $ctx ) && isset( $ctx['context'] ) ) {
				echo "    {$ctx['context']}: {$ctx['count']}\n";
			}
		}
	}

	return $fails;
}

/**
 * Memoria PHP: informativo.
 *
 * @return int Siempre 0.
 */
function hco_memoria(): int {
	echo "\n== MEMORIA ==\n";

	printf( "  Uso actual: %.1f MB\n", memory_get_usage( true ) / 1048576 );
	printf( "  Pico: %.1f MB\n", memory_get_peak_usage( true ) / 1048576 );
	printf( "  Límite: %s\n", ini_get( 'memory_limit' ) );

	return 0;
}

/**
 * Errores PHP recientes en `debug.log`.
 *
 * @return int Problemas encontrados.
 */
function hco_errores(): int {
	echo "\n== ERRORES PHP (debug.log) ==\n";

	$debug_log = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '' ) . '/debug.log';

	if ( ! file_exists( $debug_log ) || filesize( $debug_log ) <= 0 ) {
		echo "  debug.log: vacío o no existe ✅\n";
		return 0;
	}

	$size_mb = round( filesize( $debug_log ) / 1048576, 2 );
	echo "  debug.log: {$size_mb} MB\n";

	$php_errors = 0;

	foreach ( array_slice( (array) file( $debug_log ), -10 ) as $line ) {
		if ( preg_match( '/PHP (Fatal|Warning|Notice|Parse|Deprecated)/', (string) $line ) ) {
			++$php_errors;
		}
	}

	echo "  Errores PHP en últimas 10 líneas: {$php_errors}\n";

	if ( $size_mb > 10 ) {
		echo "  ⚠️ debug.log supera 10 MB\n";
		return 1;
	}

	return 0;
}

/**
 * Tiempo de respuesta de los dos endpoints de referencia.
 *
 * Un 200 o un 401 son respuesta viva (401 = pide autenticación, el endpoint existe). Más de 2 s es problema.
 *
 * @return int Problemas encontrados.
 */
function hco_rest(): int {
	echo "\n== REST (tiempo de respuesta) ==\n";

	$pruebas = array(
		array( 'enroll', home_url( '/wp-json/convoca-enroll/v1/actividades' ) ),
		array( 'core', home_url( '/wp-json/convoca/v1/admin/metrics' ) ),
	);

	$fails = 0;

	foreach ( $pruebas as $rt ) {
		$start = microtime( true );
		$r     = wp_remote_get( $rt[1], array( 'timeout' => 10, 'sslverify' => false ) );
		$ms    = round( ( microtime( true ) - $start ) * 1000 );
		$code  = wp_remote_retrieve_response_code( $r );
		$ok    = 200 === $code || 401 === $code;

		printf( "  [%s] %-8s HTTP %s  %d ms\n", $ok ? 'OK' : '⚠️', $rt[0], $code, $ms );

		if ( $ms > 2000 ) {
			echo "  ⚠️  Respuesta lenta (>2s)\n";
			++$fails;
		}
	}

	return $fails;
}

/*
 * ── MAIN ──
 *
 * Se salta al cargar el fichero como BIBLIOTECA (`CONVOCA_HC_LIBRARY`), que es como lo hace la suite. Al
 * ejecutarse con `wp eval-file` el comportamiento es idéntico: la constante no está definida.
 */
if ( ! defined( 'CONVOCA_HC_LIBRARY' ) ) {
	echo "══════════════════════════════════════════\n";
	echo "  CONVOCA OBSERVABILITY\n";
	echo '  ' . home_url() . "\n";
	echo '  ' . date( 'Y-m-d H:i:s' ) . "\n";
	echo "══════════════════════════════════════════\n";

	$fails = hco_cron()
		+ hco_licencias()
		+ hco_logs()
		+ hco_memoria()
		+ hco_errores()
		+ hco_rest();

	echo "\n══════════════════════════════════════════\n";
	printf( "  RESUMEN: %d problemas detectados\n", $fails );
	echo "══════════════════════════════════════════\n";

	if ( $fails > 0 ) {
		exit( 1 );
	}
}
