# CONTRIBUTING.md — Convoca Health Check

> Guía para contribuir a la batería de validación del ecosistema Convoca.

## Primeros pasos

1. Clona el repositorio
2. Asegúrate de tener WP-CLI disponible en el host donde ejecutes la batería
3. Ejecuta la batería contra la demo:
   `./bin/convoca-health --ssh=andromeda --path=/var/www/demo.getconvoca.app/public`
4. Asegúrate de que todo pasa antes de hacer cambios

## Entorno de desarrollo

La batería se ejecuta contra una instalación real de WordPress (local o remota):

```bash
# Local
./bin/convoca-health --path=/ruta/al/wp

# Remoto vía SSH
./bin/convoca-health --ssh=andromeda --path=/var/www/demo.getconvoca.app/public

# Directo con WP-CLI (sin wrapper)
wp eval-file health-check.php --path=/ruta/al/wp
wp eval-file observability.php --path=/ruta/al/wp
```

Modos de ejecución (flags o variables de entorno):

- `--local` / `CONVOCA_HC_LOCAL=1`: los checks de configuración de sitio pasan a WARN.
- `--safe` / `CONVOCA_HC_SAFE=1`: no crea datos reales y bloquea el envío de emails (producción).

## Estándares de código

- **PHP 8.1+** y **WP-CLI** obligatorios
- Cada check sigue el patrón `hc_out('Componente', 'Nombre', $condicion, $detalle)`
- **Regla de no-residuos**: todo dato de prueba se crea y se elimina dentro del mismo check
- Exit code 0 = todo PASS; 1 = hay FAILs (para CI)

## API Freeze

La API pública v3.0 está congelada (`docs/api-v3.0.md`). Antes de tocar hooks,
REST o shortcodes:

```bash
bash scripts/check-api-freeze.sh
```

Si difiere, actualiza `api/api-v3.0.json` **solo** si el cambio es intencionado.

## Tests

```bash
# E2E con Playwright
npx playwright install chromium
npx playwright test
```

## Pull Requests

1. Crea una rama: `git checkout -b feat/mi-cambio`
2. Haz tus cambios con commits descriptivos
3. Ejecuta `bash scripts/check-api-freeze.sh` y la batería
4. Push y abre PR en GitHub
5. El CI ejecutará la batería automáticamente

### Convención de commits

```
feat: descripción breve
fix: descripción del bug corregido
refactor: qué se reorganizó
docs: qué se documentó
chore: tarea de mantenimiento
```

## Versionado

Se sigue **SemVer**. Este repositorio no es un plugin de WordPress; la versión
se declara en `package.json`.

## Contacto

- **Autor**: José Carlos Nieto Ramos
- **GitHub**: [josecarlosnieto91](https://github.com/josecarlosnieto91)
- **Web**: [getconvoca.app](https://getconvoca.app)
