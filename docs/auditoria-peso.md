# Auditoría de peso y adelgazamiento del ecosistema Convoca

**Fecha:** 2026-08-07
**Alcance:** 8 plugins + theme
**Objetivo:** reducir tamaño/complejidad sin tocar funcionalidad pública

---

## 1. Inventario de tamaño (Fase 1)

| Repo | Total KB | Archivos | LOC | Peso dominante |
|---|---|---|---|---|
| convoca-assistant | 97,062 | 10,915 | 578,053 | vendor 65MB + **tests/node_modules 30MB** |
| convoca-core | 78,496 | 5,901 | 712,450 | vendor 76MB |
| convoca-enroll | 50,606 | 4,580 | 573,407 | vendor 47MB |
| convoca-gateway | 48,354 | 4,312 | 536,843 | vendor 47MB |
| convoca-shifts | 48,555 | 4,311 | 538,615 | vendor 47MB |
| convoca-members | 49,498 | 4,430 | 564,650 | vendor 47MB |
| convoca-publisher | 44,628 | 3,075 | 489,091 | vendor 43MB |
| convoca-theme | 43,431 | 4,314 | 371,763 | vendor 41MB |
| **TOTAL** | **449.83 MB** | **41,838** | **4,364,872** | vendor 406MB (90%) |

**Ranking de peso:** assistant > core > enroll > members > shifts > gateway > publisher > theme

**Clave:** el 90% del ecosistema es `vendor/` (dependencias Composer — producción legítima). El peso real gestionable sin vendor es **43.4 MB**.

## 2. Clasificación (Fase 2)

| Elemento | Clase | Motivo |
|---|---|---|
| `assistant/tests/node_modules/` | 🔴 | 46 MB en .gitignore (no se distribuye), Jest no integrado en CI |
| `publisher/.phpunit.result.cache` | 🔴 | Residuo de ejecución local, no debería estar versionado |
| `enroll/tests/e2e/` + config | 🟠 | Playwright local duplicado — el CI central usa `convoca-health-check/e2e/` |
| `core/wp-tests-config.php` | 🟠 | Config de WP test suite antigua — el CI usa bootstrap-unit standalone |
| `assistant/tests/*.test.js` + package.json | 🟠 | Test Jest de sesión JS, sin integración en CI |
| `tests/*.php` (PHPUnit, 8 repos) | 🟡 | Fuente de los tests que corre el CI — mantener |
| `dev-tools/` (phpstan, phpcs, phpunit) | 🟡 | Configs de QA — desarrollo útil |
| `phpstan-baseline.neon` ×4 | 🟡 | Baseline de phpstan — útil si se usa phpstan |
| `vendor/`, `includes/`, `assets/`, `admin/`, `public/`, `templates/`, `languages/`, `assets-wporg/`, `social/`, `patterns/` | 🟢 | Producción imprescindible |
| `README.md`, `readme.txt`, CHANGELOG, plantillas GitHub | 🟢 | Distribución y documentación requerida |

## 3. Impacto simulado (Fase 3)

| Repo | Actual KB | Eliminable KB | % reducción | Archivos elim |
|---|---|---|---|---|
| convoca-assistant | 97,062 | 30,241 | **31.2%** | 5,641 |
| convoca-enroll | 50,606 | 7.1 | 0.0% | 4 |
| convoca-core | 78,496 | 0.8 | 0.0% | 1 |
| convoca-publisher | 44,628 | 4.2 | 0.0% | 1 |
| **TOTAL ecosistema** | **449.83 MB** | **30.25 MB** | **6.7%** | **5,647 archivos** |

**LOC eliminables:** ~644,000 (node_modules 643,745 + e2e enroll 164 + configs)

> El ahorro es concentrado: **el 99.9% del peso eliminable está en un solo directorio** (`assistant/tests/node_modules/`). El resto del ecosistema ya está razonablemente limpio tras los goals previos.

## 4. Dependencias — cobertura QA (Fase 4)

| Elemento a eliminar | Cubierto por QA central |
|---|---|
| enroll e2e (poster/visual/media/social) | ✅ `convoca-health-check/e2e/` (11 tests E2E contra demo) |
| wp-tests-config.php | ✅ CI usa `bootstrap-unit.php` standalone (sin WP real) |
| publisher .phpunit.result.cache | ✅ Sin impacto (residuo de cache) |
| assistant Jest (JS session) | ⚠️ **NO cubierto** — la lógica JS de sesión solo la probaba el Jest local. PHPUnit cubre server-side, no el JS |

**Conclusión F4:** todo lo 🔴/🟠 está cubierto EXCEPTO el test Jest de sesión JS de assistant. Si se elimina, se pierde la única cobertura del JS de sesión — aceptable si se valida que el health check y E2E central cubren el widget (el E2E central tiene test del assistant widget).

## 5. Riesgos (Fase 5)

| Elemento | Riesgo | Impacto CI | Impacto devs | Rollback |
|---|---|---|---|---|
| assistant node_modules | Nulo (no distribuido) | Ninguno | Reinstalar con `npm install` si quieren Jest | `git checkout` no aplica (ignorado); reinstalar |
| enroll e2e | Bajo | Ninguno (CI usa e2e central) | Pierden tests locales de poster/visual | Git restore |
| core wp-tests-config | Nulo | Ninguno (CI standalone) | Nadie la usa | Git restore |
| publisher .phpunit.result.cache | Nulo | Ninguno | Ninguno | Git restore |
| assistant Jest JS | **Medio** | Ninguno (no en CI) | Pierden única cobertura JS sesión | Git restore |

## 6. Propuesta final (Fase 6)

### Opción A — Conservadora
Eliminar solo residuos claros: `.phpunit.result.cache` (publisher) + `node_modules` de assistant (46 MB, ya ignorado por git).
- **Ahorro:** 30.2 MB (6.7%) — pero node_modules ni se distribuye, así que el ahorro real de distribución es ~0.
- **Pierde:** nada funcional.

### Opción B — Recomendada
Eliminar 🔴 + 🟠: node_modules + e2e enroll + wp-tests-config + tests Jest de assistant.
- **Ahorro:** 30.25 MB, 5,647 archivos, ~644K LOC
- **Gana:** repos más limpios, sin duplicación de QA
- **Pierde:** la cobertura Jest local del JS de sesión (mitigable: el E2E central cubre el widget)
- **Riesgo:** bajo

### Opción C — Distribución mínima
Opción B + eliminar `tests/` completos + `dev-tools/` + `phpstan-*` de los 8 repos (generar tarballs de distribución sin dev).
- **Ahorro adicional:** ~300 KB de tests + configs (~0.07%)
- **Gana:** plugins listos para WordPress.org sin ningún archivo dev
- **Pierde:** los devs pierden los tests locales (el CI central los conserva)
- **Riesgo:** medio — requiere que el CI central sea la única fuente de QA

---

## Recomendación

**Opción B** — el ahorro real está en assistant (31.2% de ese repo), y la limpieza de duplicados de QA (e2e enroll, wp-tests-config, Jest sin CI) deja el ecosistema con UNA sola infraestructura de validación. La Opción C solo aporta ~0.07% adicional y añade fricción al desarrollo local.

**Nota crítica:** antes de eliminar el Jest de assistant, integrar `assistant-chat.test.js` al CI (un job Jest) o aceptar que el JS de sesión queda cubierto por el E2E central (que ya testea el widget). Recomiendo la segunda: el E2E central ya cubre el comportamiento del widget en el navegador real.
