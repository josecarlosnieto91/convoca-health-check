# Convoca API Pública v3.0 — Declaración de congelación

**Fecha de congelación:** 2026-08-07
**Estado:** 🔒 CONGELADA

Esta API es pública y estable. Cualquier cambio (añadir, renombrar o eliminar
hooks, REST endpoints, shortcodes) requiere actualizar el baseline
`api/api-v3.0.json` de forma explícita y con justificación.

## Inventario congelado

| Recurso | Cantidad |
|---------|----------|
| Hooks | 127 |
| REST endpoints | 51 |
| Shortcodes | 22 |
| Meta keys | 209 |
| Opciones | 67 |
| **Total elementos API** | **200** (hooks+REST+shortcodes) |

## Mecanismo de verificación

- **Local:** `bash scripts/check-api-freeze.sh`
- **CI:** job `api-freeze` en `tests.yml` (corre en cada push)
- El script regenera el inventario desde el código y lo compara con
  `api/api-v3.0.json`. Si difiere → FAIL.

## Documentación generada automáticamente

| Documento | Fuente | Regenerar con |
|-----------|--------|---------------|
| `docs/openapi.yaml` | Código REST | `python3 scripts/api-extract.py` + generador OpenAPI |
| `docs/hooks-inventario.md` | Código hooks/shortcodes | generador de hooks |

> Toda la documentación sale del código. No se mantiene a mano.

## Namespaces REST

| Namespace | Plugin |
|-----------|--------|
| `convoca/v1` | core (rate limiter, assistant search, admin metrics) |
| `convoca-members/v1` | members (21 endpoints: perfil, alta, renovar, certificados) |
| `convoca-enroll/v1` | enroll (12 endpoints: actividades, inscripciones, checkin) |
| `convoca-gateway/v1` | gateway (redsys notify, estado) |
| `convoca-shifts/v1` | shifts (6 endpoints: turnos, CRUD) |
| `convoca-publisher/v1` | publisher (3 endpoints: test, status, publish) |

## Reglas para cambios futuros

1. **Añadir** un hook/endpoint/shortcode: permitido (no rompe compatibilidad),
   pero hay que actualizar el baseline + docs.
2. **Renombrar**: requiere deprecación (ver política v3.0 en wiki) — mantener
   el nombre viejo como alias 1 versión.
3. **Eliminar**: solo en MAJOR version, tras deprecación.
4. El job `api-freeze` del CI fallará si el baseline no se actualiza.
