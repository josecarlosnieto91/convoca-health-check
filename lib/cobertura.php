<?php
/**
 * El número de cobertura de la batería (issue #10).
 *
 * Antes era `pass / (pass + fail)`: los **WARN** y los **SKIP** —justo lo que no se ha podido comprobar— no
 * contaban, así que un sitio con 7 SKIP y 1 WARN informaba «Coverage: 100%». Un número que sube cuando falta
 * precisamente lo que no se ha podido medir es peor que no tenerlo: invita a firmar con datos que no respaldan
 * nada.
 *
 * Aquí se devuelven **dos** números, y el que se enseña primero es el honesto:
 *
 *  - `sobre_todo`: pass / (pass + fail + warn + skip) — de todo lo que había que comprobar, cuánto está
 *    comprobado y bien.
 *  - `sobre_comprobables`: pass / (pass + fail) — de lo que se pudo comprobar, cuánto pasó. Sigue siendo útil,
 *    pero **no puede ir solo**: con 7 SKIP también daría 100%.
 *
 * @param int $pass Comprobaciones que pasan.
 * @param int $fail Comprobaciones que fallan.
 * @param int $warn Comprobaciones con aviso.
 * @param int $skip Comprobaciones que no se han podido hacer.
 * @return array{total: int, comprobables: int, sobre_todo: float, sobre_comprobables: float}
 */
function hc_calcular_cobertura( int $pass, int $fail, int $warn, int $skip ): array {
	$comprobables = $pass + $fail;
	$total        = $comprobables + $warn + $skip;

	return array(
		'total'              => $total,
		'comprobables'       => $comprobables,
		'sobre_todo'         => $total > 0 ? round( 100 * $pass / $total, 1 ) : 0.0,
		'sobre_comprobables' => $comprobables > 0 ? round( 100 * $pass / $comprobables, 1 ) : 0.0,
	);
}
