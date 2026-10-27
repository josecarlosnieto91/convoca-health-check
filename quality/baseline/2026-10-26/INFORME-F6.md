# F6 — PHPStan nivel 8 y burn-down

Fecha: 2026-10-26. Fuente de cada cifra: la ejecución de `vendor/bin/phpstan analyse` con la config del CI de
cada repo. Nada va de memoria.

## Qué se congeló

PHPStan **nivel 8** en los siete repos vendibles, con baseline versionada (`phpstan-baseline.neon`) e incluida
desde la config que usa el CI. El nivel 8 es el que exige el proyecto; la baseline no es una amnistía: es una
deuda fechada que **solo puede bajar**.

| Repo | Errores congelados |
|---|---:|
| core | 193 |
| members | 282 |
| enroll | 357 |
| gateway | 107 |
| shifts | 199 |
| publisher | 63 |
| assistant | 78 |
| **Total** | **1.279** |

Detalle de commits y del procedimiento en el goal `convoca-terminado-y-seguridad.md` (F6, paso 1).

## Burn-down de members (primera repo)

**282 → 263** errores, en seis commits y 19 correcciones reales. Ninguna es un cast para callar al analizador:
cada una cambia o documenta un comportamiento que podía fallar en silencio.

| Commit | Correcciones |
|---|---|
| `9ce9a9d` | dos formularios públicos: `ob_get_clean()` puede devolver `false` y `str_replace()` daba HTML vacío |
| `35ba435` | `strrchr()` en el dominio de correo; `wp_date('n')` en el trimestre; `get_permalink()` en la URL de login |
| `ebf6261` | cuatro `return ob_get_clean()` en Mi Área y verificación de certificado |
| `8c91462` | `(array) glob()` colaba un `false`; `wp_json_encode()` antes de `md5()`; caducidad del certificado con reserva `gmdate()` |
| `dbd5fa6` | fechas de documento y bloqueo con reserva visible; `strtotime(false)`; dominio del PDF; lectura del PDF con error explícito |
| `d0576bb` | la columna de fecha del listado declara `string` y devolvía `int\|string\|false` |

## Composición de los 263 restantes (members)

Clasificados por el identificador que emite PHPStan, no a ojo:

| Identificador | Nº | Qué es |
|---|---:|---|
| `missingType.iterableValue` | 96 | arrays sin genéricos (`array<string, mixed>`): documentación, no fallo |
| `property.nonObject` | 60 | casi todos `int\|WP_Post` de `WP_Query::$posts`: **artefacto del stub de WordPress**, no defecto |
| `missingType.parameter` | 36 | parámetros sin tipo declarado |
| `missingType.return` | 29 | métodos sin tipo de retorno |
| `argument.type` | 23 | **los que importan**: un tipo que no encaja en una función de WordPress |
| `foreach.nonIterable` | 11 | recorrer algo que puede no ser iterable |
| `assign.propertyType` | 4 | asignación a propiedad con otro tipo |
| `return.type` | 3 | retorno que no cumple la firma |
| `offsetAccess/property.notFound` | 2 | acceso a algo que puede no existir |

Lectura honesta: **161 de 263 son tipos que faltan en la documentación**, 60 son ruido del stub y **unos 32 son
defectos de tipo reales**, de los que ya se han cerrado los que afectaban a fechas, buffers y contratos de
retorno. El orden de ataque es: `argument.type` y `return.type` primero (riesgo real), después los genéricos
(mantenibilidad), y los `property.nonObject` solo si se sustituye el stub por uno propio tipado.

## Cómo se sigue (regla del ratchet)

1. Arreglar un lote pequeño y coherente (mismo síntoma, mismos ficheros).
2. `make lint` y `make test` en local, con la suite completa.
3. Regenerar la baseline con la config del CI **sin** incluir la baseline, y comprobar que el número baja.
4. Commit con el número antes y después en el mensaje.
5. CI en `success` (`PHPStan` y `Calidad`). Si sube, no se mergea.

## Qué significa «F6 terminado»

- `phpstan-baseline.neon` vacío o con ignores **con motivo escrito** (nunca un número que se sube porque el CI
  estaba en rojo).
- Los tres identificadores de riesgo (`argument.type`, `return.type`, `assign.propertyType`) a cero.
- Los `property.nonObject` resueltos con un stub propio tipado o documentados como artefacto del oficial.
- PHPStan nivel 8 sin baseline en el comando del CI (la baseline puede quedarse como fichero vacío).

## Cierre — 27/10/2026: los siete a cero

Burn-down completo. **1.279 → 0** errores de nivel 8, y **sin baseline en ninguno de los siete**: el fichero
`phpstan-baseline.neon` se ha eliminado y su `includes:` retirado de la config, así que cualquier error nuevo
rompe el CI desde el primer commit.

| Repo | Inicial | Final | Commit de cierre | PHPMD (base) | Tests |
|---|---:|---:|---|---:|---:|
| core | 193 | **0** | `56b2689` | 119 (=) | 656 |
| members | 282 | **0** | `c662849` | 233 (base 234) | 1.083 |
| enroll | 357 | **0** | `7ffb4ca` | 205 (base 204 → 205, justificado) | 934 |
| gateway | 107 | **0** | `fe4b511` | 90 (=) | 491 |
| shifts | 199 | **0** | `255be53` | 91 (=) | 447 |
| publisher | 63 | **0** | `3928e07` | 82 (=) | 476 |
| assistant | 78 | **0** | `bd72a5b` | 43 (=) | 279 |

En los siete: `make lint` en rc=0, la suite completa en verde y **CI en `success`** (PHPStan, PHPCS, PCP,
PHPUnit 8.2/8.3, acoplamiento entre plugins, higiene de datos e integración con WordPress real).

### Qué se arregló (no era cosmético)

Las correcciones con impacto en ejecución, por orden de gravedad:

- **Firmas de Redsys calculadas sobre `false`** (gateway): sin clave derivada, el HMAC se calculaba con cadena
  vacía y salía una firma que *parecía* válida. Ahora se registra el error y `sign()` devuelve vacío.
- **Cuerpos de `wp_remote_post()` con `wp_json_encode()` sin comprobar** (publisher ×5, core, gateway, enroll):
  un `false` enviaba una petición sin cuerpo.
- **`get_the_ID()`/`get_post()` normalizados a `int`** (shifts, enroll, members): se pasaban `int|false` a
  `get_post_meta()` y `wp_get_post_terms()`.
- **`WP_Error` de taxonomías leído con `[0]`** (shifts, enroll): `empty()` no cubre un `WP_Error`; era un fatal.
- **`$query->posts` nullable** recorrido con `foreach` y asignado a `WP_List_Table::$items` (members, enroll,
  shifts, core).
- **Exportadores CSV/PDF** que recorrían `posts` con posibles IDs sueltos (gateway).
- **`get_userdata()` `false`** leído como objeto (members, enroll).
- **`ob_get_clean()`** en métodos que declaran `string` (core, members, gateway).
- **`preg_replace`/`preg_split`/`glob`** con `null`/`false` hacia funciones que exigen cadena o iterable.

### Las dos veces que la puerta dijo no

La puerta de calidad rechazó dos tandas por PHPMD y **se resolvió con código, no subiendo la base**:
`Admin_Backup::construir_zip()` en core (se extrajo `temp_stream()`) y `PDF_Exporter::export()` en gateway (se
extrajo `pagos_de()`). En core, la comprobación de `WP_Post` se sustituyó por la precondición documentada de
`page_state()` para no añadir una rama.

**Única subida, justificada:** `convoca-enroll` 204 → **205** porque `Admin_Settings` pasa a 1045 líneas al
documentar los tipos de sus ocho pestañas. No se borró la documentación para maquillar la métrica: el motivo
está escrito en `.quality/baseline.json` y la separación de las pestañas queda en **convoca-enroll#53**.

### Cómo se hizo (repetible)

1. Listado completo con `--error-format=json` y la config del CI **sin** baseline.
2. Tanda 1: tipado (void nativo donde no hay retorno, `mixed` en parámetros sin tipo, docblocks en iterables).
3. Tanda 2: los errores con nombre (`argument.type`, `return.type`, `property.nonObject`, `offsetAccess`),
   uno a uno, leyendo el código.
4. `make lint`, `make test` y PHPMD **antes** de commitear; `phpcbf` para la alineación de docblocks.
5. Baseline regenerada por repo; cuando llega a 0, **se elimina el fichero** y se retira el `includes:`.
6. CI en verde y espera real al run, no al `push`.
7. Trampa aprendida: hay que **limpiar la caché de resultados de PHPStan** (`clear-result-cache`) antes de cada
   medición; con caché caliente aparecen `class.notFound` fantasma y desaparecen errores reales.
8. Trampa aprendida: los dobles de los tests no son `WP_Post`/`WP_User` reales. Las guardas con `instanceof`
   rompen la suite (member y Google Photos): donde el doble tiene la misma forma, se comprueba la propiedad
   (`isset`) o `is_object()`.

### Lo que sigue fuera de F6

- **F4 comercial** (compra en Redsys TEST, emisión, PRO desbloqueado, actualización y revocación): bloqueo de
  negocio, requiere un pedido y una licencia reales.
- Defectos abiertos: **publisher#14** (8 vs 7 redes), **enroll#52** (textdomain intermitente al primer
  arranque), **assistant#23** (CORS), **enroll#53** (separar las pestañas de ajustes).
