#!/usr/bin/env python3
"""
API v3.0 — Extractor de inventario público (v2, preciso).
Distingue:
  - HOOKS: strings usados en do_action/apply_filters (disparador o callback)
  - REST: register_rest_route
  - SHORTCODES: add_shortcode
  - META KEYS: update/get_post_meta con prefijo _convoca/_conv
  - OPCIONES: update/get_option con prefijo convoca_
  - CPTs / Taxonomías
"""
import os, re, json

WS = os.environ.get("WS", "/home/josecnr91/.openclaw/workspace")
REPOS = ["convoca-core", "convoca-members", "convoca-enroll", "convoca-gateway",
         "convoca-shifts", "convoca-publisher", "convoca-assistant", "convoca-theme"]

def scan(repo):
    d = os.path.join(WS, repo)
    files = []
    for root, dirs, fnames in os.walk(d):
        if any(x in root for x in ("vendor", ".git", "node_modules", "tests")):
            continue
        for f in fnames:
            if f.endswith(".php"):
                files.append(os.path.join(root, f))
    return files

inventory = {}

# ── Constantes con nombre de hook ──
# Los guiones programados (cron) se escriben casi siempre como constante de clase
# (`const CRON_HOOK = 'convoca_publisher_retry_event'`), y el extractor solo miraba
# literales: cuatro hooks públicos —justo los de cron, los que rompen en silencio al
# renombrarlos, porque el evento queda programado sin nadie escuchando— no estaban en
# el inventario congelado y el guardián de la API no los protegía.
# Se resuelve con el mismo alcance que PHP: primero la constante de la clase del uso
# (`self::CRON_HOOK` → la del fichero), luego `Clase::CONSTANTE`, y solo si el nombre
# no es ambiguo, la global. Dos clases pueden llamar `CRON_HOOK` a cosas distintas: es
# justo el caso que había, y resolverlo «por nombre a secas» perdía los dos.
CONSTS_CLASS = {}   # "Clase::NOMBRE" -> valor
CONSTS_FILE = {}    # (repo, rel) -> {NOMBRE: valor}
CONSTS_GLOBAL = {}  # NOMBRE -> valor (solo si no es ambiguo)
_ambig = set()
for _repo in REPOS:
    for _fp in scan(_repo):
        try:
            _c = open(_fp, encoding="utf-8", errors="replace").read()
        except Exception:
            continue
        _rel = os.path.relpath(_fp, os.path.join(WS, _repo))
        _clases = re.findall(r"\bclass\s+([A-Za-z_][A-Za-z0-9_]*)", _c)
        _pares = [(m.group(1), m.group(2)) for m in re.finditer(r"\bconst\s+([A-Z_][A-Z0-9_]*)\s*=\s*['\"]([^'\"]+)['\"]", _c)]
        for m in re.finditer(r"\bdefine\(\s*['\"]([A-Z_][A-Z0-9_]*)['\"]\s*,\s*['\"]([^'\"]+)['\"]", _c):
            _pares.append((m.group(1), m.group(2)))
        _locales = {}
        for _n, _v in _pares:
            if len(_clases) == 1:
                CONSTS_CLASS[f"{_clases[0]}::{_n}"] = _v
            if _n in _locales and _locales[_n] != _v:
                _locales.pop(_n, None)      # dos valores en el mismo fichero: no se resuelve
                _ambig.add(_n)
            else:
                _locales[_n] = _v
            if _n in CONSTS_GLOBAL and CONSTS_GLOBAL[_n] != _v:
                _ambig.add(_n)
            CONSTS_GLOBAL[_n] = _v
        CONSTS_FILE[(_repo, _rel)] = _locales
for _n in _ambig:
    CONSTS_GLOBAL.pop(_n, None)
CONSTS_GLOBAL = {k: v for k, v in CONSTS_GLOBAL.items() if re.match(r"^(convoca_|conv_)", v)}
CONSTS_CLASS = {k: v for k, v in CONSTS_CLASS.items() if re.match(r"^(convoca_|conv_)", v)}


def resolver_constante(repo, rel, prefijo, nombre):
    """Valor de una constante con nombre de hook, con el alcance de PHP."""
    if prefijo and prefijo not in ("self", "static"):
        v = CONSTS_CLASS.get(f"{prefijo}::{nombre}")
        if v:
            return v
    v = CONSTS_FILE.get((repo, rel), {}).get(nombre)
    if v and re.match(r"^(convoca_|conv_)", v):
        return v
    return CONSTS_GLOBAL.get(nombre)


for repo in REPOS:
    hooks = {}     # name -> {type, files}
    rest = []
    shortcodes = set()
    meta_keys = set()
    options = set()
    cpts = set()
    taxos = set()

    for fp in scan(repo):
        # Los artefactos de compilación (build-dir/ del theme) son una COPIA del
        # código: si se leen, el inventario declara hooks y shortcodes que el
        # código fuente ya no tiene (y el baseline se compara contra fantasmas).
        if "/build-dir/" in fp or "/build/" in fp:
            continue
        try:
            c = open(fp, encoding="utf-8", errors="replace").read()
        except Exception:
            continue
        rel = os.path.relpath(fp, os.path.join(WS, repo))

        # ── Hooks: do_action / apply_filters / do_action_deprecated ──
        # Captura el string convoca_* inmediatamente tras la llamada (con o sin espacios).
        # El `(?!\s*\.)` es importante: `apply_filters( 'convoca_document_theme_' . $suffix )`
        # NO es un hook, es un PREFIJO. Sin esa guarda el inventario congelaba
        # `convoca_document_theme_` como si fuera un hook real —y cualquier familia de
        # hooks dinámicos entra igual—, así que el guardián protegía un fantasma y dejaba
        # fuera a los de verdad.
        for m in re.finditer(r"(?:do_action|apply_filters|do_action_deprecated|apply_filters_deprecated)\(\s*['\"](convoca_[a-z0-9_]+|conv_[a-z0-9_]+)['\"](?!\s*\.)", c):
            name = m.group(1)
            if name not in hooks:
                hooks[name] = {"files": []}
            hooks[name]["fires"] = True
            if rel not in hooks[name]["files"]:
                hooks[name]["files"].append(rel)

        # Hooks cuyo nombre es una CONSTANTE (no un literal): `do_action( self::CRON_HOOK )`,
        # `wp_schedule_event( …, Scheduler::CRON_HOOK )`, `add_action( self::SPACING_HOOK, … )`.
        for m in re.finditer(
            r"(?:do_action|apply_filters|do_action_deprecated|apply_filters_deprecated|add_action|add_filter|wp_schedule_event|wp_schedule_single_event|wp_clear_scheduled_hook|wp_unschedule_event)\(\s*(?:([A-Za-z_][A-Za-z0-9_]*)::)?([A-Z_][A-Z0-9_]*)",
            c,
        ):
            name = resolver_constante(repo, rel, m.group(1), m.group(2))
            if not name:
                continue
            if name not in hooks:
                hooks[name] = {"files": []}
            hooks[name]["fires"] = True
            if m.group(0).startswith(("add_action", "add_filter")):
                hooks[name]["listens"] = True
            if rel not in hooks[name]["files"]:
                hooks[name]["files"].append(rel)


        # Nombres RETIRADOS: segundo argumento de Utils::do_action($nuevo, $viejo, …).
        # Siguen existiendo y disparándose (do_action_deprecated) mientras haya
        # alguien escuchándolos, así que son parte de la API pública (deprecada) y
        # tienen que seguir inventariados. Sin esto, dejar de usarlos dentro de casa
        # los hacía desaparecer del inventario y la API quedaba infradocumentada.
        for m in re.finditer(r"Utils::\s*(?:do_action|apply_filters)\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"](convoca_[a-z0-9_]+|conv_[a-z0-9_]+)['\"]", c):
            name = m.group(2)
            if name not in hooks:
                hooks[name] = {"files": []}
            hooks[name]["deprecated"] = True
            hooks[name]["replaced_by"] = m.group(1)
            if rel not in hooks[name]["files"]:
                hooks[name]["files"].append(rel)

        # Callbacks registrados (add_action/add_filter) — hooks que el plugin ESCUCHA
        for m in re.finditer(r"add_(?:action|filter)\(\s*['\"](convoca_[a-z0-9_]+|conv_[a-z0-9_]+)['\"]", c):
            name = m.group(1)
            if name not in hooks:
                hooks[name] = {"files": [], "listens": True}
            else:
                hooks[name]["listens"] = True
            if rel not in hooks[name]["files"]:
                hooks[name]["files"].append(rel)

        # ── REST ──
        # Constante NAMESPACE en la clase (self::NAMESPACE o self::API_NAMESPACE)
        ns_const = None
        for m in re.finditer(r"const\s+(?:NAMESPACE|API_NAMESPACE)\s*=\s*['\"]([^'\"]+)['\"]", c):
            ns_const = m.group(1)
        for m in re.finditer(r"register_rest_route\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]+)['\"]", c):
            ns, route = m.group(1), m.group(2)
            seg = c[m.end():m.end()+400]
            methods = re.findall(r"methods?\s*=>\s*['\"]([A-Z_, ]+)['\"]", seg)
            mth = methods[0] if methods else "GET"
            rest.append({"namespace": ns, "route": route, "methods": mth, "file": rel})
        for m in re.finditer(r"register_rest_route\(\s*self::(?:NAMESPACE|API_NAMESPACE)\s*,\s*['\"]([^'\"]+)['\"]", c):
            route = m.group(1)
            seg = c[m.end():m.end()+400]
            methods = re.findall(r"methods?\s*=>\s*['\"]([A-Z_, ]+)['\"]", seg)
            mth = methods[0] if methods else "GET"
            rest.append({"namespace": ns_const or "?", "route": route, "methods": mth, "file": rel})

        # ── Shortcodes ──
        for m in re.finditer(r"add_shortcode\(\s*['\"]([a-z0-9_]+)['\"]", c):
            shortcodes.add(m.group(1))

        # ── Meta keys (solo prefijos _convoca_ / _conv) ──
        for m in re.finditer(r"(?:update_post_meta|get_post_meta|add_post_meta|delete_post_meta)\(\s*[^,]+,\s*['\"](_convoca_[a-z0-9_]+|_conv_[a-z0-9_]+)['\"]", c):
            meta_keys.add(m.group(1))

        # ── Opciones (solo convoca_ / conv_ prefijo) ──
        for m in re.finditer(r"(?:update_option|get_option|delete_option)\(\s*['\"](convoca_[a-z0-9_]+|conv_[a-z0-9_]+)['\"]", c):
            options.add(m.group(1))

        # ── CPTs ──
        for m in re.finditer(r"register_post_type\(\s*['\"]([a-z0-9_]+)['\"]", c):
            cpts.add(m.group(1))

        # ── Taxonomías ──
        for m in re.finditer(r"register_taxonomy\(\s*['\"]([a-z0-9_]+)['\"]", c):
            taxos.add(m.group(1))

    inventory[repo] = {
        "hooks": {
            k: {
                "files": v["files"][:3],
                "listens": v.get("listens", False),
                "fires": v.get("fires", False),
                "deprecated": v.get("deprecated", False),
                "replaced_by": v.get("replaced_by", ""),
            }
            for k, v in sorted(hooks.items())
        },
        "rest": sorted(rest, key=lambda x: (x["namespace"], x["route"])),
        "shortcodes": sorted(shortcodes),
        "meta_keys": sorted(meta_keys),
        "options": sorted(options),
        "cpts": sorted(cpts),
        "taxonomies": sorted(taxos),
    }

print("=== INVENTARIO API v3.0 (v2) ===\n")
tot_h = tot_r = tot_s = tot_m = tot_o = 0
for repo, d in inventory.items():
    print(f"{repo}: hooks={len(d['hooks'])} rest={len(d['rest'])} sc={len(d['shortcodes'])} meta={len(d['meta_keys'])} opts={len(d['options'])} cpts={len(d['cpts'])} taxos={len(d['taxonomies'])}")
    tot_h += len(d['hooks']); tot_r += len(d['rest']); tot_s += len(d['shortcodes']); tot_m += len(d['meta_keys']); tot_o += len(d['options'])
print(f"\nTOTAL: {tot_h} hooks | {tot_r} REST | {tot_s} shortcodes | {tot_m} meta | {tot_o} opciones")

json.dump(inventory, open("/tmp/api-inventory.json", "w"), ensure_ascii=False, indent=2)
print("\nGuardado en /tmp/api-inventory.json")
