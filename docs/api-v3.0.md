# Convoca API Pública v3.0 — Declaración de congelación

**Fecha de congelación:** 2026-08-07
**Estado:** 🔒 CONGELADA

Esta API es pública y estable. Cualquier cambio (añadir, renombrar o eliminar
hooks, REST endpoints, shortcodes) requiere actualizar el baseline
`api/api-v3.0.json` de forma explícita y con justificación.

## Inventario congelado

| Recurso | Cantidad |
|---------|----------|
| Hooks | 206 |
| REST endpoints | 52 |
| Shortcodes | 27 |
| Meta keys | 248 |
| Opciones | 88 |
| **Total elementos API** | **285** (hooks+REST+shortcodes) |

> Estos números **se comprueban**: `scripts/check-api-doc.py` los compara con el inventario real y el
> job `api-freeze` del CI cae si no cuadran. Antes no los miraba nadie, y el documento llegó a declarar
> 164 hooks y 243 elementos cuando la realidad era 202 y 281 (corregido en la pasada 11 del gauntlet).

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

### 2026-10-14 — tres hooks aditivos de la identidad y del alta sin métodos de pago

Motivo: la pasada 9 del gauntlet hizo dos cosas que añaden gancho público y no lo reflejaron en el
inventario congelado, así que el job `api-freeze` estuvo en rojo desde entonces (lo cazó el CI de la
pasada 10, no una lectura).

| Cambio | Elemento | Dónde |
|--------|----------|-------|
| Añadido | `convoca_members_nombre_entidad` | convoca-members (`Identidad::nombre_entidad()`) |
| Añadido | `convoca_members_documento_url` | convoca-members (`Identidad::enlace_documento()`) |
| Añadido | `convoca_gateway_volver_url` | convoca-gateway (`Payment_Handler`) |

Los tres son **compatibles**: nadie los consumía antes de existir. Los dos primeros sacan de la
plantilla el nombre de la entidad y las URLs de sus documentos (que estaban hardcodeadas y con el
cliente real dentro); el tercero da salida a la pantalla del alta cuando no hay ningún método de pago
configurado, que antes era un callejón sin salida.

> Nota para la próxima vez: este gate vive en el CI de `convoca-health-check`, así que no avisa en el
> momento de añadir el hook en `members` o `gateway` — solo cuando algo empuja a health-check. Un
> cambio de API se comprueba a mano con `bash scripts/check-api-freeze.sh` **antes** de dar la pasada
> por cerrada.

### 2026-10-14 — el extractor aprende a leer constantes, y la documentación deja de poder mentir

Motivo: el job `api-freeze` protegía un inventario **que no era el real**. Dos agujeros, los dos del
mismo instrumento:

1. **Cuatro hooks de cron no estaban inventariados** porque su nombre es una **constante de clase**
   (`const CRON_HOOK = 'convoca_publisher_retry_event'`) y el extractor solo miraba literales. Son
   precisamente los que más duele perder de vista: un gancho programado que se renombra sin actualizar
   el inventario deja **el evento en cola sin nadie escuchando**, y nada lo delata.
   Añadidos: `convoca_security_digest` (core), `convoca_publisher_retry_event`,
   `convoca_publisher_scheduled_publish` y `convoca_publisher_apply_spacing` (publisher).
2. **Un fantasma dentro**: `apply_filters( 'convoca_document_theme_' . $suffix )` es un **prefijo**, no un
   hook, y estaba congelado como si lo fuera. El extractor ahora lo distingue y no lo inventaria.

Y en el mismo movimiento, lo que hacía posible que esto pasara desapercibido: **nadie comprobaba los
números que el documento declara**. `docs/api-v3.0.md` decía 164 hooks y 243 elementos cuando la
realidad era 202 y 281. Ahora `scripts/check-api-doc.py` (job `api-freeze`, con su negativo probado)
compara la tabla del documento con el inventario real y cae si no cuadra.

| Cambio | Elemento | Dónde |
|--------|----------|-------|
| Añadido al inventario | `convoca_security_digest` | convoca-core |
| Añadido al inventario | `convoca_publisher_retry_event`, `convoca_publisher_scheduled_publish`, `convoca_publisher_apply_spacing` | convoca-publisher |
| Retirado del inventario | `convoca_document_theme_` (era un prefijo, no un hook) | convoca-core |
| Corregido | tabla «Inventario congelado» (164→205 hooks, 243→284 elementos, 239→248 meta, 85→88 opciones) | docs/api-v3.0.md |
| Añadido | filtro `convoca_email_from` en el núcleo (206→**206** hooks, 284→**285** elementos): el punto único para cambiar el remitente de todos los correos. Es aditivo y lo cazó el guardián de congelación al minuto | convoca-core 2.3.26 |

Los cuatro añadidos son **compatibles**: existían y se disparaban, solo faltaban en la lista.

## Reglas para cambios futuros

1. **Añadir** un hook/endpoint/shortcode: permitido (no rompe compatibilidad),
   pero hay que actualizar el baseline + docs.
2. **Renombrar**: requiere deprecación (ver política v3.0 en wiki) — mantener
   el nombre viejo como alias 1 versión.
3. **Eliminar**: solo en MAJOR version, tras deprecación.
4. El job `api-freeze` del CI fallará si el baseline no se actualiza.
