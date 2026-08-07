# Auditoría de peso — RESULTADOS Opción B + Límite WordPress.org

**Actualización:** 2026-08-07 (tras ejecución Opción B)

## Límite de WordPress.org: 10 MB por zip

Fuente: Plugin Developer FAQ oficial — *"It's a .zip file and under 10Mb"*.
El formulario `wordpress.org/plugins/developers/add/` rechaza zips > 10 MB.

## Tamaños empaquetados reales

| Plugin | Zip con dev | Zip producción (`--no-dev`) | Límite | Estado |
|---|---|---|---|---|
| convoca-members | 12 MB | **1.4 MB** | 10 MB | ✅ |
| convoca-enroll | 12 MB | **1.8 MB** | 10 MB | ✅ |
| convoca-assistant | 26 MB | **1.1 MB** | 10 MB | ✅ |
| convoca-gateway | 11 MB | **968 KB** | 10 MB | ✅ |
| convoca-shifts | 11 MB | **1.1 MB** | 10 MB | ✅ |
| convoca-publisher | 9.8 MB | **1.1 MB** | 10 MB | ✅ |
| convoca-core | 31 MB | **5.8 MB** | 10 MB | ✅ |

**Conclusión:** todos los plugins caben en WordPress.org usando
`composer install --no-dev` al empaquetar. El vendor dev (phpstan 26MB,
wp-phpunit 17MB, phpcs 7MB, stubs 5.5MB) NO debe incluirse en la distribución.

## Opción B — Ejecutada ✅

| Repo | Eliminado | Estado |
|---|---|---|
| convoca-assistant | tests/node_modules (46MB disco), tests Jest (3 archivos) | ✅ pusheado |
| convoca-enroll | tests/e2e (4), scripts pw-*.mjs (10), bootstrap-fixed.php | ✅ pusheado |
| convoca-core | wp-tests-config.php (ya ignorado — solo disco) | ✅ |
| convoca-publisher | .phpunit.result.cache (ya ignorado — solo disco) | ✅ |

**Ahorro en disco:** ~46 MB (node_modules) + residuos locales.
**Ahorro en git:** ~20 archivos de tests duplicados eliminados de los repos.

## Fix adicional detectado: CI enmascaraba fallos de assistant

El pipeline `phpunit | tail -30` en el CI hacía que el exit code fuera el de
`tail` (0), así que assistant "pasaba" sin ejecutar tests (bootstrap.php
requiere WP test suite y fallaba silenciosamente). Corregido con
`PIPESTATUS[0]` — ahora el CI falla si un test no corre.

## Pendiente para distribución WordPress.org

1. Script/CI de empaquetado con `composer install --no-dev` (build-release)
2. Verificar que los tests unitarios standalone sigan pasando sin dev deps
   (los tests PHPUnit necesitan phpunit en dev — se mantiene en dev local y CI)
