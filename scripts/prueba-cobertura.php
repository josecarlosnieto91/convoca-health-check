<?php
/**
 * Caso de prueba de la cobertura de la batería (issue #10).
 *
 * Criterio de hecho del issue: *el número no permite confundir «todo comprobado» con «todo lo comprobable
 * pasó»*, con un caso de prueba que lo demuestre. Este es.
 *
 * Se ejecuta con: php scripts/prueba-cobertura.php
 */

require_once __DIR__ . '/../lib/cobertura.php';

$fallos = 0;

function comprobar( string $que, bool $condicion ): void {
	global $fallos;
	if ( $condicion ) {
		echo "  ok   {$que}\n";
		return;
	}
	echo "  FALLO {$que}\n";
	$fallos++;
}

// El caso que denunció el issue: un sitio con 7 SKIP y 1 WARN informaba «Coverage: 100%».
$sitio = hc_calcular_cobertura( 120, 0, 1, 7 );
comprobar( 'con 1 WARN y 7 SKIP, la cobertura NO es 100%', 100.0 !== $sitio['sobre_todo'] );
comprobar( 'y dice cuánto es de verdad (120 de 128 = 93,8%)', 93.8 === $sitio['sobre_todo'] );
comprobar( 'los 8 que no se comprobaron entran en el total', 128 === $sitio['total'] );
comprobar( 'de lo comprobable sí es 100% (y por eso hace falta el otro número)', 100.0 === $sitio['sobre_comprobables'] );

// Todo comprobado y correcto: 100% en los dos, sin trampa.
$limpio = hc_calcular_cobertura( 50, 0, 0, 0 );
comprobar( 'sin WARN ni SKIP, los dos números son 100%', 100.0 === $limpio['sobre_todo'] && 100.0 === $limpio['sobre_comprobables'] );

// Nada que comprobar y un fallo: no puede dividir por cero ni decir 100%.
$roto = hc_calcular_cobertura( 0, 3, 0, 0 );
comprobar( 'con fallos, la cobertura es 0%', 0.0 === $roto['sobre_todo'] );

// Sin nada: cero, sin avisos ni divisiones imposibles.
$vacio = hc_calcular_cobertura( 0, 0, 0, 0 );
comprobar( 'sin comprobaciones, 0% y sin romperse', 0.0 === $vacio['sobre_todo'] && 0.0 === $vacio['sobre_comprobables'] );

echo $fallos > 0 ? "\nFALLOS: {$fallos}\n" : "\nTodo correcto.\n";
exit( $fallos > 0 ? 1 : 0 );
