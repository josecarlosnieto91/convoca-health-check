# Informe final — calidad y seguridad de Convoca

Fecha de cierre: **27/10/2026**. Cada cifra sale de un comando ejecutado hoy; el comando va al lado.
Alcance: los siete repos vendibles (`core`, `members`, `enroll`, `gateway`, `shifts`, `publisher`,
`assistant`). Fuera de alcance por decisión del encargo: desplegar, tocar producción y reescribir historial.

## Las cinco cifras de la semana

| Cifra | Valor | Cómo se mide |
|---|---:|---|
| Altas abiertas | **4** (enroll 2, publisher 1, assistant 1) | `gh issue list --state open` por repo |
| Funciones con complejidad >15 | **91** (members 27, enroll 18, shifts 12, gateway 10, core 9, publisher 8, assistant 7) | `phpcs --standard=quality/phpcs-metrics.xml` (ERROR `MaxExceeded`) |
| Errores PHPStan nivel 8 | **0** en los siete, **sin baseline** | `vendor/bin/phpstan analyse` con la config del CI |
| Cobertura del repo más débil | **60,2 % (members)**, 1.079 tests | `~/.local/bin/cobertura-local.sh` (pcov) |
| MTTR de las altas cerradas | **0,9 días de media · 0,2 de mediana · 13,7 el peor** (233 cerradas) | `gh issue list --state closed --json createdAt,closedAt` |

Cobertura completa medida hoy: core 71,2 % · members **60,2 %** · enroll 80,8 % · gateway 74,6 % ·
shifts 73,0 % · publisher 80,5 % · assistant 84,0 %.

## Qué se midió

- **F0:** línea base de 11 repos con JSON versionado y negativo demostrado (`5a2798a`).
- **F1:** SonarQube 26.10.0 local, 11 proyectos, cobertura importada y negativo (0,0 % sin reporte).
- **F2:** Semgrep con 8 reglas propias validadas, gitleaks con allowlist justificada (0 secretos en 11),
  `composer audit` (0 avisos) y 26 hallazgos de Sonar triados (0 reales; CORS a revisión).
- **F5:** job `calidad` con ratchet en los siete, verde en `main` y **rojo demostrado** bajando la base a mano.
- **F6:** 1.279 errores de nivel 8 congelados y después **cero**, repo a repo.

## Qué se arregló (defectos con impacto real)

**Firmas y pagos.** La firma de Redsys se calculaba sobre `false` cuando `openssl_encrypt()` fallaba: salía un
HMAC sobre clave vacía que *parecía* válido y el TPV lo rechazaba sin rastro en el log. Ahora se registra el
error y el cobro recibe `WP_Error`.

**Peticiones sin cuerpo.** Cinco redes de publisher, más core, gateway y enroll, llamaban a `wp_remote_post()`
con `'body' => wp_json_encode(...)` sin cast: un `false` enviaba una petición vacía.

**Identificadores y taxonomías.** `get_the_ID()`/`get_post()` pueden devolver `false`/`null` y llegaban a
`get_post_meta()` y `wp_get_post_terms()` (15 sitios en shifts, más enroll y members). Y el resultado de
`wp_get_post_terms()` se comprobaba con `empty()`, que **no cubre un `WP_Error`**: se leía `[0]` sobre un error
de taxonomía y reventaba el guardado.

**Listados y exportadores.** `WP_Query::$posts` es `nullable` y se recorría con `foreach` y se asignaba a
`WP_List_Table::$items` (11 bucles y 4 asignaciones). Los exportadores CSV/PDF además podían recibir IDs
sueltos y leían `$post->ID` sobre un entero.

**Cuentas borradas.** `get_userdata()` devolvía `false` y se leía `->user_login` / `->display_name` en el
aviso al administrador: el fatal llegaba justo cuando tocaba avisar.

**Búferes y ficheros.** Cuatro `return ob_get_clean()` en métodos `: string` podían devolver `false` (correo o
página en blanco); `fopen()`/`getStream()` se usaban sin comprobar; `glob()` y `preg_split()` pueden devolver
`false` y `preg_replace()` `null`.

**Fechas.** `strtotime()` devolviendo `false` llegaba a `wp_date()` y a `update_post_meta()`, guardando
cadenas vacías o 1970 sin aviso.

## Qué se retiró de la web

**Nada en este goal.** No se tocaron las webs: el encargo lo excluía y la matriz de promesas sigue pendiente de
decisión de producto en dos puntos (`publisher#14`: la web dice 8 redes y el código tiene 7; CORS de
`assistant#23`). Cerrar esos dos exige elegir entre implementar o corregir el texto — decisión de JC.

## Errores propios (y qué se hizo)

1. **Medir con la caché de PHPStan caliente**: 46 errores donde había 26, con `class.notFound` fantasma.
   Desde entonces, `clear-result-cache` antes de cada medición.
2. **Docblocks de una línea con etiqueta** (`/** Desc. @param ... */`): PHPCS los acepta y **PHPStan los ignora**
   como etiqueta. Siete firmas «arregladas» seguían fallando. Ahora, bloque multilínea siempre.
3. **Guardias `instanceof` sobre dobles de test**: rompieron la verificación de certificado (el nombre salía
   vacío) y dos pruebas de Google Photos. Los tests lo cazaron; se cambió a `isset()`/`is_object()`.
4. **Extraer métodos a un trait contando llaves a ojo**: partió un `?>` y dejó el fichero sin compilar. Se
   revirtió con `git checkout` y el trabajo se documentó en `enroll#53` en vez de dejarlo a medias.
5. **Intentar subir el ratchet en core y gateway** cuando la guarda subía la complejidad: se resolvió con
   código (`temp_stream()`, `pagos_de()`). En enroll no hubo margen y la subida (204→205) quedó justificada en
   el baseline y con issue.

## Riesgos abiertos

- **F4 comercial sin probar**: compra en Redsys TEST, emisión, desbloqueo PRO, actualización y revocación.
  Bloqueo de negocio: hace falta un pedido y una licencia reales.
- **Releases desfasados**: `license-generator` 1.0.2 frente a `v1.0.0`; el tema 2.9.42 frente a `v2.7.0`.
  Publicar es decisión de JC.
- **`Admin_Settings` con 1045 líneas** (`enroll#53`): el ratchet subió 1 punto; la separación de pestañas es la
  solución pendiente.
- **Cobertura baja en members (60,2 %)**: el repo con más código (25.141 LOC) y el que más lejos está del 70 %.
- **`enroll#52`**: causa corregida y no verificada en una instalación limpia; el aviso era intermitente.

## Estado del criterio de terminado

Cerrado: F0, F1, F2, F5, F6, baselines, job de calidad en los siete y license-generator en nivel 8.
Pendiente y bloqueado por decisión o por negocio: F4 (circuito de venta), alineado de releases, matriz de
promesas al 0 y D1–D10 completos por repo.

Un CI verde mide una parte, no el trabajo. Lo que falta está arriba, con nombre y motivo.
