# Ficheros servidos que no debían estarlo — 27/10/2026

## Qué se encontró

En el docroot de `getconvoca.app` había **6 ficheros de copia** accesibles por HTTP:

| Fichero | HTTP antes | Qué contenía |
|---|---:|---|
| `wp-config.php.bak-1785657018` | **200** | `DB_PASSWORD`, `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY` y las cuatro sales |
| `api/license.php.bak` | 200 | Código de la API de licencias |
| `api/license.php.bak-renew-20260806-145347` | 200 | Ídem |
| `api/license.php.bak-deploy-20261027-101017` | 200 | Ídem |
| `api/update.php.bak-deploy-20261027-101017` | 200 | Ídem |
| `api/license-create.php.bak-lictoken` | 200 | Código del alta de licencias, **sin el token** |

Retirados con copia en `/root/convoca-ficheros-expuestos-20261027-101910.tar.gz` (600) y **404 verificado**.

## Impacto real, medido

- **Sales y claves de autenticación: ya eran distintas** de las del `.bak` (se rotaron después). Ninguna
  cookie forjada con ese fichero valía. Aun así se rotaron otra vez por decisión de JC (`shuffle-salts`),
  lo que invalida todas las sesiones abiertas.
- **Contraseña de la BD: era la vigente.** Rotada el 27/10; la anterior ya no autentica. Guardada en `pass`.
- **MariaDB escucha solo en `127.0.0.1`**: la contraseña no era explotable desde fuera del servidor.
- **Logs de acceso**: las únicas peticiones con 200 a esos ficheros son las de esta sesión (10:18–10:19).
  Los escáneres piden `wp-config.php.bak` sin sufijo y se llevan 301/404/499. El nombre con sufijo
  (`bak-1785657018`) no lo prueba ningún bot.

## Causa raíz y cierre

nginx servía cualquier fichero del docroot con extensión desconocida. Se creó
`snippets/deny-dangerous.conf` y se incluyó en los **10 vhosts** activos: `.bak`, `.bak-*`, `.old`, `.sql`,
`.env`, `.log`, `.dist`, `.swp`, `.git`. Probado con ficheros reales: **404** en getconvoca.app y demo.

## Lo que queda

- `enroll#52` y el CORS de `assistant#23`, ajenos a esto.
- Los `.bak` del directorio `api/` ya no existen; el resto de temporales se revisó en Andromeda (demo) y
  Orion (Lugg, biodevas): **0 ficheros sensibles** en sus docroots.
