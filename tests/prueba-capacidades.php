<?php
/**
 * Prueba en negativo del check «Acceso al panel».
 *
 * Carga SOLO la comprobación pura (lib/capacidades.php, sin WordPress) y la enfrenta a tres tipos:
 * uno contaminado con el patrón exacto de convoca-courses 0.2.3, uno sano como el de la 0.3.2 y uno
 * nativo de WordPress. Un check que solo sabe decir PASS no vale: esto demuestra que caza el patrón.
 *
 * Uso: wp eval-file prueba-capacidades.php --path=/ruta/al/wp
 */

require_once __DIR__ . '/../lib/capacidades.php';

$contaminado = (object) array(
	'cap'          => (object) array(
		'edit_post'   => 'convoca_manage_courses',
		'read_post'   => 'read',
		'delete_post' => 'convoca_manage_courses',
	),
	'map_meta_cap' => true,
);

$sano = (object) array(
	'cap'          => (object) array(
		'edit_post'   => 'convoca_edit_curso',
		'read_post'   => 'convoca_read_curso',
		'delete_post' => 'convoca_delete_curso',
	),
	'map_meta_cap' => true,
);

$nativo = (object) array(
	'cap'          => (object) array(
		'edit_post'   => 'edit_post',
		'read_post'   => 'read_post',
		'delete_post' => 'delete_post',
	),
	'map_meta_cap' => true,
);

$casos = array(
	'contaminado (patron de la 0.2.3)' => array( 'convoca_curso' => $contaminado ),
	'sano (patron de la 0.3.2)'        => array( 'convoca_curso' => $sano ),
	'nativo de WordPress'              => array( 'post' => $nativo ),
);

$fallos = 0;
foreach ( $casos as $nombre => $tipos ) {
	$detectado = hc_capacidades_contaminadas( $tipos );
	$esperado  = ( 0 === strpos( $nombre, 'contaminado' ) );
	$ok        = $esperado ? ! empty( $detectado ) : empty( $detectado );
	printf( "%-36s %-5s detectado=%s\n", $nombre, $ok ? 'OK' : 'FALLO', $detectado ? implode( ',', $detectado ) : '(nada)' );
	if ( ! $ok ) {
		$fallos++;
	}
}

echo $fallos
	? "RESULTADO: el check NO vale ({$fallos} fallos)\n"
	: "RESULTADO: el check caza el patron y no marca lo sano\n";

exit( $fallos > 0 ? 1 : 0 );
