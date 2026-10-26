# F0 — Línea base de calidad (cerrada el 26/10/2026)

**Quién:** Mika · **Repo del artefacto:** `convoca-health-check` · **Salida:** `quality/baseline/2026-10-26/`
**Comando único:**

```bash
cd ~/repos/convoca-health-check
./quality/measure-quality.sh --out quality/baseline/2026-10-26 \
  convoca-core convoca-members convoca-enroll convoca-gateway convoca-shifts \
  convoca-publisher convoca-assistant convoca-license-generator convoca-theme \
  convoca-courses convoca-mu-plugins
```

No se ha tocado ni una línea de producto. Los 11 repos quedaron con 0 ficheros sin confirmar (lo registra el
propio JSON en `dirty_files`).

## Tabla

| Repo | LOC | Tests | Cobertura (suelo) | PHPStan | Nivel 8 | CC>15 | CC 10-15 | Nidos>6 | Audit prod | Versión |
|---|---|---|---|---|---|---|---|---|---|---|
| core | 14.392 | 74 | 71,0 % | 5 (0) | **193** | 9 | 26 | 0 | 0 | 2.3.50 |
| members | 25.141 | 147 | 57,9 % | 5 (0) | **282** | **27** | 32 | 0 | 0 | 2.9.19 |
| enroll | 22.969 | 137 | 80,3 % | 5 (0) | **357** | 18 | 25 | 0 | 0 | 2.7.69 |
| gateway | 9.745 | 54 | 74,1 % | 5 (0) | 107 | 10 | 17 | 0 | 0 | 2.13.23 |
| shifts | 8.519 | 52 | 72,3 % | 5 (0) | 199 | 11 | 9 | 1 | 0 | 2.5.35 |
| publisher | 9.270 | 46 | 77,5 % | 5 (0) | 63 | 8 | 12 | 0 | 0 | 1.22.13 |
| assistant | 7.461 | 38 | 83,2 % | 5 (0) | 78 | 7 | 5 | 0 | 0 | 0.2.23 |
| license-generator | 362 | 3 | 96,3 % | **0** (0) | 15 | 0 | 0 | 0 | 0 | 1.0.2 |
| theme | 3.215 | 18 | 76,0 % | 5 (0) | 18 | 1 | 1 | 0 | 0 | 2.9.42 |
| courses | 12.882 | 49 | 70,4 % | 5 (0) | 20 | 5 | 5 | 0 | 0 | 0.3.3 |
| mu-plugins | 337 | 4 | 97,9 % | sin config | sin config | 0 | 0 | 0 | 0 | 1.1.0 |
| **TOTAL** | **114.293** | **622** | — | — | **1.332** | **96** | **132** | **1** | **0** | — |

Los **7 vendibles** suman **97.497 LOC** y **1.279** errores a nivel 8 (el 96 %).

## Lo que corrige esta medición

1. **Los errores de nivel 8 no eran «1.000–2.000»**: son **1.332 medidos** en los 10 repos con config.
2. **El «~143 funciones >10» que dije antes estaba mal.** Salía de contar los *avisos* que imprime PHPCS,
   que mezclan complejidad ciclomática con anidamiento y solo cubrían 9 repos. El número real de funciones
   con ciclomática **>10** es **228** (96 por encima de 15 y 132 entre 10 y 15). El anidamiento es otro
   problema y aparte: 1 función con anidamiento >6 (shifts) y 11 entre 4 y 6.
3. **`members` no es el peor en nivel 8**: lo es **`enroll` (357)**, con más LOC y más superficie. `members`
   sigue siendo el peor en complejidad (27 funciones >15) y en cobertura (57,9 %).

## Clases de error silencioso que ya aparecen (sin SAST todavía)

Esto es lo que ningún test unitario caza y justifica F2:

- **`int|WP_Post` sin comprobar** — 45 en members, 6 en assistant, 6 en enroll. `get_post()` puede devolver
  `WP_Post`, `int` o `null`; acceder a `->ID` sobre un entero no da fatal: da un dato incorrecto o un aviso
  que en producción está apagado.
- **`string|false` metido en `esc_html`/`esc_attr`** — 4 en core, 2 en gateway. Si la función falla, el
  escape devuelve `''` y el contenido desaparece sin error.
- **`resource|false`** — 6 `fclose`, 3 `fputcsv`, 2 `fgetcsv`, 2 `rewind` en core: si la apertura falla, se
  opera sobre `false` y el error se pierde.
- **`WP_Post|null`** — 7 en enroll (`$post_title`), 5 en members.
- **`WC_Order|WC_Order_Refund|true`** — 2 en license-generator: `add_order_note()` sobre `true` es un fatal
  que hoy nadie ve porque no hay tipos.
- **`object::$ID` sin tipo** — 14 en shifts, 4 en gateway, 5 en publisher: el objeto puede no tener la
  propiedad y PHP 8 emite un warning que en producción no se lee.

## Negativo demostrado

El script resuelve la config de PHPStan del CI y el resultado se comprobó contra un número conocido:

- `convoca-core` → **193** errores a nivel 8 (coincide con la medición manual previa).
- `convoca-members` → **282** (coincide).

Si cualquiera de los dos hubiera salido 0, la config sería la equivocada y F0 no se habría cerrado.

## Límites (declarados, no maquillados)

- **La cobertura de la tabla es el suelo** de `.coverage-floor`, no una medición nueva. Medir cobertura real
  es de F6 (o de `cobertura-local.sh`), no de F0.
- **`mu-plugins` no tiene config de PHPStan** ni nivel declarado: se mide complejidad y audit, y se anota el
  hueco. No se le inventa una config.
- **Aún no hay SAST, SCA de secretos ni análisis de flujo**: eso es F2. El audit de dependencias de
  producción dio **0 avisos en los 10 repos**.
- **La vara de complejidad es propia** (`quality/phpcs-metrics.xml`): 10/15 y 4/6. Es la misma para todos,
  para que las cifras se comparen entre repos.

## Decisión de Aikido, con los números delante

El goal decía: se decide al cerrar F0. Los datos:

- **SCA de producción: 0 avisos en 10/10 repos.** Lo que Aikido aportaría aquí (reachability sobre CVE) no
  tiene nada que priorizar todavía.
- **Secretos: sin medir** hasta F2 (gitleaks). Es la única pieza donde Aikido podría añadir algo.
- **SAST: sin medir** hasta F2 (Semgrep + Sonar). Cuando estén, se compara su cobertura con la de Aikido.

**Recomendación:** no abrir cuenta todavía. Se revisa al cerrar F2 con el recuento de secretos y SAST; si
gitleaks y Semgrep cubren sin ruido, Aikido no compensa una cuenta y 10 repos de cuota.

## Siguiente

F1: SonarQube Community Build en Cassiopeia (podman, Postgres, digest fijo, token en `pass`) y los 11
proyectos con la cobertura del clover cuando exista.
