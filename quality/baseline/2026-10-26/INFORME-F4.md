# F4 — Promesas y circuito de venta (26/10/2026)

## D1 · Instalación desde el asset del release

Prueba hecha en un WordPress 7.1 limpio (PHP 8.5, MariaDB 11.4, contenedores efímeros en `127.0.0.1:8123`).

### Core

- Asset: `convoca-core-2.3.50.zip` del **release**, 5.685 KB, **493 ficheros**, **381 bajo `vendor/`**,
  `vendor/autoload.php` presente.
- `wp plugin install … --activate` → **activado**, versión 2.3.50.
- Home: **HTTP 200**.
- `debug.log`: solo avisos de conectividad a wordpress.org del contenedor (sin salida HTTPS). **Cero avisos
  del plugin.**
- **Veredicto: D1 cumplido para core.**

### Enroll encima

- Asset `convoca-enroll-2.7.69.zip`, instalado con core activo → ambos activos, home 200.
- `debug.log`: **`_load_textdomain_just_in_time` — «Translation loading for the convoca-enroll domain was
  triggered too early»**. Es un aviso real de WordPress 6.7+.
- **Veredicto: D1 no cumplido todavía** → issue **enroll#52**.

### Receta (repetible)

`quality/baseline/2026-10-26/` guarda el método; los pasos que costaron:

1. MariaDB primero y esperar `mariadb-admin ping`; el `wp-config` se genera al arrancar WordPress y si la
   base no está lista se queda con `DB_HOST=mysql`.
2. Contenedor `wordpress:cli` con `--user root` para poder escribir `wp-config.php`.
3. `wp config set DB_HOST d1-db` explícito antes de `core install`.
4. `podman volume rm -f` antes de empezar: un volumen reutilizado conserva el `wp-config` viejo.

## Assets de los 7 vendibles

| Plugin | Release | ZIP |
|---|---|---|
| core | v2.3.50 | 5.685 KB |
| members | v2.9.19 | 1.534 KB |
| enroll | v2.7.69 | 2.057 KB |
| gateway | v2.13.23 | 1.061 KB |
| shifts | v2.5.35 | 1.237 KB |
| publisher | v1.22.13 | 5.322 KB |
| assistant | v0.2.23 | 1.152 KB |

Los siete tienen asset descargable y coinciden con la versión de la cabecera.

## Circuito de venta (D10) — estado por paso

| Paso | Estado | Evidencia / bloqueo |
|---|---|---|
| 1. Web muestra producto, precio y plan | **existe** | getconvoca.app y `/plugins/` en vivo |
| 2. Compra en WooCommerce + cobro | **no probado** | requiere producto y Redsys TEST; no se ha ejecutado un pedido |
| 3. Emisión de licencia | **no probado E2E** | `license-generator` tiene `max_sites: 1`; falta pedido → licencia |
| 4. Descarga del asset del release | **probado** | 7/7 con ZIP; install real en core |
| 5. Instalación + PRO desbloqueado | **no probado** | hace falta licencia emitida en el paso 3 |
| 6. Actualización | **no probado** | `Upgrade_Manager` existe; falta servidor de actualizaciones con licencia |
| 7. Revocación | **no probado** | requiere licencia y endpoint de validación |

**Conclusión honesta:** el circuito está probado hasta el paso 4. Los pasos 2, 3, 5, 6 y 7 exigen un pedido
real en TEST y una licencia emitida; sin eso, «vendible» sigue siendo una hipótesis en esa mitad.

## Promesas

La matriz completa está en `MATRIZ-PROMESAS.md`. Defectos abiertos:

- **publisher#14** — la web vende 8 redes y el plugin implementa 7.
- **enroll#52** — aviso de textdomain al activar.
- **assistant#23** — CORS con origen comodín: decidir y acotar.
- **shifts#32** — test con fecha fija que caducaba y tumbaba el CI. **Corregido y cerrado** con la puerta de
  calidad: la fecha se deriva del reloj (`tests/Unit/RestApiProximoTest.php`).

## Decisiones que necesitan a JC

1. «Sin comisiones externas»: Redsys cobra al comercio. Matizar la frase o retirarla.
2. Push por ntfy.sh: es un servicio externo; o se documenta como tal y se hace opt-in, o se retira del mensaje
   «tus datos no salen de tu servidor».
3. «Gamificación» en Shifts: la web la vende en PRO y en shifts no está.
