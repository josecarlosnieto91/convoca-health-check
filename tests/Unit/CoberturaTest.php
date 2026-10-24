<?php
/**
 * El número de cobertura de la batería.
 *
 * El caso que importa es el del issue #10: 7 SKIP y 1 WARN no pueden dar «100 %». Este cálculo alimenta la línea que
 * firma un informe, y un porcentaje que sube justo cuando falta lo que no se ha podido comprobar es peor que no tenerlo.
 *
 * @package Convoca\HealthCheck
 */

namespace Convoca\HealthCheck\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Cálculo de cobertura.
 */
final class CoberturaTest extends TestCase {

	/**
	 * Todo bien: 100 % por los dos lados.
	 */
	public function test_todo_correcto(): void {
		$c = hc_calcular_cobertura( 10, 0, 0, 0 );

		$this->assertSame( 10, $c['total'] );
		$this->assertSame( 10, $c['comprobables'] );
		$this->assertSame( 100.0, $c['sobre_todo'] );
		$this->assertSame( 100.0, $c['sobre_comprobables'] );
	}

	/**
	 * El caso del issue #10: con 7 SKIP y 1 WARN, «sobre todo» NO es 100.
	 */
	public function test_los_skip_y_los_warn_no_se_esconden(): void {
		$c = hc_calcular_cobertura( 7, 0, 1, 7 );

		$this->assertSame( 15, $c['total'], 'lo que había que comprobar incluye warn y skip' );
		$this->assertSame( 7, $c['comprobables'] );
		$this->assertSame( 46.7, $c['sobre_todo'], '7 de 15: ni de lejos 100' );
		$this->assertSame( 100.0, $c['sobre_comprobables'], 'de lo comprobable sí pasó todo' );
		$this->assertNotSame( $c['sobre_todo'], $c['sobre_comprobables'], 'los dos números tienen que poder discrepar' );
	}

	/**
	 * Fallos: los dos números bajan y coinciden.
	 */
	public function test_con_fallos(): void {
		$c = hc_calcular_cobertura( 3, 7, 0, 0 );

		$this->assertSame( 30.0, $c['sobre_todo'] );
		$this->assertSame( 30.0, $c['sobre_comprobables'] );
	}

	/**
	 * Sin nada medido no se divide por cero ni se presume de nada.
	 */
	public function test_sin_datos(): void {
		$c = hc_calcular_cobertura( 0, 0, 0, 0 );

		$this->assertSame( 0, $c['total'] );
		$this->assertSame( 0, $c['comprobables'] );
		$this->assertSame( 0.0, $c['sobre_todo'] );
		$this->assertSame( 0.0, $c['sobre_comprobables'] );
	}

	/**
	 * Sólo skips: cero comprobado, cero por ciento. No es un 100 disfrazado.
	 */
	public function test_solo_no_comprobado(): void {
		$c = hc_calcular_cobertura( 0, 0, 2, 5 );

		$this->assertSame( 7, $c['total'] );
		$this->assertSame( 0.0, $c['sobre_todo'] );
		$this->assertSame( 0.0, $c['sobre_comprobables'] );
	}

	/**
	 * Redondeo a un decimal, y sin notación científica.
	 */
	public function test_redondeo(): void {
		$c = hc_calcular_cobertura( 2, 1, 0, 0 );

		$this->assertSame( 66.7, $c['sobre_todo'] );
		$this->assertIsFloat( $c['sobre_todo'] );
	}
}
