<?php

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}
/**
 * Funciones de AJAX para el plugin de reservas y pedidos
 */

/**
 * Limpia la sesión de descuentos
 */


add_action('wp_ajax_limpiar_sesion_descuentos', 'limpiar_sesion_descuentos_callback');
add_action('wp_ajax_nopriv_limpiar_sesion_descuentos', 'limpiar_sesion_descuentos_callback');
add_action('wp_ajax_limpiar_sesion_descuentos_dni', 'limpiar_sesion_descuentos_dni_callback');
add_action('wp_ajax_nopriv_limpiar_sesion_descuentos_dni', 'limpiar_sesion_descuentos_dni_callback');
add_action('wp_ajax_limpiar_sesion_descuentos_nombre', 'limpiar_sesion_descuentos_nombre_callback');
add_action('wp_ajax_nopriv_limpiar_sesion_descuentos_nombre', 'limpiar_sesion_descuentos_nombre_callback');
add_action('wp_ajax_verificar_hermano', 'verificar_hermano_callback');
add_action('wp_ajax_nopriv_verificar_hermano', 'verificar_hermano_callback');
add_action('wp_ajax_get_horarios_stock', 'my_get_horarios_stock');
add_action('wp_ajax_nopriv_get_horarios_stock', 'my_get_horarios_stock');
add_action('wp_ajax_obtener_sesion_woocommerce', 'obtener_sesion_woocommerce_callback');
add_action('wp_ajax_nopriv_obtener_sesion_woocommerce', 'obtener_sesion_woocommerce_callback');
add_action('wp_ajax_update_redsys_installments', 'update_redsys_installments_callback');
add_action('wp_ajax_nopriv_update_redsys_installments', 'update_redsys_installments_callback');
add_action('wp_ajax_verificar_dni_tutor', 'verificar_dni_tutor_callback');
add_action('wp_ajax_nopriv_verificar_dni_tutor', 'verificar_dni_tutor_callback');



/**
 * Summary of update_redsys_installments_callback
 * @return void
 */
function update_redsys_installments_callback()
{
    $use_installments = isset($_POST['use_installments']) ? sanitize_text_field($_POST['use_installments']) : 'no';
    $product_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Guardar la opción en la sesión
    WC()->session->set('redsys_use_installments', $use_installments);

    error_log('RedSys Tokenización: Opción de fraccionamiento actualizada a: ' . $use_installments);

    // Valor de fracciones según la selección
    $fracciones_value = ($use_installments === 'yes') ? 2 : 0;

    // Si se envió un ID de producto específico, actualizar solo ese producto
    if ($product_id > 0) {
        WC()->session->set("fracciones_{$product_id}", $fracciones_value);
        WC()->session->set('redsys_use_installments', $use_installments);
        error_log('RedSys Tokenización: Actualizado valor de fracciones para producto ' . $product_id . ' a ' . $fracciones_value);
    }
    // Si no se envió ID, actualizar todos los productos del carrito
    else {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $cart_product_id = $cart_item['product_id'];
            WC()->session->set("fracciones_{$product_id}", 2); // o 0
            error_log('RedSys Tokenización: Actualizado valor de fracciones para producto ' . $cart_product_id . ' a ' . $fracciones_value);
        }
    }

    // Recalcular totales del carrito
    WC()->cart->calculate_totals();

    wp_send_json_success(array(
        'message' => 'Opción de fraccionamiento actualizada',
        'use_installments' => $use_installments,
        'product_id' => $product_id,
        'fracciones' => $fracciones_value
    ));
}

/**
 * Summary of my_get_horarios_stock
 * @return void
 */
function my_get_horarios_stock()
{
    global $wpdb;
    $tabla_semanas = $wpdb->prefix . 'semanas_campamento';
    $tabla_horarios = $wpdb->prefix . 'horarios_semana';

    // Consulta para obtener todas las semanas con sus horarios y plazas disponibles  
    $query = "  
        SELECT   
            s.id AS semana_id,  
            s.semana AS nombre_semana,  
            s.plazas_totales,  
            h.tipo_horario,  
            h.plazas AS plazas_disponibles,  
            CASE   
                WHEN h.plazas <= 0 THEN 'completo'  
                WHEN h.plazas <= 10 THEN 'limitado'  
                ELSE 'disponible'  
            END AS estado  
        FROM   
            $tabla_semanas s  
        JOIN   
            $tabla_horarios h ON s.id = h.semana_id  
        ORDER BY   
            s.id, h.tipo_horario  
    ";

    $results = $wpdb->get_results($query, ARRAY_A);

    // Si no hay resultados, verificar si las tablas existen  
    if (empty($results)) {
        // Verificar si las tablas existen  
        $semanas_exists = $wpdb->get_var("SHOW TABLES LIKE '$tabla_semanas'") == $tabla_semanas;
        $horarios_exists = $wpdb->get_var("SHOW TABLES LIKE '$tabla_horarios'") == $tabla_horarios;

        if (!$semanas_exists || !$horarios_exists) {
            wp_send_json_error([
                'message' => 'Las tablas de semanas o horarios no existen',
                'semanas_exists' => $semanas_exists,
                'horarios_exists' => $horarios_exists
            ]);
            return;
        }
    }

    // Reorganizar los datos para una estructura más útil en el frontend  
    $disponibilidad = [];

    foreach ($results as $row) {
        $semana_id = $row['semana_id'];

        if (!isset($disponibilidad[$semana_id])) {
            $disponibilidad[$semana_id] = [
                'id' => $semana_id,
                'nombre' => $row['nombre_semana'],
                'plazas_totales' => $row['plazas_totales'],
                'horarios' => []
            ];
        }

        $disponibilidad[$semana_id]['horarios'][$row['tipo_horario']] = [
            'plazas' => $row['plazas_disponibles'],
            'estado' => $row['estado']
        ];
    }

    // Convertir a array indexado para JSON  
    $disponibilidad = array_values($disponibilidad);

    // Añadir información adicional útil  
    $response = [
        'success' => true,
        'timestamp' => current_time('timestamp'),
        'disponibilidad' => $disponibilidad
    ];

    // Enviar respuesta JSON  
    wp_send_json($response);
}

function verificar_nombre_hermano($nombre_hermano)
{
    global $wpdb;

    if (empty($nombre_hermano)) {
        return false;
    }

    $nombre_normalizado = strtolower(trim($nombre_hermano));
    error_log("Buscando hermano con nombre: " . $nombre_normalizado);

    $sql = "
        SELECT COUNT(*) FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE pm.meta_key = 'nombre_alumno'
        AND LOWER(pm.meta_value) = %s
        AND p.post_type = 'shop_order'
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
    ";

    $resultado = $wpdb->get_var($wpdb->prepare($sql, $nombre_normalizado));

    error_log("Resultado SQL: " . $resultado);

    return $resultado > 0;
}


/**
 * Summary of verificar_hermano_callback
 * @return void
 */
function verificar_hermano_callback()
{
    $nombre = sanitize_text_field($_POST['nombre_hermano'] ?? '');
    $tiene = sanitize_text_field($_POST['tiene_hermano'] ?? '');

    if (!empty($nombre) && strtolower($tiene) === 'si' && verificar_nombre_hermano($nombre)) {
        WC()->session->set('tiene_hermano', 'valido');
        WC()->session->set('nombre_hermano', $nombre);
        wp_send_json_success();
    } else {
        wp_send_json_error([
            'mensaje' => 'No se encontró alumno.'
        ]);
    }
}

function verificar_dni_tutor_callback()
{
    global $wpdb;

    $nombre = sanitize_text_field($_POST['nombre_hermano'] ?? '');
    $dni = sanitize_text_field($_POST['dni_tutor'] ?? '');

    try {
        // Consulta para verificar si el hermano tiene una reserva pagada    
        $existe_dni = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta pm1
    INNER JOIN {$wpdb->prefix}postmeta pm2 ON pm1.post_id = pm2.post_id
    INNER JOIN {$wpdb->prefix}postmeta pm3 ON pm1.post_id = pm3.post_id
    WHERE LOWER(pm1.meta_value) = LOWER(%s)
    AND LOWER(pm3.meta_value) = LOWER(%s)",
            $nombre,
            $dni
        ));

        if ($existe_dni > 0) {
            WC()->session->set('dni_valido', 'valido');
            wp_send_json_success(array(
                'mensaje' => 'Dni verificado correctamente.',
                'existe_dni' => 1,
            ));

        } else {
            wp_send_json_error(array(
                'mensaje' => 'No se encontró DNI.',
                'existe_dni' => 0,
            ));
            //limpiar_sesion_descuentos_callback(false);
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'mensaje' => 'Ocurrió un error al verificar al alumno: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ));
    }
}
/**
 * Summary of limpiar_sesion_descuentos_callback
 * @return void
 */
function limpiar_sesion_descuentos_callback()
{
    if (!WC()->session) {
        wp_send_json_error('Sesión no disponible');
        return;
    }

    WC()->session->__unset('tiene_hermano');
    WC()->session->__unset('nombre_hermano');
    WC()->session->__unset('dni_valido');

    wp_send_json_success('Sesión limpiada');
}

/**
 * Summary of limpiar_sesion_descuentos_callback
 * @return void
 */
function limpiar_sesion_descuentos_dni_callback()
{
    if (!WC()->session) {
            wp_send_json_error('Sesión no disponible');
        return;
    }
    WC()->session->__unset('dni_valido');
    wp_send_json_success('Sesión limpiada');
}

/**
 * Summary of limpiar_sesion_descuentos_callback
 * @return void
 */
function limpiar_sesion_descuentos_nombre_callback()
{
    if (!WC()->session) {
            wp_send_json_error('Sesión no disponible');
        return;
    }
    WC()->session->__unset('tiene_hermano');
    WC()->session->__unset('nombre_hermano');
    wp_send_json_success('Sesión limpiada');
}

