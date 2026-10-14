#!/usr/bin/env bash
# =====================================================================================
# Verifica que el check de la clave del gateway de la batería CACE una clave indescifrable.
#
# Por qué existe: el check comprobaba que la clave empezara por «enc:» —la FORMA— y no que se
# pudiera descifrar —la CAPACIDAD—, así que daba PASS sobre una clave que deja los pagos
# bloqueados. Un verde que no mide lo que dice medir es peor que no tenerlo.
#
# No puede correr en el CI: necesita una instalación real con el gateway y su clave. Se lanza a
# mano contra el banco de pruebas, y deja los ajustes EXACTAMENTE como estaban (se comprueba por
# comparación, no se supone).
#
# Uso:
#   SITIO_HOST=andromeda SITIO_RUTA=/var/www/demo.getconvoca.app/public SITIO_USUARIO=http \
#     bash scripts/verificar-clave-gateway.sh
#
# Sale 0 si el check se comporta; 1 si no. Deja el negativo probado en cada ejecución.
# =====================================================================================
set -u

SITIO_HOST="${SITIO_HOST:-andromeda}"
SITIO_RUTA="${SITIO_RUTA:-/var/www/demo.getconvoca.app/public}"
SITIO_USUARIO="${SITIO_USUARIO:-http}"

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BATERIA="$RAIZ/health-check.php"
SONDA="$RAIZ/scripts/verificar-clave-gateway-sonda.php"
FALLOS=0

wp_remoto() { ssh -o ConnectTimeout=20 "$SITIO_HOST" "sudo -u $SITIO_USUARIO wp --path=$SITIO_RUTA $*" 2>&1; }
medida() { if [ "$2" = "0" ]; then printf "  [ok]    %-52s %s\n" "$1" "$3"; else printf "  [FALLO] %-52s %s\n" "$1" "$3"; FALLOS=$((FALLOS + 1)); fi; }
clave() { wp_remoto "eval-file /tmp/$1" | grep -i "Clave secreta utilizable"; }

limpiar() {
	ssh -o ConnectTimeout=10 "$SITIO_HOST" "rm -f /tmp/convoca-sonda-gateway.php /tmp/convoca-gateway-ajustes.json /tmp/convoca-bateria.php" 2>/dev/null
}
trap limpiar EXIT

# Los dos ficheros tienen que llegar. Si no, los pasos se convierten en nada y el guion cantaría un
# PASS falso: es el fallo con el que tropieza cualquiera que toque esto (me pasó dos veces).
scp -q "$BATERIA" "$SITIO_HOST:/tmp/convoca-bateria.php" || { echo "No se pudo subir la batería."; exit 1; }
scp -q "$SONDA" "$SITIO_HOST:/tmp/convoca-sonda-gateway.php" || { echo "No se pudo subir la sonda."; exit 1; }
echo "  sonda y batería subidas"

echo "== La batería con la clave buena =="
BUENA=$(clave convoca-bateria.php)
echo "  $BUENA"
echo "$BUENA" | grep -q '\[PASS\]' && medida "la clave buena da PASS" 0 "$(echo "$BUENA" | grep -oE 'longitud efectiva [0-9]+')" || medida "la clave buena da PASS" 1 "$BUENA"

echo "== EL NEGATIVO: clave con forma «enc:» que no se puede descifrar =="
wp_remoto "eval-file /tmp/convoca-sonda-gateway.php guardar" | sed 's/^/  /'
# El negativo solo vale si la clave CAMBIA de verdad: se compara con el largo que tenía antes, sin
# umbrales inventados. Si no cambia, esto lo dice en vez de dar un verde. (Fallarlo fue mi tercer tropiezo.)
largos() { wp_remoto "eval 'echo strlen((string) ( get_option(\"convoca_gateway_settings\")[\"secret_key\"] ?? \"\" ));'" | tr -d '[:space:]'; }
LARGO_ANTES=$(largos)
wp_remoto "eval-file /tmp/convoca-sonda-gateway.php romper" | sed 's/^/  /'
LARGO_ROTO=$(largos)
echo "  // la clave pasa de $LARGO_ANTES a $LARGO_ROTO caracteres"
if [ -n "$LARGO_ROTO" ] && [ "$LARGO_ROTO" != "$LARGO_ANTES" ]; then
	ROTA=$(clave convoca-bateria.php)
	echo "  $ROTA"
	echo "$ROTA" | grep -q '\[FAIL\]' && medida "la clave rota da FAIL" 0 "$(echo "$ROTA" | grep -oE 'longitud efectiva [0-9]+')" || medida "la clave rota da FAIL" 1 "$ROTA"
else
	medida "la clave rota da FAIL" 1 "la clave no cambió ($LARGO_ROTO caracteres): el negativo no se ha probado"
fi

echo "== Restauración y repetición =="
wp_remoto "eval-file /tmp/convoca-sonda-gateway.php restaurar" | sed 's/^/  /'
IDENTICOS=$(wp_remoto "eval-file /tmp/convoca-sonda-gateway.php comprobar")
echo "  $IDENTICOS"
echo "$IDENTICOS" | grep -q 'SÍ' && medida "los ajustes quedan idénticos a la copia" 0 "SÍ" || medida "los ajustes quedan idénticos a la copia" 1 "$IDENTICOS"
DEVUELTA=$(clave convoca-bateria.php)
echo "  $DEVUELTA"
echo "$DEVUELTA" | grep -q '\[PASS\]' && medida "vuelve a PASS tras restaurar" 0 "$(echo "$DEVUELTA" | grep -oE 'longitud efectiva [0-9]+')" || medida "vuelve a PASS tras restaurar" 1 "$DEVUELTA"

echo
[ "$FALLOS" = "0" ] && echo "Todo pasa: el check caza la clave rota y no molesta a la buena." || echo "$FALLOS medida(s) en rojo."
exit "$([ "$FALLOS" = "0" ] && echo 0 || echo 1)"
