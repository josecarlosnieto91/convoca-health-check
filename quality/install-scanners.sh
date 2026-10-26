#!/usr/bin/env bash
# install-scanners.sh — instala los escáneres locales de Convoca con versión fijada.
#
# Destino: ~/.local/opt/quality/<herramienta>/<versión>/ y enlaces en ~/.local/bin.
# Verificación: sha256 contra ~/.local/opt/quality/MANIFEST (trust-on-first-use) o contra
# el fichero oficial de checksums cuando el proyecto lo publica (gitleaks).
#
# Semgrep NO se instala en el host: se ejecuta con podman (imagen fijada por digest en
# quality/semgrep.sh) para no depender del Python del sistema.
set -euo pipefail

DEST="$HOME/.local/opt/quality"
BIN="$HOME/.local/bin"
MANIFEST="$DEST/MANIFEST"
mkdir -p "$DEST" "$BIN"
touch "$MANIFEST"

log() { printf '%s\n' "$*"; }

# devuelve el sha guardado para herramienta+versión (vacío si no hay)
saved_sha() { awk -v t="$1" -v v="$2" '$1==t && $2==v {print $3}' "$MANIFEST"; }

record_sha() { # tool version sha
  grep -v "^$1 $2 " "$MANIFEST" > "$MANIFEST.tmp" || true
  printf '%s %s %s\n' "$1" "$2" "$3" >> "$MANIFEST.tmp"
  mv "$MANIFEST.tmp" "$MANIFEST"
}

install_phpmd() {
  local v="3.0.0"
  local dir="$DEST/phpmd/$v"
  local f="$DEST/phpmd/$v/phpmd.phar"
  if [ -x "$f" ]; then log "phpmd $v ya instalado"; else
    mkdir -p "$dir"
    curl -fsSL -o "$f" "https://github.com/phpmd/phpmd/releases/download/$v/phpmd.phar"
    chmod +x "$f"
  fi
  local sha; sha="$(sha256sum "$f" | cut -d' ' -f1)"
  local prev; prev="$(saved_sha phpmd "$v")"
  if [ -n "$prev" ] && [ "$prev" != "$sha" ]; then log "FALLO: sha de phpmd $v no coincide ($prev != $sha)"; exit 1; fi
  [ -z "$prev" ] && record_sha phpmd "$v" "$sha"
  ln -sf "$f" "$BIN/phpmd"
  log "phpmd $v OK ($sha)"
}

install_phpmetrics() {
  local v="2.11.0"
  local dir="$DEST/phpmetrics/$v"
  local f="$DEST/phpmetrics/$v/phpmetrics"
  if [ -x "$f" ]; then log "phpmetrics $v ya instalado"; else
    mkdir -p "$dir"
    curl -fsSL -o "$f" "https://github.com/phpmetrics/PhpMetrics/releases/download/v$v/phpmetrics-linux-x86_64"
    chmod +x "$f"
  fi
  local sha; sha="$(sha256sum "$f" | cut -d' ' -f1)"
  local prev; prev="$(saved_sha phpmetrics "$v")"
  if [ -n "$prev" ] && [ "$prev" != "$sha" ]; then log "FALLO: sha de phpmetrics $v no coincide"; exit 1; fi
  [ -z "$prev" ] && record_sha phpmetrics "$v" "$sha"
  ln -sf "$f" "$BIN/phpmetrics"
  log "phpmetrics $v OK ($sha)"
}

install_gitleaks() {
  local v="8.30.1"
  local dir="$DEST/gitleaks/$v"
  local f="$dir/gitleaks"
  if [ ! -x "$f" ]; then
    mkdir -p "$dir"
    local tmp; tmp="$(mktemp -d)"
    curl -fsSL -o "$tmp/checksums.txt" "https://github.com/gitleaks/gitleaks/releases/download/v$v/gitleaks_${v}_checksums.txt"
    curl -fsSL -o "$tmp/gitleaks.tar.gz" "https://github.com/gitleaks/gitleaks/releases/download/v$v/gitleaks_${v}_linux_x64.tar.gz"
    local want got
    want="$(grep "gitleaks_${v}_linux_x64.tar.gz" "$tmp/checksums.txt" | cut -d' ' -f1)"
    got="$(sha256sum "$tmp/gitleaks.tar.gz" | cut -d' ' -f1)"
    if [ -z "$want" ] || [ "$want" != "$got" ]; then log "FALLO: checksum de gitleaks no coincide"; exit 1; fi
    tar -xzf "$tmp/gitleaks.tar.gz" -C "$dir" gitleaks
    rm -rf "$tmp"
    chmod +x "$f"
    record_sha gitleaks "$v" "$got"
  else
    log "gitleaks $v ya instalado"
  fi
  ln -sf "$f" "$BIN/gitleaks"
  log "gitleaks $v OK"
}

install_phpmd
install_phpmetrics
install_gitleaks

log "--- versiones ---"
"$BIN/phpmd" --version
"$BIN/phpmetrics" --version 2>/dev/null | head -1
"$BIN/gitleaks" version
