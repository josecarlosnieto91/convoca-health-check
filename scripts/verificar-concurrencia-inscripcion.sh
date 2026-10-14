#!/usr/bin/env bash
# =====================================================================================
# Verifica que la inscripción NO se puede duplicar con peticiones simultáneas.
#
# Por qué existe: tres peticiones idénticas a la vez (mismo correo, mismo DNI) creaban tres inscripciones,
# daban tres códigos de reserva distintos y consumían tres plazas del cupo de una sola persona. La causa
# era que la re-verificación de duplicados usaba get_posts() dentro de la transacción y WordPress cachea
# el resultado de las consultas: la segunda lectura devolvía su propia respuesta anterior, sin consultar
# la base. Arreglado con SQL directo con bloqueo (convoca-enroll 2.7.43).
#
# Es EL MISMO arnés que midió el defecto (3 filas, plazas de 5 a 2), para poder repetirlo.
#
# Y mide algo más que el resultado: **que las peticiones se hayan solapado de verdad**. Sin eso, un
# «1 fila» no distingue «el arreglo funciona» de «las peticiones fueron en fila india». Se comprueba
# cronometrando una petición sola contra el lote en paralelo: si se solapan, el lote no cuesta el triple.
#
# No puede correr en el CI: necesita un WordPress real con el plugin activo. Se lanza a mano contra el
# banco de pruebas y borra todo lo que crea.
#
# Uso:
#   SITIO_HOST=andromeda SITIO_RUTA=/var/www/demo.getconvoca.app/public SITIO_USUARIO=http \
#   SITIO_NOMBRE=demo.getconvoca.app bash scripts/verificar-concurrencia-inscripcion.sh [nº de peticiones]
# =====================================================================================
set -u

SITIO_HOST="${SITIO_HOST:-andromeda}"
SITIO_RUTA="${SITIO_RUTA:-/var/www/demo.getconvoca.app/public}"
SITIO_USUARIO="${SITIO_USUARIO:-http}"
SITIO_NOMBRE="${SITIO_NOMBRE:-demo.getconvoca.app}"
PETICIONES="${1:-3}"

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
AYUDANTE="$RAIZ/scripts/verificar-concurrencia-inscripcion.php"
SELLO="$(date +%s)"
CORREO_UNO="qa-concurrencia-uno-$SELLO@example.org"
CORREO_LOTE="qa-concurrencia-lote-$SELLO@example.org"
# Dos DNIs válidos y distintos: el de la referencia y el del lote. Si compartieran DNI, el motor
# rechazaría el lote por «ya existe una inscripción con este DNI» —y haría bien—, y el guion mediría
# otra cosa creyendo que mide la carrera. (Fue el cuarto tropiezo propio con este arnés.)
DNI_UNO="12345678Z"
DNI_LOTE="87654321X"
FALLOS=0

wp_remoto() { ssh -o ConnectTimeout=20 "$SITIO_HOST" "sudo -u $SITIO_USUARIO wp --path=$SITIO_RUTA $*" 2>&1; }
medida() { if [ "$2" = "0" ]; then printf "  [ok]    %-52s %s\n" "$1" "$3"; else printf "  [FALLO] %-52s %s\n" "$1" "$3"; FALLOS=$((FALLOS + 1)); fi; }

ACTIVIDAD=""
limpiar() {
	[ -n "$ACTIVIDAD" ] && wp_remoto "eval-file /tmp/verificar-concurrencia.php borrar $ACTIVIDAD" >/dev/null 2>&1
	ssh -o ConnectTimeout=10 "$SITIO_HOST" "rm -f /tmp/verificar-concurrencia.php /tmp/resp-*.json" 2>/dev/null
}
trap limpiar EXIT

scp -q "$AYUDANTE" "$SITIO_HOST:/tmp/verificar-concurrencia.php" || { echo "No se pudo subir el ayudante."; exit 1; }

# El limitador de intentos del plugin cortaría la prueba: se limpia antes.
wp_remoto "db query \"delete from wp_options where option_name like 'convoca_rl_%'\"" >/dev/null

ACTIVIDAD=$(wp_remoto "eval-file /tmp/verificar-concurrencia.php crear" | grep -oE 'actividad=[0-9]+' | grep -oE '[0-9]+')
[ -z "$ACTIVIDAD" ] && { echo "No se pudo crear la actividad de pruebas."; exit 1; }
NONCE=$(wp_remoto "eval 'echo wp_create_nonce(\"convoca_enroll_inscribir_nonce\");'" | tr -d '[:space:]')
echo "Actividad $ACTIVIDAD · cupo de 5 · $PETICIONES peticiones simultáneas"

# El lote sale en paralelo DENTRO de una sola sesión SSH: tres sesiones distintas arrancarían desfasadas,
# podrían no solaparse y el guion daría un verde falso. Devuelve «exitos=N» y «ms=T».
lote() { # correo, nº de peticiones, dni
	ssh -o ConnectTimeout=30 "$SITIO_HOST" "bash -s" <<FIN
INICIO=\$(date +%s%N)
for i in \$(seq 1 $2); do
  curl -sk -H 'Host: $SITIO_NOMBRE' -X POST 'https://127.0.0.1/wp-admin/admin-ajax.php' \\
    --data-urlencode 'action=convoca_inscribir' --data-urlencode 'nonce=$NONCE' \\
    --data-urlencode 'actividad_id=$ACTIVIDAD' --data-urlencode 'nombre=Prueba Concurrencia' \\
    --data-urlencode 'email=$1' --data-urlencode 'dni=$3' --data-urlencode 'telefono=600000000' \\
    --data-urlencode 'consentimiento=1' --data-urlencode 'tipo_inscripcion=socio_dia' > /tmp/resp-\$i.json &
done
wait
echo "exitos=\$(grep -l '"success":true' /tmp/resp-*.json 2>/dev/null | wc -l)"
echo "ms=\$(( (\$(date +%s%N) - INICIO) / 1000000 ))"
head -c 140 /tmp/resp-1.json 2>/dev/null; echo
rm -f /tmp/resp-*.json
FIN
}

echo
echo "== Referencia: UNA petición sola =="
UNO=$(lote "$CORREO_UNO" 1 "$DNI_UNO")
echo "$UNO" | sed 's/^/  /'
MS_UNO=$(echo "$UNO" | grep -oE 'ms=[0-9]+' | grep -oE '[0-9]+')

echo
echo "== El lote: $PETICIONES peticiones IDÉNTICAS a la vez =="
LOTE=$(lote "$CORREO_LOTE" "$PETICIONES" "$DNI_LOTE")
echo "$LOTE" | sed 's/^/  /'
EXITOS=$(echo "$LOTE" | grep -oE 'exitos=[0-9]+' | grep -oE '[0-9]+')
MS_LOTE=$(echo "$LOTE" | grep -oE 'ms=[0-9]+' | grep -oE '[0-9]+')

echo
echo "== Lo que ha quedado en la base =="
CUENTA=$(wp_remoto "eval-file /tmp/verificar-concurrencia.php contar $ACTIVIDAD $CORREO_LOTE")
echo "  $CUENTA"
FILAS=$(echo "$CUENTA" | grep -oE 'filas=[0-9]+' | grep -oE '[0-9]+')
LIBRES=$(echo "$CUENTA" | grep -oE 'plazas_libres=[0-9]+' | grep -oE '[0-9]+')

medida "las peticiones se solaparon" "$([ "${MS_LOTE:-99999}" -lt "$(( ${MS_UNO:-1} * 2 ))" ] && echo 0 || echo 1)" "lote ${MS_LOTE}ms frente a ${MS_UNO}ms de una sola (en fila india serían ~$(( ${MS_UNO:-1} * PETICIONES ))ms)"
medida "una sola inscripción" "$([ "$FILAS" = "1" ] && echo 0 || echo 1)" "filas=$FILAS (con el defecto: $PETICIONES)"
medida "una sola petición aceptada" "$([ "$EXITOS" = "1" ] && echo 0 || echo 1)" "exitos=$EXITOS de $PETICIONES"
medida "dos plazas consumidas (1 + 1)" "$([ "$LIBRES" = "3" ] && echo 0 || echo 1)" "plazas libres=$LIBRES (con el defecto: 1)"

echo
[ "$FALLOS" = "0" ] && echo "Todo pasa: la inscripción no se duplica ni come plazas de más." || echo "$FALLOS medida(s) en rojo."
exit "$([ "$FALLOS" = "0" ] && echo 0 || echo 1)"
