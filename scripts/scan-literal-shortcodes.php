<?php
/**
 * scan-literal-shortcodes.php — Criba de shortcodes literales en el contenido.
 *
 * Busca en páginas, entradas y CPTs del sitio corchetes que el visitante ve
 * como texto porque ningún plugin activo registra ese shortcode (el caso de
 * `[mi_panel]` en la página «Mi Panel de Socio»: se publicó con el texto
 * literal a la vista hasta que alguien lo vio).
 *
 * Un shortcode huérfano no deja rastro en los logs: la única comprobación fiable
 * es `shortcode_exists()` sobre los tags hallados en el contenido.
 *
 * Uso:
 *   wp eval-file scan-literal-shortcodes.php
 *   wp --path=<sitio> eval-file scan-literal-shortcodes.php
 *
 * Salida: lista de contenido afectado con estado, ID, slug y shortcode, más un
 * resumen por shortcode y el estado de las páginas del sistema.
 */

$post_types = array( 'page', 'post', 'actividad', 'miembro', 'centro_turno' );

$posts = get_posts(
	array(
		'post_type'      => $post_types,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);

$total = 0;
$tags  = array();

foreach ( $posts as $p ) {
	if ( ! preg_match_all( '/\[([a-zA-Z][a-zA-Z0-9_\-]*)([^\]]*)\]/', (string) $p->post_content, $m, PREG_SET_ORDER ) ) {
		continue;
	}
	foreach ( $m as $hit ) {
		$tag = $hit[1];
		if ( shortcode_exists( $tag ) ) {
			continue;
		}
		++$total;
		$tags[ $tag ][] = $p->ID;

		$snippet = trim( (string) $p->post_content );
		if ( strlen( $snippet ) > 90 ) {
			$snippet = substr( $snippet, 0, 90 ) . '…';
		}

		printf(
			"%-9s #%-6d %-28s [%s]   contenido: %s\n",
			$p->post_status,
			$p->ID,
			substr( $p->post_name, 0, 28 ),
			$tag,
			str_replace( "\n", ' ', $snippet )
		);
	}
}

echo "\n--- RESUMEN ---\n";
if ( ! $total ) {
	echo "Sin shortcodes huérfanos en el contenido.\n";
} else {
	foreach ( $tags as $tag => $ids ) {
		printf( "[%s]: %d uso(s) en %s\n", $tag, count( $ids ), implode( ',', array_slice( $ids, 0, 10 ) ) );
	}
	printf( "TOTAL: %d usos huérfanos\n", $total );
}

// Páginas del sistema: las que crea el asistente y a las que apuntan los emails.
echo "\n--- PÁGINAS DEL SISTEMA ---\n";
$system_slugs = array( 'alta-socios', 'panel-socio', 'pago', 'pago-completado', 'pago-error', 'renovar', 'panel-reservas', 'checkin' );
foreach ( $system_slugs as $slug ) {
	$page = get_page_by_path( $slug );
	if ( ! $page ) {
		printf( "%-18s NO EXISTE\n", $slug );
		continue;
	}
	preg_match_all( '/\[([a-zA-Z][a-zA-Z0-9_\-]*)/', (string) $page->post_content, $mm );
	$found = array();
	foreach ( $mm[1] as $t ) {
		$found[] = '[' . $t . ( shortcode_exists( $t ) ? '] ok' : '] SIN REGISTRAR' );
	}
	printf( "%-18s #%-6d %-9s %s\n", $slug, $page->ID, $page->post_status, implode( ' ', $found ) );
}

echo "\n(Leyenda: [tag] SIN REGISTRAR = nadie lo registra; el visitante ve los corchetes)\n";
