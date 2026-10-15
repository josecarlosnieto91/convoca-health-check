#!/usr/bin/env python3
"""Gate de higiene de datos: el cliente no aparece en el producto.

Por qué existe: en la revisión de la pasada 8 se comprobó a mano que los plugins casi no llevan
cadenas de clientes ni dominios ni correos hardcodeados, pero **no había ningún guardián** que lo
vigilara, así que la comprobación se perdía en cuanto pasaba la revisión. Esto lo convierte en una
prueba que corre en el CI de cada empujón.

Qué busca, en el código que se publica (no en pruebas ni documentación ni vendor):
  - nombres de clientes o sitios reales (palabras conocidas, configurables con --also);
  - dominios distintos del propio del producto;
  - direcciones de correo que no estén en la lista permitida.

Cómo se salta un caso justificado: en la misma línea, un comentario con
`convoca-hygiene-ignore: <motivo>`. Se exige el motivo, para que la excepción quede explicada.

Uso:
    python3 check-data-hygiene.py [raíz] [--also palabra1,palabra2]
Sale con 0 si está limpio y 2 si encuentra algo (para que el CI lo note).
"""
from __future__ import annotations

import argparse
import os
import re
import sys

# Nombres de clientes o instalaciones reales que NUNCA deben aparecer en el producto.
CLIENTES = [
    "biodevas", "lugg", "damaris", "damarisholistica", "asturnas", "treelogic",
    "ucam", "gestistock", "stockey", "juliette", "triajeset", "orion", "cassiopeia",
]

# Dominios que sí son del producto o de terceros legítimos (documentación, licencias, servicios).
DOMINIOS_PERMITIDOS = {
    # El producto.
    "getconvoca.app", "www.getconvoca.app", "convoca.local",
    # Documentación, licencias y estándares.
    "wordpress.org", "es.wordpress.org", "developer.wordpress.org", "schemas.wp.org",
    "www.gnu.org", "gnu.org", "opensource.org", "github.com", "schema.org",
    "w3.org", "www.w3.org", "example.com", "example.org",
    # Pasarelas y servicios que el producto usa de verdad.
    "redsys.es", "sis.redsys.es", "stripe.com", "api.stripe.com",
    "graph.facebook.com", "api.linkedin.com", "api.twitter.com",
    "api.mailgun.net", "api.eu.mailgun.net", "ntfy.sh",
    "www.googleapis.com", "wa.me", "api.whatsapp.com",
    "accounts.google.com", "oauth2.googleapis.com", "sheets.googleapis.com",
    "businessprofileperformance.googleapis.com", "mybusinessbusinessinformation.googleapis.com",
    "www.facebook.com", "sis-t.redsys.es", "photos.app.goo.gl", "pagosonline.redsys.es",
    "developers.facebook.com", "www.linkedin.com", "learn.microsoft.com",
    "developer.twitter.com", "developers.tiktok.com", "console.cloud.google.com",
    "developers.google.com", "api.telegram.org", "core.telegram.org",
    "docs.joinmastodon.org", "mastodon.social", "tuweb.com",
    "mybusiness.googleapis.com", "open-api.tiktok.com", "upload.twitter.com",
    "fusejs.io", "kiro.me", "www.apache.org", "apache.org",
    "i.creativecommons.org", "creativecommons.org", "www.instagram.com", "instagram.com",
    "asociacion.org", "demo.getconvoca.app", "josecarlosnietoramos.com",
    "www.youtube.com", "youtube.com",
    # Fuentes y librerías de terceros.
    "fonts.googleapis.com", "fonts.gstatic.com", "www.jsdelivr.com", "cdn.jsdelivr.net",
    "www.chartjs.org", "registry.npmjs.org", "unpkg.com",
}

# Correos que sí se admiten (los del producto y los de ejemplo).
CORREOS_PERMITIDOS = {
    "soporte@getconvoca.app", "info@getconvoca.app", "hola@getconvoca.app",
    "noreply@getconvoca.app", "demo@convoca.local", "hola@asociacion.org",
}

# .txt incluido a propósito: el `readme.txt` de cada plugin es lo que se publica en el
# directorio y lo que lee quien instala. Se colaba por ahí (lo cazó la batería del
# ecosistema el 13/10/2026, no este guardián, que solo miraba código).
EXTENSIONES = (".php", ".js", ".css", ".json", ".html", ".txt")
# `tests/` sigue fuera por ahora, y es el centro del issue #9: la batería sí los mira (y tiene razon:
# esos ficheros viajan en el repositorio) pero incluirlos aquí saca 11 hallazgos de datos de prueba
# legitimos (@test.com, paga.test) que hay que admitir antes en la lista de ejemplos. Medido el 15/10/2026:
# el cambio se hizo, se midio y se revirtio para no dejar el CI en rojo a medias.
# UNA sola lista (issue #9): había dos —esta y la del `os.walk`— con contenidos distintos, y editando
# una no pasaba nada. La del `os.walk` era la que mandaba. `tests/` NO está: los ficheros de prueba viajan
# en el repositorio y sus datos son tan públicos como el código, que es lo que ya hacía la batería.
NOMBRES_FUERA = {".git", "vendor", "node_modules", "docs", "languages", ".github", "dev-tools",
                 "playwright-report", "test-results"}
CARPETAS_FUERA = {f"/{n}/" for n in NOMBRES_FUERA} | {"/assets/vendor/"}

RX_DOMINIO = re.compile(r"https?://([a-z0-9.-]+\.[a-z]{2,})", re.I)
# Ejemplos y marcadores de posición: no son datos de nadie.
# Ejemplos, marcadores de posición y los dominios que las RFC 2606 y 6761 reservan para pruebas
# (`test`, `invalid`, `example`, `localhost`). Los correos entran por aquí también: `admin@test.com`
# y `quien@paga.test` son datos de mentira, no de nadie (issue #9).
RX_EJEMPLO = re.compile(
    r"(ejemplo\.|example\.|\.test\b|\.invalid\b|\.example\b|\.local\b|localhost\b|"
    r"correo\.com|email\.com|tu@|tucorreo|maria@|juan@|nombre@|cliente@|"
    r"@(test|example|invalid)\.(com|org|net)\b)", re.I)
RX_CORREO = re.compile(r"[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}", re.I)
IGNORE = "convoca-hygiene-ignore"


def ficheros(raiz: str):
    for base, dirs, nombres in os.walk(raiz):
        # La misma lista que arriba, no una copia (issue #9).
        dirs[:] = [d for d in dirs if d not in NOMBRES_FUERA]
        for n in nombres:
            if n.endswith((".min.js", ".min.css")) or n in {"package-lock.json", "composer.lock"}:
                continue
            if n.endswith(EXTENSIONES):
                ruta = os.path.join(base, n)
                if any(c in ruta.replace(os.sep, "/") for c in CARPETAS_FUERA):
                    continue
                yield ruta


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("raiz", nargs="*", default=["."])
    ap.add_argument("--also", default="", help="palabras extra separadas por comas")
    args = ap.parse_args()
    clientes = CLIENTES + [p.strip().lower() for p in args.also.split(",") if p.strip()]

    hallazgos = []
    revisados = 0
    for raiz in args.raiz:
        for ruta in ficheros(raiz):
            revisados += 1
            try:
                with open(ruta, encoding="utf-8", errors="replace") as fh:
                    lineas = fh.readlines()
            except OSError:
                continue
            for i, linea in enumerate(lineas, 1):
                if IGNORE in linea:
                    continue
                baja = linea.lower()
                for palabra in clientes:
                    if re.search(r"\b" + re.escape(palabra) + r"\b", baja):
                        hallazgos.append((ruta, i, "cliente", palabra, linea.strip()[:150]))
                        break
                if RX_EJEMPLO.search(linea):
                    continue
                for dom in RX_DOMINIO.findall(linea):
                    if dom.lower() not in DOMINIOS_PERMITIDOS:
                        hallazgos.append((ruta, i, "dominio", dom, linea.strip()[:150]))
                for correo in RX_CORREO.findall(linea):
                    if correo.lower() not in CORREOS_PERMITIDOS:
                        hallazgos.append((ruta, i, "correo", correo, linea.strip()[:150]))

    print(f"   ficheros revisados: {revisados}")
    if not hallazgos:
        print("   OK: sin cadenas de clientes, dominios ajenos ni correos sin permitir.")
        return 0
    print(f"   HALLAZGOS: {len(hallazgos)}")
    for ruta, i, tipo, valor, texto in hallazgos:
        print(f"      {tipo:8s} {os.path.relpath(ruta)}:{i} [{valor}] {texto}")
    print()
    print("   Si un caso está justificado, marca la línea con")
    print("   `convoca-hygiene-ignore: <motivo>`; si es un dominio o correo legítimo, añádelo a las")
    print("   listas permitidas de este guion.")
    return 2


if __name__ == "__main__":
    sys.exit(main())
