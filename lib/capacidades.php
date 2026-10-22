<?php
/**
 * Capacidades: qué nombres no pueden usarse como capacidad por entrada de un tipo de contenido.
 *
 * Vive aparte porque la comprobación es pura (no necesita WordPress) y así se puede probar en
 * negativo con tipos sintéticos: un check que solo sabe decir PASS no vale para nada.
 *
 * @package Convoca\HealthCheck
 */

// Capacidades que WordPress (y el ecosistema) comprueban SIN id de entrada. Mapear una de estas
// como capacidad por entrada de un tipo de contenido la mete en $post_type_meta_caps y la deja
// resolviéndose como do_not_allow en cada comprobación global: el panel entero se cierra.
//
// OJO: `read_post`, `edit_post` y `delete_post` NO están en la lista porque son los NOMBRES
// legítimos de las meta caps (los tipos nativos los usan así y nadie los comprueba globalmente).
// La primera versión de este check los incluía y marcaba `post` y `attachment` como contaminados:
// el fallo era del check, no del sitio.
function hc_capacidades_globales() {
    return array('read', 'edit_posts', 'edit_others_posts', 'edit_private_posts',
        'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts',
        'delete_others_posts', 'delete_private_posts', 'delete_published_posts',
        'manage_options', 'convoca_manage_courses', 'convoca_grade_courses',
        'convoca_enroll_courses', 'convoca_view_certificates', 'manage_inscripciones');
}

// Detección pura (sin WordPress): recibe los tipos y devuelve las contaminaciones encontradas.
// Al ser pura se puede probar en negativo con un tipo sintético, que es como se demuestra que
// este check caza el patrón de verdad y no solo dice PASS.
function hc_capacidades_contaminadas($tipos) {
    $globales = hc_capacidades_globales();
    $contaminados = array();
    foreach ((array) $tipos as $slug => $obj) {
        if (empty($obj->cap) || empty($obj->map_meta_cap)) {
            continue;
        }
        foreach (array('edit_post', 'read_post', 'delete_post') as $por_entrada) {
            $valor = isset($obj->cap->$por_entrada) ? (string) $obj->cap->$por_entrada : '';
            if ('' !== $valor && in_array($valor, $globales, true)) {
                $contaminados[] = "{$slug}.{$por_entrada}={$valor}";
            }
        }
    }
    return $contaminados;
}
