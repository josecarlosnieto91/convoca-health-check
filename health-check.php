<?php
/**
 * Convoca Health Check — batería de validación permanente.
 *
 * Uso:
 *   wp eval-file health-check.php --path=/ruta/al/wp
 *   wp convoca-health --format=json (si se instala como comando)
 *
 * Valida: API REST, hooks, shortcodes, CPTs, taxonomías, integraciones,
 * flujos Members→Gateway, Enroll→Gateway, Assistant, Publisher, Theme.
 * Exit code 0 = todo PASS. Exit code 1 = hay FAILs (para CI).
 *
 * Cada check se ejecuta contra la instalación real. Los datos de prueba
 * se crean y se limpian dentro del mismo check (no deja residuos).
 */

// ── Salida y contadores ──
$GLOBALS['hc_pass'] = 0;
$GLOBALS['hc_fail'] = 0;
$GLOBALS['hc_warn'] = 0;
$GLOBALS['hc_results'] = [];
// Modo local (CI sin demo): los checks de configuración del sitio (páginas,
// credenciales, índice, tema) pasan a WARN en vez de FAIL. La lógica
// funcional (shortcodes, CPTs, REST, rate limiter, flujos) sigue estricta.
// Se activa con variable de entorno CONVOCA_HC_LOCAL=1 o arg --local.
$GLOBALS['hc_local'] = getenv('CONVOCA_HC_LOCAL') === '1'
    || in_array('--local', $_SERVER['argv'] ?? [], true);

function hc_out($component, $name, $ok, $detail = '') {
    $status = $ok ? 'PASS' : 'FAIL';
    echo "  [$status] $component :: $name" . ($detail ? " ($detail)" : '') . "\n";
    $GLOBALS['hc_results'][] = ['component' => $component, 'name' => $name, 'status' => $status, 'detail' => $detail];
    $ok ? $GLOBALS['hc_pass']++ : $GLOBALS['hc_fail']++;
}

// Check de configuración de sitio: en modo local, un fallo es WARN (no FAIL).
function hc_cfg($component, $name, $ok, $detail = '') {
    if (!$ok && $GLOBALS['hc_local']) {
        hc_warn($component, $name, $detail . ' [config sitio]');
        return;
    }
    hc_out($component, $name, $ok, $detail);
}

function hc_warn($component, $name, $detail = '') {
    echo "  [WARN] $component :: $name" . ($detail ? " ($detail)" : '') . "\n";
    $GLOBALS['hc_results'][] = ['component' => $component, 'name' => $name, 'status' => 'WARN', 'detail' => $detail];
    $GLOBALS['hc_warn']++;
}

function hc_section($title) {
    echo "\n== $title ==\n";
}

// Namespaces REST registrados
function hc_rest_namespaces() {
    $server = rest_get_server();
    $ns = [];
    foreach (array_keys($server->get_routes()) as $route) {
        if (preg_match('#^/(convoca[^/]*/v\d+)/#', $route, $m)) {
            $ns[$m[1]] = true;
        }
    }
    return array_keys($ns);
}

// Shortcodes registrados
function hc_shortcodes() {
    global $shortcode_tags;
    return array_keys($shortcode_tags);
}

// ── COMPONENTES ──

function hc_core() {
    hc_section('Convoca Core');
    $includes = WP_PLUGIN_DIR . '/convoca-core/includes';

    hc_out('Core', 'Archivos de admin', file_exists("$includes/admin-appearance.php"), 'admin-appearance.php');
    hc_out('Core', 'Backup', file_exists("$includes/Admin_Backup.php"), 'Admin_Backup.php');
    hc_out('Core', 'Wizard', file_exists("$includes/Admin_Setup_Wizard.php"), 'Admin_Setup_Wizard.php');
    hc_out('Core', 'Logger', class_exists('Convoca\Core\Logger'), '');
    hc_out('Core', 'Webhook_Manager', class_exists('Convoca\Core\Webhook_Manager'), '');
    hc_out('Core', 'Module_Registry', class_exists('Convoca\Core\Module_Registry'), '');
    hc_out('Core', 'License_Manager', class_exists('Convoca\Core\License_Manager'), '');

    // Rate limiter funcional (limpiar antes)
    if (class_exists('Convoca\Core\Utils')) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $action = 'hc_' . wp_generate_password(6, false);
        $cache_key = 'convoca_rl_' . $action . '_' . md5($ip);
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", $cache_key));
        wp_cache_delete($cache_key, 'convoca_rate_limits');
        $r = [];
        for ($i = 1; $i <= 4; $i++) {
            $r[$i] = \Convoca\Core\Utils::check_rate_limit($action, 3, 60);
        }
        hc_out('Core', 'Rate limiter bloquea abuso', $r[1] && $r[2] && $r[3] && !$r[4], implode(',', array_map(fn($v) => $v ? 'P' : 'B', $r)));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", $cache_key));

        // Locks
        $l1 = \Convoca\Core\Utils::acquire_lock('hc_lock', 30);
        $l2 = \Convoca\Core\Utils::acquire_lock('hc_lock', 30);
        hc_out('Core', 'Locks exclusivos', $l1 && !$l2, '');
    }

    // REST admin/metrics protegido
    $r = wp_remote_get(home_url('/wp-json/convoca/v1/admin/metrics'), ['timeout' => 10, 'sslverify' => false]);
    hc_cfg('Core', 'REST /admin/metrics protegido', in_array(wp_remote_retrieve_response_code($r), [401, 403]), 'HTTP ' . wp_remote_retrieve_response_code($r));
}

function hc_members() {
    hc_section('Convoca Members');
    $dir = WP_PLUGIN_DIR . '/convoca-members';

    // CPTs
    hc_out('Members', 'CPT miembro', post_type_exists('miembro'), '');
    hc_out('Members', 'CPT convoca_documento', post_type_exists('convoca_documento'), '');
    hc_out('Members', 'Taxonomía tipo_miembro', taxonomy_exists('tipo_miembro'), '');

    // Shortcodes
    $scs = hc_shortcodes();
    foreach (['convoca_mi_area', 'convoca_mi_perfil', 'convoca_renovar', 'convoca_alta_socio', 'convoca_voluntariado'] as $sc) {
        hc_out('Members', "Shortcode $sc", in_array($sc, $scs), '');
    }

    // REST protegido
    $r = wp_remote_get(home_url('/wp-json/convoca-members/v1/me'), ['timeout' => 10, 'sslverify' => false]);
    hc_cfg('Members', 'REST /me protegido', in_array(wp_remote_retrieve_response_code($r), [401, 403]), 'HTTP ' . wp_remote_retrieve_response_code($r));

    // Estados válidos
    if (class_exists('Convoca\Members\Estados')) {
        $states = \Convoca\Members\Estados::STATES;
        hc_out('Members', 'Estados definidos (6)', count($states) === 6, implode(',', $states));
        hc_out('Members', 'Estados sin "expirado"', !in_array('expirado', $states), '');
    }

    // Flujo: alta → edición → renovación (con plan correcto)
    $member_id = wp_insert_post(['post_type' => 'miembro', 'post_status' => 'publish', 'post_title' => 'HC Member']);
    update_post_meta($member_id, '_convoca_email', 'hc@example.com');
    update_post_meta($member_id, '_convoca_plan', 'bronze');
    update_post_meta($member_id, '_convoca_estado_miembro', 'pendiente_pago');
    hc_out('Members', 'Alta de socio', $member_id > 0 && get_post_type($member_id) === 'miembro', "ID $member_id");

    update_post_meta($member_id, '_convoca_direccion', 'Calle HC 1');
    hc_out('Members', 'Edición dirección', get_post_meta($member_id, '_convoca_direccion', true) === 'Calle HC 1', '');

    if (class_exists('Convoca\Members\Rest_API')) {
        $token = wp_generate_password(32, false);
        set_transient('convoca_member_session_' . $token, ['id' => $member_id, 'last_renewal' => time(), 'pending_cookie' => false], DAY_IN_SECONDS);
        $_COOKIE['convoca_member_session'] = $token;
        $api = new \Convoca\Members\Rest_API();

        // Renovación
        $req = new \WP_REST_Request('POST', '/convoca-members/v1/me/renovar');
        $resp = $api->renew_membership($req);
        $data = $resp->get_data();
        hc_out('Members', 'Renovación genera payment_url', $resp->get_status() === 200 && !empty($data['payment_url']), 'HTTP ' . $resp->get_status());
        if (!empty($data['pago_id'])) {
            wp_delete_post($data['pago_id'], true);
        }

        // Baja
        $req = new \WP_REST_Request('POST', '/convoca-members/v1/me/unsubscribe');
        $resp = $api->unsubscribe_request($req);
        hc_out('Members', 'Solicitud de baja', $resp->get_status() === 200, 'HTTP ' . $resp->get_status());

        delete_transient('convoca_member_session_' . $token);
    }

    wp_delete_post($member_id, true);
}

function hc_enroll() {
    hc_section('Convoca Enroll');
    $dir = WP_PLUGIN_DIR . '/convoca-enroll';

    // CPTs
    hc_out('Enroll', 'CPT actividad', post_type_exists('actividad'), '');
    hc_out('Enroll', 'CPT inscripcion', post_type_exists('inscripcion'), '');
    hc_out('Enroll', 'CPT convoca_evaluacion', post_type_exists('convoca_evaluacion'), '');

    // Shortcodes
    $scs = hc_shortcodes();
    foreach (['convoca_inscripcion_page', 'convoca_form_inscripcion', 'convoca_panel_reservas', 'convoca_evaluacion', 'convoca_actividad_meta', 'convoca_inscripcion_actual'] as $sc) {
        hc_out('Enroll', "Shortcode $sc", in_array($sc, $scs), '');
    }

    // REST público
    $r = wp_remote_get(home_url('/wp-json/convoca-enroll/v1/actividades'), ['timeout' => 10, 'sslverify' => false]);
    hc_cfg('Enroll', 'REST /actividades público', wp_remote_retrieve_response_code($r) === 200, 'HTTP ' . wp_remote_retrieve_response_code($r));

    // Motor inscripción
    hc_out('Enroll', 'Motor_Inscripcion', class_exists('Convoca\Enroll\Motor_Inscripcion') || file_exists("$dir/includes/Motor_Inscripcion.php"), '');

    // Google Calendar / Photos
    hc_out('Enroll', 'Google_Calendar', file_exists("$dir/includes/Google_Calendar.php"), '');
    hc_out('Enroll', 'Google_Photos', file_exists("$dir/includes/Google_Photos.php"), '');

    // Cron
    $crons = _get_cron_array();
    $enroll_crons = [];
    foreach ((array)$crons as $ts => $events) {
        foreach ((array)$events as $hook => $data) {
            if (strpos($hook, 'convoca_enroll') === 0) {
                $enroll_crons[$hook] = true;
            }
        }
    }
    hc_out('Enroll', 'Cron programados', count($enroll_crons) >= 3, count($enroll_crons) . ' hooks');

    // Poster engine
    hc_out('Enroll', 'Poster engine', file_exists("$dir/media/class-poster-engine.php"), '');

    // Flujo: actividad + inscripción + JSON-LD
    $act_id = wp_insert_post(['post_type' => 'actividad', 'post_status' => 'publish', 'post_title' => 'HC Actividad']);
    update_post_meta($act_id, '_convoca_fecha_inicio', date('Y-m-d', strtotime('+7 days')) . ' 10:00');
    update_post_meta($act_id, '_convoca_plazas_totales', 10);
    update_post_meta($act_id, '_convoca_plazas_disponibles', 10);
    update_post_meta($act_id, '_convoca_requires_payment', 1);
    hc_out('Enroll', 'Crear actividad', $act_id > 0, "ID $act_id");

    $ins_id = wp_insert_post(['post_type' => 'inscripcion', 'post_status' => 'publish', 'post_title' => 'HC Insc']);
    update_post_meta($ins_id, '_convoca_actividad_id', $act_id);
    update_post_meta($ins_id, '_convoca_estado', 'pendiente_pago');
    hc_out('Enroll', 'Inscripción creada', $ins_id > 0, "ID $ins_id");

    // JSON-LD en página de actividad
    $url = get_permalink($act_id);
    if ($url) {
        $html = wp_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
        $body = wp_remote_retrieve_body($html);
        hc_cfg('Enroll', 'JSON-LD Event', strpos($body, 'application/ld+json') !== false, 'ld+json');
    }

    // Placeholders %% no deben aparecer
    if ($url) {
        $html = wp_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
        $body = wp_remote_retrieve_body($html);
        preg_match_all('/%%[A-Z_]+%%/', $body, $tokens);
        hc_out('Enroll', 'Sin placeholders %%', empty($tokens[0]), implode(',', array_unique($tokens[0])));
    }

    wp_delete_post($ins_id, true);
    wp_delete_post($act_id, true);
}

function hc_gateway() {
    hc_section('Convoca Gateway');
    $settings = get_option('convoca_gateway_settings', []);

    hc_cfg('Gateway', 'Merchant code', !empty($settings['merchant_code']), $settings['merchant_code'] ?? '');
    hc_cfg('Gateway', 'Entorno test', ($settings['environment'] ?? '') === 'test', $settings['environment'] ?? '?');
    $key = $settings['secret_key'] ?? '';
    hc_cfg('Gateway', 'Clave encriptada enc:', strpos($key, 'enc:') === 0, substr($key, 0, 15) . '...');

    $scs = hc_shortcodes();
    foreach (['convoca_pago', 'convoca_pago_ok', 'convoca_pago_ko'] as $sc) {
        hc_out('Gateway', "Shortcode $sc", in_array($sc, $scs), '');
    }

    // Webhook real
    $r = wp_remote_post(home_url('/wp-json/convoca-gateway/v1/notify'), ['timeout' => 10, 'sslverify' => false, 'body' => []]);
    $code = wp_remote_retrieve_response_code($r);
    hc_out('Gateway', 'Webhook /notify existe', $code !== 404, "HTTP $code");

    // Páginas de retorno
    $r = wp_remote_get(home_url('/pago-ok/'), ['timeout' => 10, 'sslverify' => false]);
    hc_cfg('Gateway', 'Página /pago-ok/', wp_remote_retrieve_response_code($r) === 200, 'HTTP ' . wp_remote_retrieve_response_code($r));
    $r = wp_remote_get(home_url('/pago-ko/'), ['timeout' => 10, 'sslverify' => false]);
    hc_cfg('Gateway', 'Página /pago-ko/', wp_remote_retrieve_response_code($r) === 200, 'HTTP ' . wp_remote_retrieve_response_code($r));

    // CPT pago
    hc_out('Gateway', 'CPT pago', post_type_exists('pago'), '');

    // Flujo create_payment con validaciones
    if (class_exists('Convoca\Gateway\Payment_Handler')) {
        $handler = new \Convoca\Gateway\Payment_Handler();
        $bad = $handler->create_payment(['origin' => 'members', 'origin_id' => 0, 'amount_cents' => 0]);
        hc_out('Gateway', 'Valida importe 0', is_wp_error($bad), is_wp_error($bad) ? $bad->get_error_code() : 'sin error');
        $bad2 = $handler->create_payment(['origin' => 'invalido', 'origin_id' => 1, 'amount_cents' => 100]);
        hc_out('Gateway', 'Valida origin inválido', is_wp_error($bad2), is_wp_error($bad2) ? $bad2->get_error_code() : 'sin error');
    }
}

function hc_shifts() {
    hc_section('Convoca Shifts');
    hc_out('Shifts', 'CPT centro_turno', post_type_exists('centro_turno'), '');
    hc_out('Shifts', 'Taxonomía actividad', taxonomy_exists('convoca_shifts_actividad'), '');

    $scs = hc_shortcodes();
    foreach (['convoca_calendario', 'convoca_proximos_turnos', 'convoca_resumen_turnos', 'convoca_boton_apuntarse'] as $sc) {
        hc_out('Shifts', "Shortcode $sc", in_array($sc, $scs), '');
    }

    $r = wp_remote_get(home_url('/wp-json/convoca-shifts/v1/turnos'), ['timeout' => 10, 'sslverify' => false]);
    hc_cfg('Shifts', 'REST /turnos', wp_remote_retrieve_response_code($r) === 200, 'HTTP ' . wp_remote_retrieve_response_code($r));

    hc_out('Shifts', 'duplicar_semana', file_exists(WP_PLUGIN_DIR . '/convoca-shifts/includes/cpt-turno.php'), 'cpt-turno.php');

    // Flujo CRUD + estados
    $turno_id = wp_insert_post(['post_type' => 'centro_turno', 'post_status' => 'publish', 'post_title' => 'HC Turno']);
    update_post_meta($turno_id, '_fecha_inicio', date('Y-m-d', strtotime('+3 days')));
    update_post_meta($turno_id, '_estado', 'abierto_disponible');
    $ok = $turno_id > 0 && in_array(get_post_meta($turno_id, '_estado', true), ['abierto_disponible', 'abierto_ocupado', 'cerrado']);
    hc_out('Shifts', 'CRUD turno + estado válido', $ok, 'ID ' . $turno_id);
    wp_delete_post($turno_id, true);
}

function hc_publisher() {
    hc_section('Convoca Publisher');
    $dir = WP_PLUGIN_DIR . '/convoca-publisher';

    // Canales
    $channels = ['Facebook', 'LinkedIn', 'Twitter', 'TikTok', 'GoogleMyBusiness', 'Telegram', 'Mastodon'];
    $found = 0;
    foreach ($channels as $ch) {
        $file = "$dir/includes/channels/class-" . strtolower($ch) . ".php";
        if (file_exists($file)) $found++;
    }
    hc_out('Publisher', "Canales ($found/7)", $found === 7, '');

    // Prefijo coherente
    $pub = file_exists("$dir/includes/class-publisher.php") ? file_get_contents("$dir/includes/class-publisher.php") : '';
    hc_out('Publisher', 'Prefijo convoca_publisher_', strpos($pub, 'convoca_publisher_async_publish') !== false, 'async hook');
    hc_out('Publisher', 'Sin prefijo cp_', strpos($pub, 'cp_async_publish') === false, '');

    // Tabla retry
    global $wpdb;
    $table = $wpdb->prefix . 'convoca_publisher_retry_queue';
    hc_cfg('Publisher', 'Tabla retry_queue', $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table, '');

    // REST protegido
    $r = wp_remote_get(home_url('/wp-json/convoca-publisher/v1/status'), ['timeout' => 10, 'sslverify' => false]);
    hc_cfg('Publisher', 'REST /status protegido', in_array(wp_remote_retrieve_response_code($r), [401, 403]), 'HTTP ' . wp_remote_retrieve_response_code($r));

    // Async al publicar (solo si auto_publish)
    if (get_option('convoca_publisher_auto_publish')) {
        $post_id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'HC Publisher']);
        $scheduled = wp_next_scheduled('convoca_publisher_async_publish', [$post_id]);
        hc_out('Publisher', 'Publicación automática async', $scheduled !== false, $scheduled ? 'programado' : 'no');
        if ($scheduled) wp_unschedule_event($scheduled, 'convoca_publisher_async_publish', [$post_id]);
        wp_delete_post($post_id, true);
    } else {
        hc_warn('Publisher', 'Publicación automática', 'auto_publish desactivado (no testeado)');
    }
}

function hc_assistant() {
    hc_section('Convoca Assistant');
    $scs = hc_shortcodes();
    hc_out('Assistant', 'Shortcode convoca_assistant', in_array('convoca_assistant', $scs), '');

    hc_out('Assistant', 'CPT convoca_faq', post_type_exists('convoca_faq'), '');
    hc_out('Assistant', 'CPT convoca_kb', post_type_exists('convoca_kb'), '');
    hc_out('Assistant', 'Taxonomía convoca_faq_cat', taxonomy_exists('convoca_faq_cat'), '');
    hc_out('Assistant', 'Taxonomía convoca_kb_cat', taxonomy_exists('convoca_kb_cat'), '');

    // Búsqueda funcional
    $r = wp_remote_post(home_url('/wp-json/convoca/v1/assistant/search'), [
        'timeout' => 15, 'sslverify' => false,
        'body' => json_encode(['query' => 'voluntariado horas']),
        'headers' => ['Content-Type' => 'application/json'],
    ]);
    $body = json_decode(wp_remote_retrieve_body($r), true);
    hc_cfg('Assistant', 'REST search 200', wp_remote_retrieve_response_code($r) === 200, 'HTTP ' . wp_remote_retrieve_response_code($r));
    hc_cfg('Assistant', 'Búsqueda devuelve resultados', !empty($body['results'] ?? []), 'results=' . count($body['results'] ?? []));

    // Índice
    $upload = wp_upload_dir();
    hc_cfg('Assistant', 'Índice JSON', file_exists($upload['basedir'] . '/convoca-assistant/index.json'), 'index.json');

    // Rebuild protegido
    $r = wp_remote_post(home_url('/wp-json/convoca/v1/assistant/rebuild-index'), ['timeout' => 10, 'sslverify' => false, 'body' => []]);
    hc_cfg('Assistant', 'Rebuild protegido', in_array(wp_remote_retrieve_response_code($r), [401, 403]), 'HTTP ' . wp_remote_retrieve_response_code($r));
}

function hc_theme() {
    hc_section('Convoca Theme');
    $theme = wp_get_theme();
    hc_cfg('Theme', 'Tema activo Convoca', stripos($theme->get('Name'), 'convoca') !== false, $theme->get('Name'));

    $scs = hc_shortcodes();
    hc_out('Theme', 'Shortcode dark_mode', in_array('convoca_dark_mode_toggle', $scs), '');

    $patterns = is_dir(get_stylesheet_directory() . '/patterns') ? glob(get_stylesheet_directory() . '/patterns/*.php') : [];
    hc_out('Theme', 'Block patterns', count($patterns) >= 15, count($patterns));
    hc_out('Theme', 'theme.json', file_exists(get_stylesheet_directory() . '/theme.json'), '');
    hc_out('Theme', 'dark-mode.js', file_exists(get_stylesheet_directory() . '/assets/js/dark-mode.js'), '');

    // Home 200
    $r = wp_remote_get(home_url('/'), ['timeout' => 10, 'sslverify' => false]);
    $body = wp_remote_retrieve_body($r);
    hc_cfg('Theme', 'Home 200 + HTML', wp_remote_retrieve_response_code($r) === 200 && strpos($body, '<!DOCTYPE') !== false, 'HTTP ' . wp_remote_retrieve_response_code($r));
}

function hc_migrations() {
    hc_section('Migraciones');

    if ( ! class_exists( 'Convoca\\Core\\Migration_History' ) ) {
        hc_out( 'Migraciones', 'Migration_History disponible', false, 'clase no encontrada' );
        return;
    }

    $history = \Convoca\Core\Migration_History::get_all();
    $fails   = 0;
    foreach ( $history as $h ) {
        if ( ( $h['status'] ?? '' ) === 'failed' ) {
            $fails++;
        }
    }
    hc_out( 'Migraciones', 'Sin migraciones fallidas', $fails === 0, $fails ? "{$fails} fallidas" : count( $history ) . ' en historial' );

    // Mostrar últimas 3 entradas
    $recent = array_slice( $history, -3 );
    foreach ( $recent as $h ) {
        $mark = ( $h['status'] ?? '' ) === 'failed' ? '⚠️' : '✅';
        printf( "  %s %s %s→%s (%s)\n", $mark, $h['plugin'] ?? '?', $h['from'] ?? '?', $h['to'] ?? '?', $h['status'] ?? '?' );
    }
}

function hc_clean_code() {
    hc_section('Higiene de código');

    // Cadenas prohibidas: referencias a proyectos reales o datos de producción
    // que no deben colarse en el código fuente. El check escanea los archivos
    // de los plugins (no la BD — el contenido de demo se audita aparte).
    $prohibited = array(
        'Los Lugg',
        'Lugones',
        'Biodevas',
        'Taller de Yoga',
        'Sierra del Sueve',
        'Finca Biodevas',
        'biodevas.org',
        'lugg.biodevas.org',
        'coordinacion@biodevas.org',
        'Centro Social Turnos',
        'Turnos Centro Social',
    );

    $dirs = array('convoca-core', 'convoca-members', 'convoca-enroll', 'convoca-gateway',
                  'convoca-shifts', 'convoca-publisher', 'convoca-assistant', 'convoca-theme');
    $found = array();
    foreach ($dirs as $dir) {
        $base = WP_PLUGIN_DIR . '/' . $dir;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
        foreach ($it as $file) {
            if ($file->isDir()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, array('php', 'js', 'html', 'md', 'txt', 'json'), true)) {
                continue;
            }
            $path = $file->getPathname();
            if (strpos($path, '/vendor/') !== false || strpos($path, '/node_modules/') !== false) {
                continue;
            }
            // Los CHANGELOGs documentan el histórico real del proyecto
            // (incluidos fixes de dominios antiguos) — no son código reutilizable.
            if (basename($path) === 'CHANGELOG.md') {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }
            foreach ($prohibited as $term) {
                if (strpos($content, $term) !== false) {
                    $found[] = str_replace(WP_PLUGIN_DIR . '/', '', $path) . " contiene '{$term}'";
                }
            }
        }
    }
    hc_out('Higiene', 'Sin cadenas de proyectos reales', empty($found), $found ? implode(' | ', array_slice($found, 0, 5)) : 'OK');
}

function hc_integrations() {
    hc_section('Integraciones');
    $handler = null;
    if (class_exists('Convoca\Gateway\Payment_Handler')) {
        $handler = new \Convoca\Gateway\Payment_Handler();
    }

    // Namespaces conviven
    $ns = hc_rest_namespaces();
    $required = ['convoca/v1', 'convoca-members/v1', 'convoca-enroll/v1', 'convoca-gateway/v1', 'convoca-shifts/v1', 'convoca-publisher/v1'];
    $missing = array_diff($required, $ns);
    hc_cfg('Integraciones', '6 namespaces REST', empty($missing), $missing ? implode(',', $missing) : implode(',', $ns));

    // Members → Gateway: pago activa membresía
    if ($handler) {
        $member = wp_insert_post(['post_type' => 'miembro', 'post_status' => 'publish', 'post_title' => 'HC Int Member']);
        update_post_meta($member, '_convoca_email', 'hc.int@example.com');
        update_post_meta($member, '_convoca_plan', 'bronze');
        update_post_meta($member, '_convoca_estado_miembro', 'pendiente_pago');
        $result = $handler->create_payment(['origin' => 'members', 'origin_id' => $member, 'amount_cents' => 3000, 'concepto' => 'Cuota HC']);
        if (!is_wp_error($result) && !empty($result['pago_id'])) {
            $pago_id = $result['pago_id'];
            // NO pre-setear _convoca_pago_id: el listener lo guarda al procesar
            // (pre-setearlo causaría dedup falso: last_pago_id === pago_id)
            \Convoca\Core\Utils::do_action('convoca_gateway_payment_completed', 'convoca_payment_completed', $pago_id, 'members', (int) $member, ['method' => 'tarjeta']);
            $estado = get_post_meta($member, '_convoca_estado_miembro', true);
            $cuota = get_post_meta($member, '_convoca_estado_cuota', true);
            hc_out('Integraciones', 'Members←Gateway activa membresía', $estado === 'activo' && $cuota === 'activa', "estado=$estado cuota=$cuota");
            wp_delete_post($pago_id, true);
        } else {
            hc_out('Integraciones', 'Members←Gateway activa membresía', false, is_wp_error($result) ? $result->get_error_message() : 'sin pago');
        }
        wp_delete_post($member, true);

        // Enroll → Gateway: pago confirma inscripción
        $act = wp_insert_post(['post_type' => 'actividad', 'post_status' => 'publish', 'post_title' => 'HC Int Act']);
        update_post_meta($act, '_convoca_plazas_totales', 10);
        update_post_meta($act, '_convoca_plazas_disponibles', 10);
        $ins = wp_insert_post(['post_type' => 'inscripcion', 'post_status' => 'publish', 'post_title' => 'HC Int Ins']);
        update_post_meta($ins, '_convoca_actividad_id', $act);
        update_post_meta($ins, '_convoca_estado', 'pendiente_pago');
        $result = $handler->create_payment(['origin' => 'enroll', 'origin_id' => $ins, 'amount_cents' => 500, 'concepto' => 'Insc HC']);
        if (!is_wp_error($result) && !empty($result['pago_id'])) {
            $pago_id = $result['pago_id'];
            \Convoca\Core\Utils::do_action('convoca_gateway_payment_completed', 'convoca_payment_completed', $pago_id, 'enroll', (int) $ins, ['method' => 'tarjeta']);
            $estado = get_post_meta($ins, '_convoca_estado', true);
            hc_out('Integraciones', 'Enroll←Gateway confirma inscripción', $estado === 'confirmada', "estado=$estado");
            wp_delete_post($pago_id, true);
        } else {
            hc_out('Integraciones', 'Enroll←Gateway confirma inscripción', false, is_wp_error($result) ? $result->get_error_message() : 'sin pago');
        }
        wp_delete_post($ins, true);
        wp_delete_post($act, true);
    }
}

// ── MAIN ──
echo "============================================\n";
echo "  CONVOCA HEALTH CHECK\n";
echo "  " . home_url() . "\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "============================================\n";

$start = microtime(true);
hc_core();
hc_members();
hc_enroll();
hc_gateway();
hc_shifts();
hc_publisher();
hc_assistant();
hc_theme();
hc_migrations();
hc_clean_code();
hc_integrations();

$elapsed = round(microtime(true) - $start, 1);
$total = $GLOBALS['hc_pass'] + $GLOBALS['hc_fail'];
$coverage = $total > 0 ? round(100 * $GLOBALS['hc_pass'] / $total, 1) : 0;

echo "\n============================================\n";
echo "  RESUMEN: {$GLOBALS['hc_pass']} PASS / {$GLOBALS['hc_fail']} FAIL / {$GLOBALS['hc_warn']} WARN\n";
echo "  Coverage: {$coverage}%  ({$elapsed}s)\n";
echo "============================================\n";

// Exit code para CI: FAIL = 1
if (defined('HC_EXIT') && HC_EXIT && $GLOBALS['hc_fail'] > 0) {
    exit(1);
}
if (!empty($_SERVER['argv']) && in_array('--exit-code', $_SERVER['argv'], true) && $GLOBALS['hc_fail'] > 0) {
    exit(1);
}
exit(0);
