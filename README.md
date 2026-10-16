# Convoca Health Check

Batería de validación permanente del ecosistema Convoca. Un único comando valida
todas las funcionalidades públicas y las integraciones entre plugins, sin
depender de auditorías manuales.

## Uso

```bash
# Local (WP en la ruta por defecto)
./bin/convoca-health

# Ruta específica
./bin/convoca-health --path=/var/www/demo.getconvoca.app/public

# En un servidor remoto vía SSH (Andromeda usa fish → bash explícito)
./bin/convoca-health --ssh=andromeda --path=/var/www/demo.getconvoca.app/public
```

## Salida

```
============================================
  CONVOCA HEALTH CHECK
  https://demo.getconvoca.app
============================================
== Convoca Core ==
  [PASS] Core :: Archivos de admin (admin-appearance.php)
  [PASS] Core :: Rate limiter bloquea abuso (persona P,P,P,B · conexión bloquea en la 24 de 24)
  ...
== Integraciones ==
  [PASS] Integraciones :: 6 namespaces REST
  [PASS] Integraciones :: Members←Gateway activa membresía (estado=activo cuota=activa)
  [PASS] Integraciones :: Enroll←Gateway confirma inscripción (estado=confirmada)
============================================
  RESUMEN: 88 PASS / 0 FAIL / 1 WARN
  Coverage: 100%  (5.3s)
============================================
```

Exit code 0 = todo PASS. Exit code 1 = hay FAILs (para CI).

## Cobertura

| Componente | Checks | Valida |
|---|---|---|
| Core | 11 | archivos admin, rate limiter, locks, REST protegido |
| Members | 13 | CPTs, shortcodes, REST, estados, alta/edición/renovación/baja |
| Enroll | 14 | CPTs, shortcodes, REST, cron, JSON-LD, poster, actividad+inscripción |
| Gateway | 12 | credenciales, clave enc:, shortcodes, webhook, páginas OK/KO, validaciones |
| Shifts | 9 | CPT, taxonomía, shortcodes, REST, CRUD |
| Publisher | 7 | canales, prefijo coherente, tabla retry, REST, async |
| Assistant | 9 | shortcode, CPTs, taxonomías, search funcional, índice, rebuild |
| Theme | 6 | tema activo, patterns, theme.json, dark-mode, home |
| Integraciones | 3 | namespaces REST, Members←Gateway, Enroll←Gateway |

**Total: 88 checks** — ejecutados en ~5s sin dejar residuos (los datos de prueba se limpian).

## CI

GitHub Actions ejecuta la batería diariamente (cron 06:30 UTC) y bajo demanda
(`workflow_dispatch`). Requiere secrets:

- `ANDROMEDA_SSH_KEY` — clave SSH privada para conectar a Andromeda
- `ANDROMEDA_KNOWN_HOSTS` — host keys de Andromeda

## Desarrollo

- `health-check.php` — la batería completa (WP-CLI `wp eval-file`)
- `bin/convoca-health` — wrapper bash (path/ssh/format)
- `observability.php` — panel de estado en vivo (cron, licencias, logs, memoria, errores, REST) vía `wp eval-file`
- Añade un check nuevo con el patrón `hc_out('Componente', 'Nombre', $condicion, $detalle)`

## Regla

Nunca añadir un check que deje residuos en la BD. Todo dato de prueba se crea
y se elimina dentro del mismo check.

## Licencia

GPL-2.0-or-later. Ver [LICENSE](LICENSE).


## Los catálogos de traducción (`.pot`), cómo se regeneran

Los `.pot` se generan a mano una vez y luego se quedan atrás — pasó en **nueve** repos del ecosistema. El
comando es el mismo en todos (mismos argumentos, para que no haya dos varas):

```
wp i18n make-pot . languages/<fichero>.pot --slug=<dominio> --domain=<dominio> \
  --exclude=node_modules,vendor,tests,.git,build,dist
```

Y `scripts/check-pot-al-dia.py` vigila que no vuelvan a desfasarse: compara la versión que el `.pot` declara
en su cabecera con la del fichero principal, y avisa si el catálogo apunta a ficheros que ya no existen
(el del tema arrastraba 133 referencias a un `build-dir/` retirado). Corre en el vigilante diario del
ecosistema, que tiene los clones locales a mano.

## El `Stable tag` del `readme.txt`, cuadrado (issue #13)

Al subir la versión de un plugin se toca la cabecera del fichero principal y el `CHANGELOG`, y el
**`Stable tag` del `readme.txt` se queda atrás** sin que nadie lo note. No es cosmético —un `Stable tag` que
no cuadra impide a wordpress.org servir los archivos correctos— y además pone **en rojo** el trabajo de
Plugin Check del repositorio, donde ese rojo ya se confundió una vez con un fallo de red.

`scripts/check-stable-tag.py` compara la versión del repo (la cabecera del fichero que declara
`Plugin Name:`, o `Version:` de `style.css` en un tema) con el `Stable tag` del `readme.txt`:

```
python3 scripts/check-stable-tag.py ~/repos/convoca-core ~/repos/convoca-members …
```

Avisa también si el readme **no trae** `Stable tag` (no se da por bueno lo que no está). Y corre en el
mismo vigilante diario que los otros tres.

**Dónde mira el instrumento, que es lo que hay que comprobar antes de creer su verde**: lee la cabecera del
fichero que declara `Plugin Name:`, no cualquier `Version:` de cualquier `*.php` del repositorio (hay
ficheros de prueba con su propia versión). Probado en negativo: un `Version: 0.0.1` en un fichero suelto no
le despista; un `Stable tag` que no cuadra, un readme sin el campo o un repo sin readme, sí le hacen caer.

### El vigilante diario, y el defecto que tenía

Los cuatro guardianes (higiene, `.pot`, colisiones y `Stable tag`) los llama
`~/.hermes/scripts/convoca-higiene.sh`. **Silencio = todo limpio**: en un job de script, stdout vacío no
entrega nada, así que un guardián que no se ejecuta se ve igual que un guardián que no encuentra nada.

El 15/10/2026 se midió que eso no era teoría: los bloques del `.pot` (duplicado) y de **colisiones** estaban
dentro del `if [ ! -f "$GUARDIAN" ]` que comprueba el guardián de higiene, así que en el camino normal —el
único que ocurre— **el de colisiones no se ejecutaba nunca**. Ahora cada guardián es independiente: que falte
uno no silencia a los demás, no hay dos copias del mismo bloque, y está probado haciendo que **cada uno
hable** cuando debe (un sandbox con una colisión, un `.pot` desfasado, un `Stable tag` descuadrado y un dato
de cliente: los cuatro mensajes aparecen).
