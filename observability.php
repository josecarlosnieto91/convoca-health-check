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
 */
echo "══════════════════════════════════════════\n";
echo "  CONVOCA OBSERVABILITY\n";
echo "  " . home_url() . "\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "══════════════════════════════════════════\n";

$fails = 0;

// ── 1. Cron programados ─────────────────────────────
echo "\n== CRON ==\n";
$cron = _get_cron_array();
$convoca_events = array();
foreach ( (array) $cron as $timestamp => $hooks ) {
    foreach ( $hooks as $hook => $data ) {
        if ( strpos( $hook, 'convoca' ) === 0 ) {
            $next = $timestamp - time();
            $convoca_events[] = array( $hook, $next, count( $data ) );
        }
    }
}
usort( $convoca_events, function ( $a, $b ) { return $a[1] <=> $b[1]; } );
foreach ( $convoca_events as $ev ) {
    $status = $ev[1] < -3600 ? '⚠️ ATRASADO' : ( $ev[1] < 0 ? '⚠️ retraso' : 'OK' );
    if ( $ev[1] < -3600 ) { $fails++; }
    printf( "  [%s] %-42s next=%s\n", $status, $ev[0], human_readable_diff( $ev[1] ) );
}
echo "  Total eventos cron Convoca: " . count( $convoca_events ) . "\n";

// ── 2. Licencias ────────────────────────────────────
echo "\n== LICENCIAS ==\n";
if ( class_exists( 'Convoca\\Core\\License_Manager' ) ) {
    $license = \Convoca\Core\License_Manager::get_license();
    $label   = \Convoca\Core\License_Manager::get_status_label();
    echo "  Estado: {$label}\n";
    if ( is_array( $license ) ) {
        foreach ( $license as $k => $v ) {
            if ( ! is_array( $v ) && ! is_object( $v ) ) {
                echo "  {$k}: " . ( is_bool( $v ) ? var_export( $v, true ) : esc_html( (string) $v ) ) . "\n";
            }
        }
    }
} else {
    echo "  License_Manager no disponible\n";
}

// ── 3. Logs ─────────────────────────────────────────
echo "\n== LOGS ==\n";
if ( class_exists( 'Convoca\\Core\\Logger' ) ) {
    $stats = \Convoca\Core\Logger::get_stats();
    echo "  Total logs: {$stats['total']}\n";
    foreach ( (array) $stats['by_level'] as $level => $count ) {
        $mark = $level === 'error' ? '⚠️' : '';
        echo "  {$mark} {$level}: {$count}\n";
        if ( $level === 'error' && (int) $count > 0 ) { $fails++; }
    }
    echo "  Tamaño tabla: {$stats['size_kb']} KB\n";
    // Top contextos de error — ayuda a distinguir tests vs producción.
    if ( ! empty( $stats['by_context'] ) && is_array( $stats['by_context'] ) ) {
        echo "  Top contextos:\n";
        foreach ( array_slice( $stats['by_context'], 0, 5 ) as $ctx ) {
            if ( is_array( $ctx ) && isset( $ctx['context'] ) ) {
                echo "    {$ctx['context']}: {$ctx['count']}\n";
            }
        }
    }
}

// ── 4. Memoria PHP ──────────────────────────────────
echo "\n== MEMORIA ==\n";
printf( "  Uso actual: %.1f MB\n", memory_get_usage( true ) / 1048576 );
printf( "  Pico: %.1f MB\n", memory_get_peak_usage( true ) / 1048576 );
printf( "  Límite: %s\n", ini_get( 'memory_limit' ) );

// ── 5. Errores PHP recientes (debug.log) ────────────
echo "\n== ERRORES PHP (debug.log) ==\n";
$debug_log = WP_CONTENT_DIR . '/debug.log';
if ( file_exists( $debug_log ) && filesize( $debug_log ) > 0 ) {
    $size_mb = round( filesize( $debug_log ) / 1048576, 2 );
    echo "  debug.log: {$size_mb} MB\n";
    $lines = file( $debug_log );
    $last = array_slice( $lines, -10 );
    $php_errors = 0;
    foreach ( $last as $line ) {
        if ( preg_match( '/PHP (Fatal|Warning|Notice|Parse|Deprecated)/', $line ) ) {
            $php_errors++;
        }
    }
    echo "  Errores PHP en últimas 10 líneas: {$php_errors}\n";
    if ( $size_mb > 10 ) {
        echo "  ⚠️ debug.log supera 10 MB\n";
        $fails++;
    }
} else {
    echo "  debug.log: vacío o no existe ✅\n";
}

// ── 6. Tiempo de respuesta REST ─────────────────────
echo "\n== REST (tiempo de respuesta) ==\n";
$rest_tests = array(
    array( 'enroll', home_url( '/wp-json/convoca-enroll/v1/actividades' ) ),
    array( 'core',   home_url( '/wp-json/convoca/v1/admin/metrics' ) ),
);
foreach ( $rest_tests as $rt ) {
    $start = microtime( true );
    $r = wp_remote_get( $rt[1], array( 'timeout' => 10, 'sslverify' => false ) );
    $ms = round( ( microtime( true ) - $start ) * 1000 );
    $code = wp_remote_retrieve_response_code( $r );
    $ok = $code === 200 || $code === 401;
    printf( "  [%s] %-8s HTTP %s  %d ms\n", $ok ? 'OK' : '⚠️', $rt[0], $code, $ms );
    if ( $ms > 2000 ) {
        echo "  ⚠️  Respuesta lenta (>2s)\n";
        $fails++;
    }
}

echo "\n══════════════════════════════════════════\n";
printf( "  RESUMEN: %d problemas detectados\n", $fails );
echo "══════════════════════════════════════════\n";

if ( $fails > 0 ) {
    exit( 1 );
}

function human_readable_diff( $seconds ) {
    $abs = abs( $seconds );
    if ( $abs < 60 ) { return $seconds . 's'; }
    if ( $abs < 3600 ) { return round( $seconds / 60 ) . 'm'; }
    if ( $abs < 86400 ) { return round( $seconds / 3600 ) . 'h'; }
    return round( $seconds / 86400 ) . 'd';
}
