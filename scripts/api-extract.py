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

for repo in REPOS:
    hooks = {}     # name -> {type, files}
    rest = []
    shortcodes = set()
    meta_keys = set()
    options = set()
    cpts = set()
    taxos = set()

    for fp in scan(repo):
        try:
            c = open(fp, encoding="utf-8", errors="replace").read()
        except Exception:
            continue
        rel = os.path.relpath(fp, os.path.join(WS, repo))

        # ── Hooks: do_action / apply_filters / do_action_deprecated ──
        # Captura el string convoca_* inmediatamente tras la llamada (con o sin espacios)
        for m in re.finditer(r"(?:do_action|apply_filters|do_action_deprecated|apply_filters_deprecated)\(\s*['\"](convoca_[a-z0-9_]+|conv_[a-z0-9_]+)['\"]", c):
            name = m.group(1)
            if name not in hooks:
                hooks[name] = {"files": []}
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
        "hooks": {k: {"files": v["files"][:3], "listens": v.get("listens", False)} for k, v in sorted(hooks.items())},
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
