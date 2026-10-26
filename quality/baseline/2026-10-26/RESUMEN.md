# Línea base de calidad — 2026-10-26

Generado por `quality/measure-quality.sh`.

| Repo | LOC | Tests | Cobertura | PHPStan | Nivel 8 | CC>15 | CC 10-15 | Nidos>6 | Audit | Versión / commit |
|---|---|---|---|---|---|---|---|---|---|---|
| convoca-assistant | 7461 | 38 | 83.2 % | 5 (0) | 78 | 7 | 5 | 0 | None | 0.2.23 / 0bba435 |
| convoca-core | 14392 | 74 | 71.0 % | 5 (0) | 193 | 9 | 26 | 0 | 0 | 2.3.50 / 8bc546f |
| convoca-courses | 12882 | 49 | 70.4 % | 5 (0) | 20 | 5 | 5 | 0 | 0 | 0.3.3 / 32323c0 |
| convoca-enroll | 22969 | 137 | 80.3 % | 5 (0) | 357 | 18 | 25 | 0 | 0 | 2.7.69 / e35ddd2 |
| convoca-gateway | 9745 | 54 | 74.1 % | 5 (0) | 107 | 10 | 17 | 0 | None | 2.13.23 / a00e4c1 |
| convoca-license-generator | 362 | 3 | 96.3 % | 0 (0) | 15 | 0 | 0 | 0 | None | 1.0.2 / 395e7d1 |
| convoca-members | 25141 | 147 | 57.9 % | 5 (0) | 282 | 27 | 32 | 0 | 0 | 2.9.19 / 7397ec7 |
| convoca-mu-plugins | 337 | 4 | 97.9 % | — (None) | None | None | None | None | None | 1.1.0 / 7dfa29e |
| convoca-publisher | 9270 | 46 | 77.5 % | 5 (0) | 63 | 8 | 12 | 0 | 0 | 1.22.13 / efd563d |
| convoca-shifts | 8519 | 52 | 72.3 % | 5 (0) | 199 | 11 | 9 | 1 | None | 2.5.35 / 3b63159 |
| convoca-theme | 3215 | 18 | 76.0 % | 5 (0) | 18 | 1 | 1 | 0 | None | 2.9.42 / a9a17c6 |

`CC>15` y `CC>10` cuentan funciones (PHPCS Generic.Metrics.CyclomaticComplexity).
`Nivel 8` es PHPStan con la misma config del CI y `--level=8`.
`Cobertura` es el **suelo** declarado en `.coverage-floor`, no una medición.
