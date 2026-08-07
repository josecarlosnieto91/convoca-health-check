# Rendimiento — Performance Probe

El core (`convoca-core`) incluye `Performance_Probe`, un profiler ligero
que mide durante una petición:

| Métrica | Detalle |
|---------|---------|
| **Queries SQL** | contador total + las lentas (>20ms) con caller |
| **Hooks lentos** | do_action/apply_filters con duración >50ms |
| **Memoria** | pico de la petición |
| **Tiempo total** | duración de la petición |

## Uso

```bash
# Como admin, añade el parámetro a cualquier página:
https://tudominio.org/?convoca_probe=1
```

El reporte se renderiza en el HTML (comentario `<!-- Convoca Performance Probe -->`).

## Cómo leerlo

- **Queries altas + ninguna lenta**: problema de cantidad, no de queries individuales
  → revisar cache de objetos (Redis) y consultas repetidas en loop.
- **Queries lentas**: buscar optimización (índices, transients).
- **Hooks lentos**: buscar callbacks pesados (emails, HTTP remoto, generación PDF).

## Hallazgos de la demo (2026-08-07)

| Petición | Queries | Memoria | Tiempo |
|----------|---------|---------|--------|
| Home (1ª) | 188 | 148 MB | 1.00s |
| Home (2ª) | 167 | 158 MB | 0.83s |

**Diagnóstico**: la demo no usa object cache (Redis activo pero sin conectar).
Instalar php-redis + drop-in object-cache reduciría a ~20 queries.
Ver skill convoca-server.
