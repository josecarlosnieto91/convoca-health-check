<?php
/**
 * Fixture limpio: NO debe disparar ninguna regla de Convoca.
 */

function convoca_fixture_bueno( $wpdb ) {
	$id    = absint( $_GET['id'] );
	$nombre = sanitize_text_field( wp_unslash( $_POST['nombre'] ?? '' ) );

	$wpdb->query( $wpdb->prepare( 'DELETE FROM wp_conv WHERE id = %d', $id ) );

	echo esc_html( $nombre );

	$ruta = basename( $_REQUEST['plantilla'] ?? '' );
	include __DIR__ . '/plantillas/' . $ruta;

	$json = json_decode( (string) wp_unslash( $_POST['json'] ?? '' ), true );
	$nombre_json = is_array( $json ) && isset( $json['nombre'] ) ? (string) $json['nombre'] : '';

	$respuesta = wp_remote_get( 'https://example.test/api' );
	if ( is_wp_error( $respuesta ) ) {
		return array();
	}

	return array( $nombre_json, wp_remote_retrieve_body( $respuesta ) );
}
