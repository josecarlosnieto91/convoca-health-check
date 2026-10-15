# Las pruebas de integración: estado y decisión (issue #3)

## Lo que hay, medido el 15/10/2026

| repositorio | ficheros en `tests/Integration/` | en la suite unitaria | en el CI |
|---|---|---|---|
| convoca-core | 3 | no | no |
| convoca-enroll | 8 | no | no |
| convoca-members | 1 | no | no |
| convoca-gateway | 1 | no | no |
| convoca-shifts | 1 | no | no |
| **total** | **14** | — | — |

Comprobación al día de hoy, la misma que pedía el issue:

```bash
cd <repo> && vendor/bin/phpunit --testsuite unit --list-tests | grep -c Integration   # -> 0
```

El paso de `convoca-enroll/ci.yml` que **parecía** ejecutarlos —dentro de un `if` que en el runner siempre se
cumplía, así que imprimía un aviso y salía con 0— ya no está: esa apariencia de cobertura se retiró.

## Decisión: **no verificado, con motivo y plan**

Se declaran **NV** (no verificados) en vez de dejar que parezcan cobertura. Motivos:

1. **No se ejecutan en ninguna automatización y mantener el espejismo es peor que no tenerlos.** Un fichero de
   pruebas que no corre no protege: da tranquilidad falsa, que es exactamente el defecto que persigue esta
   auditoría.
2. **Montarlos de verdad no es «añadir un paso»**: hace falta un WordPress de pruebas (con su base de datos) en el
   trabajo de cada repositorio. Eso ya existe en el trabajo `health-check-local` de este repositorio, así que es
   *hacerlo cinco veces más*, no inventarlo.
3. **Lo que cubrirían lo cubren hoy otras dos cosas**: las suites unitarias (con sus negativos probados) y la
   verificación en la demo con datos reales, que es donde de verdad se ve si algo funciona.

## Plan, si algún día se quieren de verdad

1. Levantar el WordPress de pruebas una vez, en un trabajo **reutilizable** (el de `health-check-local` sirve de
   plantilla) y publicarlo como artefacto.
2. Que cada repositorio lo consuma y ejecute `tests/Integration` en su propio `ci.yml`.
3. Y no darlo por bueno hasta que la comprobación de arriba devuelva algo distinto de 0 **en el log del CI**, no
   en local.

Mientras eso no pase, este documento es la respuesta a «¿esto está probado?»: **no, y se sabe**.
