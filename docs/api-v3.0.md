# Convoca API Pública v3.0 — Declaración de congelación

**Fecha de congelación:** 2026-08-07
**Estado:** 🔒 CONGELADA

Esta API es pública y estable. Cualquier cambio (añadir, renombrar o eliminar
hooks, REST endpoints, shortcodes) requiere actualizar el baseline
`api/api-v3.0.json` de forma explícita y con justificación.

## Inventario congelado

| Recurso | Cantidad |
|---------|----------|
| Hooks | 164 |
| REST endpoints | 52 |
| Shortcodes | 27 |
| Meta keys | 239 |
| Opciones | 85 |
| **Total elementos API** | **243** (hooks+REST+shortcodes) |

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

## Cambios deliberados

### 2026-09-23 — la funcionalidad de interfaz pasa del mu-plugin de un sitio a los plugins

Motivo: el theme usaba seis shortcodes que sólo registraba un mu-plugin privado de un sitio, así
que «plugins Convoca + theme» no pintaba cabecera, pie ni fecha de evento.

| Cambio | Elemento | Dónde |
|--------|----------|-------|
| Añadido | `[convoca_menu]`, `[convoca_socials]`, `[convoca_cuando]`, `[convoca_donde]`, `[convoca_relacionadas]`, `[convoca_stats]` | convoca-core |
| Añadido | `convoca_social_links`, `convoca_site_stats`, `convoca_event_meta_legacy_keys` | convoca-core |
| Retirado | `convoca_theme_stats` → `convoca_site_stats` | convoca-theme → convoca-core |
| Retirado | `convoca_theme_social_instagram/facebook/youtube/handle` → `convoca_social_links` | convoca-theme → convoca-core |

Los elementos añadidos son compatibles (nadie los consumía antes de existir). Los retirados estaban
en el theme y su sustituto está en Core: se cambian en los sitios en el mismo despliegue (`convoca_social_links`
alimenta tanto el shortcode como los tokens del pie).

### 2026-09-23 — el extractor ya no lee artefactos de compilación

`build-dir/` del theme es una copia del código: al leerlo, el inventario declaraba hooks y shortcodes
que el fuente ya no tenía y la comparación con el baseline se hacía contra fantasmas. Excluido en
`scripts/api-extract.py`.

## Reglas para cambios futuros

1. **Añadir** un hook/endpoint/shortcode: permitido (no rompe compatibilidad),
   pero hay que actualizar el baseline + docs.
2. **Renombrar**: requiere deprecación (ver política v3.0 en wiki) — mantener
   el nombre viejo como alias 1 versión.
3. **Eliminar**: solo en MAJOR version, tras deprecación.
4. El job `api-freeze` del CI fallará si el baseline no se actualiza.
