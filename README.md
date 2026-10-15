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
  [PASS] Core :: Rate limiter bloquea abuso (P,P,P,B)
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
