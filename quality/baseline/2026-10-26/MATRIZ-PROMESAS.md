# Matriz de promesas de la web — getconvoca.app (26/10/2026)

Cada línea de la web es un criterio de aceptación. Veredictos: **probado** (hay ejecución), **existe sin
probar** (hay código, no hay prueba funcional mía), **no lo encuentro** (defecto).

## Globales

| Promesa | Evidencia | Veredicto |
|---|---|---|
| Instalar en 2 minutos | D1: core instalado y activado en WordPress limpio desde el ZIP del release; home 200 | **existe sin cronómetro** |
| Importar el Excel de socios | `members/admin/class-xlsx-reader.php` + pantalla de importación | existe sin probar |
| Carnet digital | `members/includes/PDF_Card.php` | existe sin probar |
| Usuarios ilimitados | No hay límite por usuario en el código de licencia | existe sin probar |
| Tus datos en tu web | Arquitectura local; **excepción**: push vía ntfy.sh (servicio externo) | **parcial** |
| Exportar a CSV | `members/admin/class-csv-exporter.php`, `gateway/CSV_Exporter.php` | existe sin probar |
| Gratis para siempre | `Publisher::REDES_GRATIS` y módulos sin caducidad en código | existe sin probar |
| PRO 99 €/año | Precio solo en la web; no hay nada en código que lo verifique | comercial |
| 14 días de devolución | Política comercial | comercial |
| 1 licencia = 1 sitio | `license-generator`: `'max_sites' => 1` | **probado en código** |
| Soporte <24 h | Compromiso comercial | comercial |
| Actualizaciones incluidas | `core/includes/Upgrade_Manager.php` | existe sin probar E2E |

## Por plugin

| Plugin | Promesa | Evidencia | Veredicto |
|---|---|---|---|
| Core | logging, licencias, capacidades, PDFs, plantillas, webhooks | Logs, License_Manager, Capabilities, PDF, Email_Layout, Webhook_Manager | probado en suites |
| Core | notificaciones push | `Push_Notifier` contra **ntfy.sh** | existe; **servicio externo** |
| Members | fichas, cuotas, carnets, área personal, gamificación, certificados | CPT_Miembro, PDF_Card, Voluntariado_Gamification, Certificate_Generator | existe |
| Enroll | inscripciones, lista de espera, check-in, evaluaciones, CRM | Motor_Inscripcion, Checkin_Handler, evaluaciones, listados | probado en suites |
| Enroll | check-in PWA **offline** | `public/assets/pwa/sw.js` + `Checkin_PWA` | existe sin probar sin red |
| Gateway | tarjeta | Redsys_Client | probado en suites (TEST) |
| Gateway | **Bizum** | `Redsys_Client::METHOD_BIZUM = 'z'` | existe sin probar |
| Gateway | «sin comisiones externas» | Redsys cobra comisión al comercio | **promesa a matizar** |
| Shifts | calendario, check-in, estadísticas, sincronización | admin/rest/cpt | probado en suites |
| Shifts | gamificación | **no aparece en el código de shifts** (sí en members) | **no lo encuentro** |
| Publisher | **8 redes** | 3 gratis (Facebook **+ Instagram**, X, LinkedIn) + 4 PRO (GMB, Mastodon, Telegram, TikTok) = **8 redes** en 7 canales | **probado** (9 tests de Instagram: publicación y verificación) |
| Publisher | GMB, plantillas, reintentos, cifrado de tokens | class-googlemybusiness, class-crypto, cola | existe |
| Assistant | widget, sin IA, 100 % local | REST + widget + índice local | probado en suites |
| PRO | automatizaciones, gamificación, offline, carteles, webhooks, bloques, PDF | código en 6 plugins | existe; falta prueba funcional por plan |
| PRO | 8 redes | Instagram va dentro del canal Facebook/Instagram | **probado** |
| PRO | notificaciones push | ntfy.sh | existe; servicio externo |

## Lo que hay que decidir o corregir

1. ~~**8 redes**~~ → **no era defecto**: Instagram se publica desde el canal «Facebook / Instagram»
   (contenedor + publicación, 9 tests). El error fue mío al contar clases de canal en vez de redes. publisher#14 cerrado.
2. **«Sin comisiones externas»** → **decidido**: «sin comisiones de Convoca». Aplicado en la home el 27/10.
3. **Push por ntfy.sh** → **decidido**: opt-in, documentado y con aviso en la política de privacidad.
4. **Gamificación en Shifts** → **decidido**: fuera de la tarjeta de Shifts (sigue en PRO, donde vive Members).
   Aplicado en la home el 27/10.
5. **«2 minutos»** → **decidido**: sin cifra. Home, «plugin-wordpress-asociaciones» y «cómo funciona»
   pasan a «en un rato» / «es rápido» el 27/10.

## Decisiones de producto (27/10)

- **Bizum**: probado en producción en Lugg y funcionaba. Se queda anunciado.
- **Check-in sin cobertura**: hay que probar la PWA sin red y, según salga, ajustar el texto.
- **Licencia = 1 sitio**: el staging del mismo sitio no consume licencia.
- **Precio PRO**: 99 €/año, IVA incluido, renovación manual. Al caducar se paran las actualizaciones;
  el plugin sigue funcionando.
- **«Gratis para siempre»**: definido en `convoca-docs/docs/guides/licensing.md` (freemium por plugin,
  con la tabla de qué es PRO en cada uno). Corregido: 1 sitio + staging, 8 redes y courses.

## Lo que sí está probado hoy

- El **asset del release** de core (493 ficheros, 381 de `vendor/`, autoload presente) se instala y activa en
  un WordPress limpio sin avisos del plugin, y la home responde 200.
- El mismo WordPress instala **enroll** encima y ambos quedan activos; aparece un aviso de traducción cargada
  antes de `init` → issue enroll#52.
- Los 7 vendibles tienen ZIP en su release, con tamaños entre 1,0 y 5,7 MB.
- `max_sites: 1` en la emisión de licencias.
