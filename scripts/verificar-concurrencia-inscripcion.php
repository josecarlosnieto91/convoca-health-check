<?php
/**
 * Ayudante del verificador de concurrencia de inscripciones (`verificar-concurrencia-inscripcion.sh`).
 *
 * Crea (o borra) la actividad de pruebas y cuenta lo que ha quedado en la base. Hace falta una actividad
 * propia porque las de la demo están caducadas y el motor rechaza las fechas pasadas.
 *
 * Uso:
 *   wp eval-file verificar-concurrencia-inscripcion.php crear <correo>
 *   wp eval-file verificar-concurrencia-inscripcion.php contar <id> <correo>
 *   wp eval-file verificar-concurrencia-inscripcion.php borrar <id>
 */

$accion = $args[0] ?? 'contar';

if ( 'crear' === $accion ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'actividad',
			'post_status'  => 'publish',
			'post_title'   => 'Prueba de concurrencia (gauntlet)',
			'post_content' => 'Actividad creada por el verificador de concurrencia. Se borra al terminar.',
		)
	);
	$fecha = gmdate( 'Y-m-d', time() + 3 * DAY_IN_SECONDS );
	foreach ( array(
		'_convoca_fecha_inicio'       => $fecha,
		'_convoca_fecha_fin'          => $fecha,
		'_convoca_lugar'              => 'Sitio de pruebas',
		'_convoca_ubicacion'          => 'Sitio de pruebas',
		'_convoca_plazas_totales'     => 5,
		'_convoca_plazas_disponibles' => 5,
		'_convoca_plazas_ocupadas'    => 0,
		'_convoca_requiere_pago'      => 0,
	) as $clave => $valor ) {
		update_post_meta( $id, $clave, $valor );
	}
	printf( "actividad=%d\n", $id );
	return;
}

if ( 'contar' === $accion ) {
	global $wpdb;
	$actividad = (int) $args[1];
	$correo    = (string) $args[2];

	$filas = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_convoca_email'
			 WHERE p.post_type = 'inscripcion' AND p.post_status = 'publish' AND m.meta_value = %s",
			$correo
		)
	);
	$libres = (string) get_post_meta( $actividad, '_convoca_plazas_disponibles', true );
	printf( "filas=%d plazas_libres=%s\n", $filas, $libres );
	return;
}

if ( 'borrar' === $accion ) {
	$actividad = (int) $args[1];
	foreach ( get_posts(
		array(
			'post_type'   => 'inscripcion',
			'numberposts' => -1,
			'post_status' => 'any',
			'meta_key'    => '_convoca_actividad_id',
			'meta_value'  => $actividad,
			'fields'      => 'ids',
		)
	) as $hijo ) {
		wp_delete_post( (int) $hijo, true );
	}
	wp_delete_post( $actividad, true );
	// Y las inscripciones de los correos de prueba, por si alguna quedó sin actividad.
	global $wpdb;
	foreach ( (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'inscripcion' AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value LIKE 'qa-concurrencia-%')" ) as $resto ) {
		wp_delete_post( (int) $resto, true );
	}
	echo "limpio\n";
	return;
}

echo "acción no reconocida\n";
