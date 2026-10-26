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
