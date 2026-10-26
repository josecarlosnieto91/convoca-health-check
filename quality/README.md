# quality/ — métricas de calidad y seguridad del ecosistema Convoca

Artefactos del goal `~/.hermes/goals/convoca-terminado-y-seguridad.md`.

## Qué hay

| Fichero | Para qué |
|---|---|
| `measure-quality.sh` | Mide un repo o la lista por defecto y escribe JSON + `RESUMEN.md` en `--out`. |
| `phpcs-metrics.xml` | Vara única de complejidad: ciclomática 10/15 y anidamiento 4/6. |
| `install-scanners.sh` | Instala PHPMD, PHPmetrics y gitleaks con versión fijada y sha; verifica en cada pasada. |
| `run-sonar.sh` | Analiza repos con el Sonar local y guarda medidas, gate y vulnerabilidades. |
| `semgrep.sh` + `semgrep/convoca.yml` | 8 reglas propias (SQL, XSS, LFI, SSRF, unserialize, json, roles, `@`). |
| `gitleaks.sh` + `gitleaks.toml` | Secretos en árbol e historial, con allowlist justificada. |
| `metrics.sh` + `phpmd.xml` | PHPMD (complejidad, NPath, longitud) y PHPmetrics (MI, LCOM). |
| `ci-gate.sh` | Puerta de CI con **ratchet**: falla solo si un número sube respecto a `.quality/baseline.json`. |
| `baseline/YYYY-MM-DD/` | Línea base fechada: un JSON por repo, `summary.json`, `RESUMEN.md` e informes. |

La puerta de CI se publica como workflow reutilizable en `.github/workflows/quality.yml`; cada repo la llama:

```yaml
  calidad:
    uses: josecarlosnieto91/convoca-health-check/.github/workflows/quality.yml@main
```

y aporta su `.quality/baseline.json` (`phpstan_errors`, `phpmd_total`, `semgrep_errors`,
`composer_advisories`, `secretos`). Los cinco números solo pueden bajar.

## Uso

```bash
./quality/measure-quality.sh --out quality/baseline/$(date +%F) convoca-core convoca-members
./quality/measure-quality.sh --out /tmp/probe            # los 11 por defecto
```

Qué mide por repo: LOC y ficheros de PHP (sin `vendor`, `tests`, `node_modules`, `build`, `assets-wporg`,
`e2e`), funciones con ciclomática >15 y entre 10 y 15, anidamiento >6 y entre 4 y 6, PHPStan al nivel del
**CI** y al **nivel 8**, suelo de cobertura, nº de ficheros de test, `composer audit` de producción y
versión/commit/ficheros sucios.

## Reglas

1. **La config de PHPStan es la del CI**, leída de `.github/workflows/ci.yml`. Si no se puede leer, se usa
   `phpstan.neon`, `phpstan.neon.dist` o `dev-tools/phpstan.neon.dist`, en ese orden.
2. **No modifica repos**: solo escribe en `--out`; PHPStan usa su caché temporal. El JSON guarda
   `dirty_files` para demostrarlo.
3. **Negativo conocido**: `convoca-core` debe dar **193** errores a nivel 8 y `convoca-members` **282**. Si
   salen 0, la config está mal y la medición no vale.
4. **La cobertura de la tabla es el suelo**, no una medición. Para cobertura real: `cobertura-local.sh`.
5. `mu-plugins` no tiene config de PHPStan: se anota el hueco, no se inventa una.

## Histórico

- `baseline/2026-10-26/` — primera línea base (F0). 114.293 LOC, 1.332 errores a nivel 8, 96 funciones
  >15 y 228 >10. Informe: `INFORME-F0.md`.
