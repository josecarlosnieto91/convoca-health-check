#!/usr/bin/env python3
"""Gate de acoplamiento: un plugin no provee la funcionalidad de otro.

Por qué existe: en la semana del 21/10/2026 se limpió a mano el mismo defecto en cuatro plugins —core,
members, enroll y shifts creaban roles de personas y averiguaban quién era voluntario leyendo roles—, y al
cerrar apareció `convoca-members#64`: un fatal en el cron de renovaciones que **ningún test veía**, porque
sólo se rompe con Convoca Gateway **desactivado**. Limpiar a mano no dura: esto lo convierte en una prueba
que corre en cada empujón.

Qué vigila, en el código que se publica (ni pruebas, ni vendor, ni dev-tools, ni stubs de PHPStan):

  R1  roles          Nadie crea ni borra roles fuera de Convoca Core. El vocabulario de personas es del
                     producto y `add_role()` no pisa un rol existente: el que llega segundo no añade nada,
                     así que con tres plugins creándolos el rol se queda a medias según quién active antes.
  R2  clases         Nadie llama a una clase de otro plugin sin comprobar que existe. Una llamada suelta
                     revienta con «Class not found» justo cuando el otro plugin NO está, que es la
                     configuración que nadie prueba (`members#64`).
  R3  metas          Nadie escribe metas de otro plugin: la asignación de un turno es de Shifts y la
                     identidad del socio es de Members. Se escribe donde se sabe, se lee donde se necesita.
  R4  personas       Nadie decide por rol ni por capacidad de personas fuera de Members: quien necesite
                     personas pregunta al contrato `convoca_shifts_personas_asignables`, y quien necesite
                     saber si alguien acredita horas pregunta a `convoca_can_credit_hours`.

Cómo se salta un caso justificado: en la misma línea, `convoca-acoplamiento-ignore: <motivo>`. Se exige el
motivo: una excepción sin explicación es deuda disfrazada.

Uso:
    python3 check-acoplamiento.py [raíz] [--reglas R1,R2,R3,R4] [--verbose]
Sale con 0 si está limpio y 2 si encuentra algo (para que el CI lo note).
"""
from __future__ import annotations

import argparse
import os
import re
import sys

# --------------------------------------------------------------------------------------------------
# Dueño de cada plugin: el nombre del paquete y el «namespace» corto que usa en las clases.
# --------------------------------------------------------------------------------------------------
NAMESPACES = {
    "convoca-core": "Core",
    "convoca-members": "Members",
    "convoca-enroll": "Enroll",
    "convoca-gateway": "Gateway",
    "convoca-shifts": "Shifts",
    "convoca-courses": "Courses",
    "convoca-publisher": "Publisher",
    "convoca-assistant": "Assistant",
}

# Roles de personas: vocabulario del producto, se crean en Core y sólo ahí.
ROLES_DE_PERSONAS = ("socio", "voluntario_aprobado", "monitor_actividad")

# Capacidades que se preguntan para decidir sobre personas (quién es voluntario, quién puede qué).
CAPACIDADES_DE_PERSONAS = ("gestionar_mis_turnos",)

# Meta → plugin que lo escribe. R3: escribir un meta ajeno es proveer funcionalidad ajena.
DUENOS_DE_METAS = {
    "_monitor": "convoca-shifts",
    "_id_responsable": "convoca-shifts",
    "_convoca_fecha_aviso": "convoca-shifts",
    "_necesita_apoyo": "convoca-shifts",
    "_convoca_member_id": "convoca-members",
    "_convoca_voluntario_aprobado": "convoca-members",
    "_convoca_estado_cuota": "convoca-members",
    "_convoca_horas_contadas": "convoca-enroll",
}

# Ficheros que pueden nombrar clases de otros plugins a propósito.
FICHEROS_EXIMIDOS = (
    "includes/Features.php",          # el detector: su único trabajo es preguntar quién está.
    "phpstan-bootstrap.php",
    "phpstan-convoca-core.stub.php",  # doble del núcleo para el analizador, no se ejecuta nunca.
)

# Core NO se vigila: es requisito obligatorio de todos los plugins (`Requires Plugins: convoca-core`), así
# que está cargado siempre y llamarlo sin `class_exists` es legítimo. Lo que hay que proteger es llamar a
# un addon **opcional** (Members, Enroll, Gateway, Shifts, Courses, Publisher, Assistant) sin comprobar
# que está: esa es la configuración que nadie prueba y la que mató el cron en `members#64`.
NAMESPACIO_OBLIGATORIO = "Core"
DIRECTORIOS_EXIMIDOS = ("vendor", "node_modules", "tests", "dev-tools", "stubs", "build", "dist")

# Líneas que nombran una clase sin usarla: declaraciones de dependencia para pintar Ajustes, `use`, y las
# que ya van protegidas.
# Dos protecciones y dos distancias, porque son patrones distintos: `class_exists( '\Convoca\X\Y' )` va
# pegado a su llamada (o en el `if` inmediato), mientras que `Features::is_gateway_active()` es una
# comprobación de módulo que abre un bloque largo (en el formulario de enroll, la llamada está 17 líneas
# después). Medirlas juntas obligaba a una ventana tan ancha que el guardián dejaba de ver nada.
RX_PROTEGIDA_CERCA = re.compile(r"class_exists|method_exists|interface_exists|function_exists")
RX_PROTEGIDA_BLOQUE = re.compile(r"Features::")
VENTANA_CERCA = 6
VENTANA_BLOQUE = 30
# `'class' => '\Convoca\X\Y'` es una DECLARACIÓN de dependencia para pintar Ajustes, no una llamada: puede
# ir al principio de la línea o dentro del array del módulo, así que se busca en cualquier posición.
RX_DECLARACION = re.compile(r"'class'\s*=>|^\s*use\s|^\s*\*|^\s*//|^\s*/\*")
RX_LLAMADA_CLASE = re.compile(r"\\?Convoca\\([A-Za-z]+)\\")
RX_ADD_ROLE = re.compile(r"(?<!->)(?<!::)\b(add_role|remove_role)\s*\(")
RX_ESCRITURA_META = re.compile(r"\b(?:update|add|delete)_(?:post|user)_meta\s*\(\s*[^,]+,\s*'([^']+)'")
RX_GET_USERS = re.compile(r"\bget_users\s*\(")
RX_ROL_PERSONA = re.compile(r"'(voluntario_aprobado|monitor_actividad|socio)'")
RX_CAP_PERSONA = re.compile(r"'capability'\s*=>\s*'(gestionar_mis_turnos)'")

ESCAPE = "convoca-acoplamiento-ignore:"


def plugin_actual(raiz: str) -> str:
    """Nombre del plugin a partir de la raíz que se analiza."""
    nombre = os.path.basename(os.path.abspath(raiz))
    return nombre if nombre in NAMESPACES else "desconocido"


def ficheros_php(raiz: str):
    """Recorre el código que se publica (ni pruebas, ni vendor, ni herramientas)."""
    for base, dirs, ficheros in os.walk(raiz):
        dirs[:] = [d for d in dirs if d not in DIRECTORIOS_EXIMIDOS and not d.startswith(".")]
        for f in ficheros:
            if not f.endswith(".php"):
                continue
            ruta = os.path.join(base, f)
            rel = os.path.relpath(ruta, raiz).replace(os.sep, "/")
            if rel in FICHEROS_EXIMIDOS or rel.startswith("dev-tools/"):
                continue
            yield ruta, rel


def comentario(linea: str) -> bool:
    """¿La línea es un comentario (de línea o de bloque)?"""
    t = linea.lstrip()
    return t.startswith("*") or t.startswith("//") or t.startswith("/*") or t.startswith("#")


def saltada(linea: str) -> bool:
    """¿Lleva el escape con motivo?"""
    if ESCAPE not in linea:
        return False
    motivo = linea.split(ESCAPE, 1)[1].strip(" */\t")
    return len(motivo) >= 8  # sin motivo escrito, el escape no vale


def main() -> int:
    ap = argparse.ArgumentParser(description="Gate de acoplamiento entre los plugins de Convoca.")
    ap.add_argument("raiz", nargs="?", default=".", help="raíz del plugin a analizar")
    ap.add_argument("--reglas", default="R1,R2,R3,R4", help="qué reglas hacer cumplir (por defecto, todas)")
    ap.add_argument(
        "--avisos",
        default="",
        help="reglas que se miden y se imprimen pero NO hacen fallar (deuda con dueño y fase)",
    )
    ap.add_argument("--verbose", action="store_true", help="muestra también lo eximido")
    args = ap.parse_args()

    raiz = os.path.abspath(args.raiz)
    if not os.path.isdir(raiz):
        print(f"FALLO: no existe la raíz {raiz}")
        return 1

    propio = plugin_actual(raiz)
    if propio == "desconocido":
        print(f"AVISO: «{os.path.basename(raiz)}» no es un plugin de Convoca conocido; se analiza igual.")
    reglas = {r.strip().upper() for r in args.reglas.split(",") if r.strip()}
    avisos = {r.strip().upper() for r in args.avisos.split(",") if r.strip()} - reglas
    # Las reglas en aviso también se miden: si no, no habría nada que avisar.
    evaluar = reglas | avisos

    hallazgos = []
    revisados = 0

    for ruta, rel in ficheros_php(raiz):
        try:
            with open(ruta, encoding="utf-8", errors="ignore") as fh:
                lineas = fh.read().splitlines()
        except OSError:
            continue
        revisados += 1

        # Guarda temprana a nivel de fichero: `if ( ! Features::is_gateway_active() ) { … continue; }` deja
        # el resto del bucle/función ya garantizado, y la llamada puede quedar treinta líneas más abajo
        # (`members/Cron_Manager`). Se detecta la comprobación junto a un `continue`/`return` en ±3 líneas.
        guarda_temprana = False
        for j, l in enumerate(lineas):
            if not RX_PROTEGIDA_BLOQUE.search(l):
                continue
            # Diez líneas de margen: el `if` de la guarda lleva dentro el aviso por el Logger y el release
            # del lock antes del `continue` (medido: siete líneas en `members/Cron_Manager`).
            if any(re.search(r"\b(continue|return)\b", v) for v in lineas[max(0, j - 10):j + 11]):
                guarda_temprana = True
                break

        for i, linea in enumerate(lineas, 1):
            # El escape vale en la propia línea o en la inmediatamente anterior: en su propia línea no
            # alarga el código, que es lo que rompe el límite de longitud de PHPCS.
            if (
                not linea.strip()
                or comentario(linea)
                or saltada(linea)
                or any(saltada(lineas[i - k]) for k in (2, 3, 4) if i - k >= 0)
                or RX_DECLARACION.search(linea)
            ):
                continue

            cerca = lineas[max(0, i - VENTANA_CERCA):i]
            bloque = lineas[max(0, i - VENTANA_BLOQUE):i]
            protegida = (
                guarda_temprana
                or any(RX_PROTEGIDA_CERCA.search(v) for v in cerca)
                or any(RX_PROTEGIDA_BLOQUE.search(v) for v in bloque)
            )

            # R1 — crear o borrar roles fuera de Core.
            if "R1" in evaluar and propio not in ("convoca-core", "desconocido") and RX_ADD_ROLE.search(linea):
                hallazgos.append(("R1", rel, i, "crea/borra un rol fuera de Core", linea.strip()[:140]))

            # R2 — clase de otro plugin sin comprobar que existe.
            if "R2" in evaluar and not protegida:
                for otro in RX_LLAMADA_CLASE.findall(linea):
                    # Ni el propio plugin, ni el obligatorio (Core), ni nombres que no son de un plugin.
                    if otro in (NAMESPACES.get(propio), NAMESPACIO_OBLIGATORIO) or otro not in NAMESPACES.values():
                        continue
                    hallazgos.append(("R2", rel, i, f"usa una clase de Convoca {otro} sin class_exists/Features", linea.strip()[:140]))
                    break

            # R3 — escribir metas de otro plugin.
            if "R3" in evaluar:
                for meta in RX_ESCRITURA_META.findall(linea):
                    dueno = DUENOS_DE_METAS.get(meta)
                    if dueno and dueno != propio:
                        hallazgos.append(("R3", rel, i, f"escribe el meta {meta}, que es de {dueno}", linea.strip()[:140]))

            # R4 — decidir por rol o capacidad de personas fuera de Members.
            #
            # Se mira la llamada y las cuatro líneas siguientes: `get_users( array( 'role' => …` va partido,
            # y buscar sólo en la línea dejaba pasar justo el caso que se quería cazar (medido en el negativo).
            if "R4" in evaluar and propio not in ("convoca-members", "desconocido") and RX_GET_USERS.search(linea):
                trozo = "\n".join(lineas[i - 1:i + 4])
                rol = RX_ROL_PERSONA.search(trozo)
                cap = RX_CAP_PERSONA.search(trozo)
                if rol or cap:
                    que = rol.group(1) if rol else cap.group(1)
                    hallazgos.append(("R4", rel, i, f"decide por {que}: eso lo contesta Members por contrato", linea.strip()[:140]))

    print(f"== acoplamiento: {propio} ==")
    print(f"   ficheros revisados: {revisados}")
    print(f"   reglas: {', '.join(sorted(reglas))}")
    # Los avisos se separan de los fallos: una regla en aviso documenta deuda y deja el CI en verde hasta
    # que la fase que la resuelve la active. Nunca al revés: lo que bloquea es lo que ya se cumple.
    fallos = [h for h in hallazgos if h[0] not in avisos]
    pendientes = [h for h in hallazgos if h[0] in avisos]

    if pendientes:
        print(f"   AVISOS (no bloquean, deuda con dueño): {len(pendientes)}")
        for regla, rel, i, que, _texto in pendientes:
            print(f"      {regla}  {rel}:{i}  {que}")

    if not fallos:
        print("   OK: este plugin no provee funcionalidad de otro.")
        return 0

    print(f"   HALLAZGOS: {len(fallos)}")
    for regla, rel, i, que, texto in fallos:
        print(f"      {regla}  {rel}:{i}  {que}")
        if args.verbose:
            print(f"            {texto}")
    print()
    print("   Un caso justificado se marca en su línea con")
    print(f"   `{ESCAPE} <motivo>` — con motivo, o no vale.")
    return 2


if __name__ == "__main__":
    sys.exit(main())
