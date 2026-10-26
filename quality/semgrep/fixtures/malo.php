<?php
/**
 * Fixture de reglas Semgrep de Convoca. TODO lo de este fichero es un defecto a propósito.
 * No se ejecuta: solo lo analiza quality/semgrep.sh.
 */

function convoca_fixture_malo() {
	global $wpdb;

	// convoca-sql-sin-preparar
	$id = $_GET['id'];
	$wpdb->query( "DELETE FROM wp_conv WHERE id = $id" );

	// convoca-salida-sin-escapar
	echo $_POST['nombre'];

	// convoca-fichero-desde-entrada
	include $_REQUEST['plantilla'];

	// convoca-ssrf
	wp_remote_get( $_GET['url'] );

	// convoca-unserialize
	$datos = unserialize( $_POST['payload'] );

	// convoca-json-sin-comprobar
	$nombre = json_decode( $_POST['json'] )['nombre'];

	// convoca-add-role
	add_role( 'rol_nuevo', 'Rol nuevo', array( 'read' ) );

	// convoca-arroba-silenciador
	$contenido = @file_get_contents( '/tmp/no-existe' );

	return array( $datos, $nombre, $contenido );
}
