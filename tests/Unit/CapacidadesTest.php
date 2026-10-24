<?php
/**
 * La detección de capacidades contaminadas: el check que tumbó el panel entero.
 *
 * El 22-10-2026 un tipo de contenido con `read_post => 'read'` dejó `/wp-admin` en 403 para todo el mundo, administrador
 * incluido, y la batería no lo vio porque ningún check miraba el mapa de capacidades. Aquí se fija que el check caza ese
 * patrón exacto, y también que **no** marca lo legítimo: su primera versión incluía `read_post`, `edit_post` y
 * `delete_post` en la lista de prohibidas y daba por contaminados `post` y `attachment`. El fallo era del check.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Capacidades por entrada.
 */
final class CapacidadesTest extends TestCase {

	/**
	 * Un tipo con sus capacidades.
	 *
	 * @param array $por_entrada Capacidades por entrada.
	 * @param bool  $map_meta    Si mapea meta caps.
	 * @return object
	 */
	private function tipo( array $por_entrada, bool $map_meta = true ): object {
		return (object) array(
			'cap'          => (object) $por_entrada,
			'map_meta_cap' => $map_meta,
		);
	}

	/**
	 * La lista de capacidades globales: `read` sí; los nombres de las meta caps **no**.
	 */
	public function test_la_lista_de_globales(): void {
		$globales = hc_capacidades_globales();

		$this->assertContains( 'read', $globales );
		$this->assertContains( 'manage_options', $globales );

		foreach ( array( 'read_post', 'edit_post', 'delete_post' ) as $legitima ) {
			$this->assertNotContains( $legitima, $globales, 'son los nombres legítimos de las meta caps: marcarlas da falsos positivos' );
		}
	}

	/**
	 * El patrón exacto que tumbó el panel: `read_post => 'read'`.
	 */
	public function test_caza_el_patron_que_tumbo_el_panel(): void {
		$tipos = array(
			'convoca_curso' => $this->tipo(
				array(
					'edit_post'   => 'convoca_manage_courses',
					'read_post'   => 'read',
					'delete_post' => 'convoca_manage_courses',
				)
			),
		);

		$detectado = hc_capacidades_contaminadas( $tipos );

		$this->assertContains( 'convoca_curso.read_post=read', $detectado );
		// También `edit_post => convoca_manage_courses`: esa capacidad es global del ecosistema, así que usarla como
		// capacidad por entrada la mete en `$post_type_meta_caps` igual que `read`. El patrón real las tenía las dos.
		$this->assertContains( 'convoca_curso.edit_post=convoca_manage_courses', $detectado );
	}

	/**
	 * Un tipo sano no se marca.
	 */
	public function test_no_marca_lo_sano(): void {
		$tipos = array(
			'convoca_curso' => $this->tipo(
				array(
					'edit_post'   => 'convoca_edit_curso',
					'read_post'   => 'convoca_read_curso',
					'delete_post' => 'convoca_delete_curso',
				)
			),
		);

		$this->assertSame( array(), hc_capacidades_contaminadas( $tipos ) );
	}

	/**
	 * Los tipos nativos de WordPress usan sus propias meta caps: no son contaminación.
	 */
	public function test_los_nativos_no_se_marcan(): void {
		$tipos = array(
			'post'       => $this->tipo( array( 'read_post' => 'read_post', 'edit_post' => 'edit_post' ) ),
			'attachment' => $this->tipo( array( 'read_post' => 'read_post' ) ),
		);

		$this->assertSame( array(), hc_capacidades_contaminadas( $tipos ), 'la primera versión del check se equivocaba aquí' );
	}

	/**
	 * Sin `map_meta_cap` el mapa de capacidades por entrada no se usa: no hay nada que contaminar.
	 */
	public function test_sin_map_meta_cap_se_ignora(): void {
		$tipos = array( 'raro' => $this->tipo( array( 'read_post' => 'read' ), false ) );

		$this->assertSame( array(), hc_capacidades_contaminadas( $tipos ) );
	}

	/**
	 * Sin `cap` tampoco.
	 */
	public function test_sin_cap_se_ignora(): void {
		$tipos = array( 'raro' => (object) array( 'map_meta_cap' => true ) );

		$this->assertSame( array(), hc_capacidades_contaminadas( $tipos ) );
	}

	/**
	 * Varias contaminaciones en un tipo salen todas, y una capacidad vacía no cuenta.
	 */
	public function test_varias_y_vacias(): void {
		$tipos = array(
			'malo' => $this->tipo(
				array(
					'edit_post'   => 'manage_options',
					'read_post'   => '',
					'delete_post' => 'delete_posts',
				)
			),
		);

		$detectado = hc_capacidades_contaminadas( $tipos );

		$this->assertContains( 'malo.edit_post=manage_options', $detectado );
		$this->assertContains( 'malo.delete_post=delete_posts', $detectado );
		$this->assertCount( 2, $detectado, 'la vacía no genera aviso' );
	}

	/**
	 * Acepta cualquier cosa que se pueda recorrer (el runner le pasa el resultado de `get_post_types`).
	 */
	public function test_acepta_un_array_plano(): void {
		$this->assertSame( array(), hc_capacidades_contaminadas( array() ) );
	}
}
