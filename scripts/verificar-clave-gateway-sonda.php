<?php
/**
 * Sonda del verificador de la clave del gateway (`verificar-clave-gateway.sh`).
 *
 * Guarda los ajustes, los rompe con una clave que tiene la FORMA correcta («enc:») y el contenido
 * indescifrable —el caso que el check viejo daba por bueno— y los repone exactos.
 *
 * Uso: wp eval-file verificar-clave-gateway-sonda.php guardar|romper|restaurar|comprobar
 */

$copia  = '/tmp/convoca-gateway-ajustes.json';
$actual = get_option( 'convoca_gateway_settings' );

switch ( $args[0] ?? 'comprobar' ) {
	case 'guardar':
		file_put_contents( $copia, wp_json_encode( $actual ) );
		printf( "ajustes guardados (%d claves, clave de %d caracteres)\n", count( (array) $actual ), strlen( (string) ( $actual['secret_key'] ?? '' ) ) );
		break;

	case 'romper':
		$roto               = $actual;
		$roto['secret_key'] = 'enc:' . base64_encode( str_repeat( "\x00", 32 ) );
		update_option( 'convoca_gateway_settings', $roto );
		printf( "clave rota: ahora mide %d caracteres\n", strlen( (string) get_option( 'convoca_gateway_settings' )['secret_key'] ) );
		break;

	case 'restaurar':
		update_option( 'convoca_gateway_settings', json_decode( (string) file_get_contents( $copia ), true ) );
		printf( "ajustes repuestos: la clave mide %d caracteres\n", strlen( (string) get_option( 'convoca_gateway_settings' )['secret_key'] ) );
		break;

	case 'comprobar':
		printf( "idénticos a la copia: %s\n", $actual == json_decode( (string) file_get_contents( $copia ), true ) ? 'SÍ' : 'NO' );
		break;
}
