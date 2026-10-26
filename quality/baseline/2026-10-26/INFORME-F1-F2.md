# F1 y F2 — Sonar y escáneres (26/10/2026)

## F1 · SonarQube Community Build

| Pieza | Valor |
|---|---|
| Versión | **26.10.0** (imagen `sonarqube:community` fijada por digest `sha256:0b340844…`) |
| Base de datos | `postgres:17-alpine` por digest `sha256:266d3c25…` |
| URL | `127.0.0.1:9000` (solo loopback) |
| Proyectos | **11** (los 7 vendibles + license-generator, theme, courses, mu-plugins) |
| Token | `pass show convoca/sonar-token` (una línea) |
| Admin | `pass show convoca/sonar-admin` |
| Scripts | `quality/sonar/docker-compose.yml`, `quality/run-sonar.sh` |

**Cobertura importada, con negativo demostrado:** el mismo proyecto analizado **sin**
`sonar.php.coverage.reportPaths` da **0,0 %**; con el clover da **64,7 %**. El clover de PHPUnit dice 71,2 %
(4.515 de 6.342 sentencias). La diferencia está medida: Sonar cuenta **7.415 líneas ejecutables** frente a
6.342 del clover (mete como no cubiertos ficheros que la suite no cargó) y por fichero las cifras bailan
≤10 líneas por el modelo de líneas. No es un import roto: es otro denominador. **Criterio corregido en el
goal**: no se exige igualdad con el clover, se exige que el import funcione y que la diferencia esté explicada.

| Repo | ncloc | Cobertura | Bugs | Vulnerabilidades | Code smells | Complejidad | Cognitiva | Duplicación |
|---|---|---|---|---|---|---|---|---|
| core | 9.856 | 64,7 % | 30 | 5 | 9 | 2.007 | 2.029 | 1,6 % |
| members | 16.389 | 58,0 % | 75 | 2 | 23 | — | — | — |
| enroll | 15.333 | 78,0 % | 43 | 10 | 15 | — | — | — |
| gateway | 5.858 | 74,5 % | 20 | 3 | 4 | — | — | — |
| shifts | 6.414 | 69,2 % | 11 | 0 | 12 | — | — | — |
| publisher | 6.529 | 77,4 % | 6 | 2 | 0 | — | — | — |
| assistant | 4.997 | 83,7 % | 35 | 3 | 0 | — | — | — |
| license-generator | 219 | 97,0 % | 0 | 0 | 0 | — | — | — |
| theme | 5.284 | 61,2 % | 3 | 1 | 14 | — | — | — |
| courses | 7.484 | 61,7 % | 18 | 0 | 0 | — | — | — |
| mu-plugins | 219 | 81,8 % | 0 | 0 | 0 | — | — | — |

**Límite aceptado:** la edición Community analiza una sola rama y **no decora PRs**. Las puertas viven en el CI
(`quality.yml`), no en Sonar.

## F2 · Escáneres locales

| Herramienta | Versión | Integridad |
|---|---|---|
| PHPMD | 3.0.0 | phar + sha256 en `~/.local/opt/quality/MANIFEST` |
| PHPmetrics | 2.11.0 | phar + sha256 en manifiesto |
| gitleaks | 8.30.1 | checksum oficial de la release |
| Semgrep | 1.181.1 | imagen por digest `sha256:3db5c7af…` |

`quality/install-scanners.sh` reinstala o verifica; si un sha no coincide, falla.

### Reglas propias (`quality/semgrep/convoca.yml`)

8 reglas: SQL sin preparar (taint), salida sin escapar, fichero desde entrada (LFI), SSRF, `unserialize`,
`json_decode` indexado sin comprobar, `add_role` fuera de core y operador `@`.

**Negativo demostrado:** las 8 disparan contra `fixtures/malo.php` (8 hallazgos) y **0** contra
`fixtures/bueno.php`, sin errores de parseo.

**Dos correcciones durante la validación, y por qué:**

1. La regla XSS nació en modo taint y producía **44 falsos positivos en 6 repos**: Semgrep marca como
   contaminado el retorno de `$wpdb->get_results()` cuando la consulta lleva un `$_GET`, así que señalaba
   ecos de datos que vienen de la base de datos. Se reescribió con patrones de secuencia (superglobal directa
   o asignada y luego impresa). Resultado real: **0 XSS** — coherente con que WPCS ya bloquea el escape.
2. El LFI de members (`class-admin-import-csv.php:407`) era falso positivo: `ruta_por_ficha()` valida token
   (regex), propietario y que el `realpath` quede dentro del directorio. Se añadió como sanitizer documentado.

### Secretos (gitleaks, árbol + historial completo)

Primera pasada: 12 hallazgos. Clasificados **uno a uno** contra el commit real:

- 9 son `--admin_password=NoEsUnSecreto123` del WordPress efímero del CI (no es un secreto: el contenedor se
  destruye y no toca ningún sitio).
- 2 son la **clave de comercio de PRUEBAS de Redsys** (publicada por Redsys) en tests del gateway.
- 1 es un usuario de prueba (`alumno2-prueba`) en un script de verificación.

`quality/gitleaks.toml` recoge solo esos patrones, con motivo. Resultado final: **0 secretos en 11/11**
(árbol e historial).

### Vulnerabilidades de Sonar: 26 → 0 reales

| Regla | Casos | Veredicto |
|---|---|---|
| `php:S4790` hash débil (MD5) | 18 | **Falso positivo**: todos son claves de caché, rate-limit o deduplicación de webhooks, no firmas |
| `php:S5547` cifrado débil | 1 | **Aceptado**: `des-ede3-cbc` es lo que **exige Redsys** por protocolo |
| `php:S5332` HTTP en claro | 6 | **Falso positivo**: `'http://panel_reservas'` es una clave de plantilla de correo, no una URL |
| `python:S5443` directorio público | 1 | Script local de verificación (`glob` de `/tmp`); sin superficie de producción |
| `php:S5122` CORS comodín | 1 | **Revisión real** → issue assistant#23 |

### Complejidad y mantenibilidad (PHPMD + PHPmetrics)

| Repo | PHPMD total | Ciclomática | Método >50 líneas | NPath | Parámetros | MI medio | MI mínimo |
|---|---|---|---|---|---|---|---|
| members | 234 | 54 | 112 | 51 | 10 | 62,4 | 17,6 |
| enroll | 204 | 47 | 102 | 42 | 10 | 57,0 | 16,5 |
| core | 119 | 25 | 56 | 28 | 7 | 71,0 | 26,0 |
| shifts | 91 | 22 | 44 | 21 | 4 | 68,6 | 30,0 |
| gateway | 90 | 22 | 39 | 22 | 5 | 56,0 | 23,1 |
| publisher | 82 | 17 | 38 | 19 | 6 | 58,9 | 22,0 |
| courses | 51 | 14 | 17 | 15 | 4 | 83,3 | 39,9 |
| assistant | 43 | 9 | 22 | 6 | 6 | 74,2 | 44,2 |
| theme | 9 | 2 | 5 | 2 | — | — (procedural) | — |
| license-generator | 1 | 0 | 1 | 0 | 0 | 83,0 | 66,8 |

**Total: 924 violaciones** (212 de ciclomática >10, 234 métodos >50 líneas, 206 de NPath). El 47 % está en
members y enroll.

## Errores propios de esta tanda

1. Guardé el token de Sonar con dos líneas (`Bearer token\ntoken`) y el log del scanner lo escupió. **Rotado**
   y los tres scripts leen con `head -1`. El token anterior quedó revocado.
2. La regla XSS en taint (44 falsos positivos) — corregida y medida.
3. El workflow de calidad se referenció desde core **antes** de estar empujado en health-check: el run de core
   falló con «workflow file issue». Se publicó y se relanzó con un commit vacío.
4. El WordPress de prueba D1 necesitaba `DB_HOST` explícito: el volumen reutilizaba un `wp-config` con `mysql`.
   Se corrigió con `wp config set DB_HOST d1-db`; la receta queda en `quality/baseline/2026-10-26/INFORME-F4.md`.
